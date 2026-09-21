<?php
/**
 * security.php — sesja, nagłówki bezpieczeństwa i rate limiting.
 */

declare(strict_types=1);

/**
 * Startuje sesję z bezpiecznymi parametrami ciasteczka.
 */
function start_secure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $secure = is_https();

    session_name('PKSESSID');
    session_set_cookie_params([
        'lifetime' => 0,          // ciasteczko sesyjne
        'path'     => '/',
        'domain'   => '',
        'secure'   => $secure,     // Secure tylko po HTTPS
        'httponly' => true,        // niedostępne dla JS
        'samesite' => 'Lax',       // ochrona CSRF na poziomie ciasteczka
    ]);

    session_start();
}

/**
 * Wysyła zestaw nagłówków bezpieczeństwa HTTP.
 *
 * CSP jest restrykcyjne: wszystkie zasoby pochodzą wyłącznie z tej domeny
 * ('self'). Aplikacja nie korzysta z zewnętrznych CDN ani skryptów inline.
 */
function send_security_headers(): void
{
    if (headers_sent()) {
        return;
    }

    $csp = implode('; ', [
        "default-src 'self'",
        "base-uri 'self'",
        "form-action 'self'",
        "frame-ancestors 'none'",
        "img-src 'self' data:",
        "style-src 'self'",
        "script-src 'self'",
        "font-src 'self'",
        "connect-src 'self'",
        "object-src 'none'",
    ]);

    header('Content-Security-Policy: ' . $csp);
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=()');
    header('Cross-Origin-Opener-Policy: same-origin');
    header('Cross-Origin-Resource-Policy: same-origin');

    if (is_https()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }

    // Nie ujawniamy wersji PHP.
    header_remove('X-Powered-By');
}

/**
 * Generyczny rate limiting per IP + akcja, oparty o tabelę rate_limit_hits.
 * Zwraca true jeśli limit został PRZEKROCZONY (należy odrzucić żądanie).
 */
function rate_limit_exceeded(string $action, int $maxHits, int $windowSec): bool
{
    $ip = client_ip();
    $pdo = db();

    // Policz trafienia w oknie czasowym.
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM rate_limit_hits
         WHERE ip_address = :ip AND action = :action
           AND created_at >= (UTC_TIMESTAMP() - INTERVAL :win SECOND)'
    );
    $stmt->bindValue(':ip', $ip);
    $stmt->bindValue(':action', $action);
    $stmt->bindValue(':win', $windowSec, PDO::PARAM_INT);
    $stmt->execute();
    $count = (int) $stmt->fetchColumn();

    if ($count >= $maxHits) {
        return true;
    }

    // Zarejestruj bieżące trafienie.
    $ins = $pdo->prepare(
        'INSERT INTO rate_limit_hits (ip_address, action, created_at)
         VALUES (:ip, :action, UTC_TIMESTAMP())'
    );
    $ins->execute([':ip' => $ip, ':action' => $action]);

    return false;
}

/**
 * Sprzątanie starych wpisów rate limitu (wywoływane sporadycznie).
 */
function rate_limit_gc(int $olderThanSec = 86400): void
{
    try {
        $pdo = db();
        $stmt = $pdo->prepare(
            'DELETE FROM rate_limit_hits
             WHERE created_at < (UTC_TIMESTAMP() - INTERVAL :sec SECOND)'
        );
        $stmt->bindValue(':sec', $olderThanSec, PDO::PARAM_INT);
        $stmt->execute();
    } catch (Throwable $e) {
        app_log('gc', 'rate_limit_gc: ' . $e->getMessage());
    }
}
