<?php
/**
 * auth.php — uwierzytelnianie administratora i ochrona brute-force.
 */

declare(strict_types=1);

/**
 * Czy admin jest zalogowany (z uwzględnieniem wygaśnięcia sesji).
 */
function admin_is_logged_in(): bool
{
    if (empty($_SESSION['admin_id'])) {
        return false;
    }

    $idle = (int) config('security.session_idle_timeout', 1800);
    $last = (int) ($_SESSION['admin_last_activity'] ?? 0);
    if ($idle > 0 && $last > 0 && (time() - $last) > $idle) {
        admin_logout();
        return false;
    }
    $_SESSION['admin_last_activity'] = time();
    return true;
}

/**
 * Wymusza zalogowanie — w przeciwnym razie przekierowuje na stronę logowania.
 */
function admin_require_login(): void
{
    if (!admin_is_logged_in()) {
        redirect('/admin/login.php');
    }
}

/**
 * Zwraca dane zalogowanego administratora (lub null).
 */
function admin_current(): ?array
{
    if (!admin_is_logged_in()) {
        return null;
    }
    return [
        'id'    => (int) $_SESSION['admin_id'],
        'email' => (string) ($_SESSION['admin_email'] ?? ''),
        'name'  => (string) ($_SESSION['admin_name'] ?? ''),
    ];
}

/**
 * Liczba nieudanych prób logowania z danego IP w oknie czasowym.
 */
function login_recent_failures(string $ip): int
{
    $window = (int) config('security.login_window_sec', 900);
    $pdo = db();
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM login_attempts
         WHERE ip_address = :ip AND success = 0
           AND attempted_at >= (UTC_TIMESTAMP() - INTERVAL :win SECOND)'
    );
    $stmt->bindValue(':ip', $ip);
    $stmt->bindValue(':win', $window, PDO::PARAM_INT);
    $stmt->execute();
    return (int) $stmt->fetchColumn();
}

/**
 * Czy IP jest aktualnie zablokowane przez brute-force.
 */
function login_is_locked(string $ip): bool
{
    $max = (int) config('security.login_max_attempts', 5);
    return login_recent_failures($ip) >= $max;
}

/**
 * Zapis próby logowania (audyt + brute-force). Nigdy nie zapisujemy hasła.
 */
function login_record_attempt(string $ip, ?string $email, bool $success): void
{
    $pdo = db();
    $stmt = $pdo->prepare(
        'INSERT INTO login_attempts (ip_address, email, success, attempted_at)
         VALUES (:ip, :email, :success, UTC_TIMESTAMP())'
    );
    $stmt->execute([
        ':ip'      => $ip,
        ':email'   => $email !== null ? mb_substr($email, 0, 190) : null,
        ':success' => $success ? 1 : 0,
    ]);
}

/**
 * Próba logowania. Zwraca true przy sukcesie.
 * Ten sam komunikat błędu dla złego loginu i hasła (brak enumeracji kont).
 */
function admin_attempt_login(string $email, string $password): bool
{
    $ip = client_ip();
    $email = mb_strtolower(trim($email));

    $pdo = db();
    $stmt = $pdo->prepare('SELECT id, email, password_hash, full_name, is_active FROM admins WHERE email = :email LIMIT 1');
    $stmt->execute([':email' => $email]);
    $admin = $stmt->fetch();

    // Zawsze wykonaj weryfikację hasła (stały czas — utrudnia timing attack).
    $hash = $admin['password_hash'] ?? '$2y$12$............................................................';
    $valid = password_verify($password, $hash);

    if (!$admin || (int) $admin['is_active'] !== 1 || !$valid) {
        login_record_attempt($ip, $email, false);
        return false;
    }

    // Sukces — regeneracja identyfikatora sesji (ochrona session fixation).
    session_regenerate_id(true);
    $_SESSION['admin_id']            = (int) $admin['id'];
    $_SESSION['admin_email']         = $admin['email'];
    $_SESSION['admin_name']          = $admin['full_name'];
    $_SESSION['admin_last_activity'] = time();

    login_record_attempt($ip, $email, true);

    // Rehash, jeśli zmienił się domyślny koszt bcrypt.
    if (password_needs_rehash($admin['password_hash'], PASSWORD_BCRYPT, ['cost' => 12])) {
        $new = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $upd = $pdo->prepare('UPDATE admins SET password_hash = :h WHERE id = :id');
        $upd->execute([':h' => $new, ':id' => $admin['id']]);
    }

    $upd = $pdo->prepare('UPDATE admins SET last_login_at = UTC_TIMESTAMP() WHERE id = :id');
    $upd->execute([':id' => $admin['id']]);

    app_log('auth', 'Logowanie admina OK: ' . $email . ' z IP ' . $ip);
    return true;
}

/**
 * Wylogowanie i zniszczenie sesji.
 */
function admin_logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 42000,
            'path'     => $p['path'],
            'domain'   => $p['domain'],
            'secure'   => $p['secure'],
            'httponly' => $p['httponly'],
            'samesite' => $p['samesite'] ?? 'Lax',
        ]);
    }
    session_destroy();
}
