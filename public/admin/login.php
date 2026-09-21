<?php
/**
 * admin/login.php — logowanie do panelu.
 *
 * Zabezpieczenia: CSRF, ochrona brute-force (limit prób per IP),
 * ten sam komunikat dla błędnego loginu i hasła (brak enumeracji kont),
 * regeneracja sesji po udanym logowaniu (w admin_attempt_login).
 */
declare(strict_types=1);
require __DIR__ . '/../../app/bootstrap.php';

if (admin_is_logged_in()) {
    redirect('/admin/index.php');
}

$error = null;
$ip = client_ip();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!csrf_validate()) {
        $error = 'Sesja wygasła. Odśwież stronę i spróbuj ponownie.';
    } elseif (login_is_locked($ip)) {
        $error = 'Zbyt wiele nieudanych prób logowania. Spróbuj ponownie za kilka minut.';
        app_log('auth', 'Zablokowane logowanie (brute-force) z IP ' . $ip);
    } else {
        $email = (string) ($_POST['email'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        if (admin_attempt_login($email, $password)) {
            redirect('/admin/index.php');
        }
        $error = 'Nieprawidłowy login lub hasło.';
    }
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Logowanie — Panel administracyjny</title>
    <link rel="icon" href="/assets/images/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body class="admin-body login-body">
    <main class="login-wrap">
        <form class="login-card" method="post" action="/admin/login.php" data-testid="admin-login-form">
            <div class="login-brand">
                <span class="brand-mark" aria-hidden="true">DŁ</span>
                <div>
                    <strong>Panel administracyjny</strong><br>
                    <small>Pogotowie Komputerowe</small>
                </div>
            </div>

            <?php if ($error): ?>
                <div class="form-alert show error" role="alert" data-testid="login-error"><?= e($error) ?></div>
            <?php endif; ?>

            <?= csrf_field() ?>
            <div class="form-row">
                <label for="email">Adres e-mail</label>
                <input type="email" id="email" name="email" required autocomplete="username" autofocus data-testid="login-email">
            </div>
            <div class="form-row">
                <label for="password">Hasło</label>
                <input type="password" id="password" name="password" required autocomplete="current-password" data-testid="login-password">
            </div>
            <button type="submit" class="btn btn-primary btn-lg btn-block" data-testid="login-submit">Zaloguj się</button>
        </form>
    </main>
</body>
</html>
