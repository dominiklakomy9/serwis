<?php
/**
 * bootstrap.php — wspólny punkt startowy dla wszystkich żądań.
 *
 * Ładuje konfigurację, ustawia bezpieczne parametry sesji i nagłówki,
 * konfiguruje obsługę błędów (logowanie po stronie serwera, brak wycieków
 * do przeglądarki) oraz dołącza pozostałe moduły warstwy aplikacji.
 */

declare(strict_types=1);

// --- Ścieżki bazowe -------------------------------------------------
define('APP_ROOT', dirname(__DIR__));
define('CONFIG_FILE', APP_ROOT . '/config/config.php');
define('STORAGE_PATH', APP_ROOT . '/storage');
define('LOG_PATH', STORAGE_PATH . '/logs');

// --- Wczytanie konfiguracji ----------------------------------------
if (!is_file(CONFIG_FILE)) {
    http_response_code(500);
    // Nie ujawniamy szczegółów ścieżek użytkownikowi.
    exit('Błąd konfiguracji serwera.');
}

/** @var array $CONFIG */
$CONFIG = require CONFIG_FILE;
$GLOBALS['__CONFIG'] = $CONFIG;

/**
 * Pobiera wartość z konfiguracji notacją kropkową, np. config('db.host').
 */
function config(string $key, $default = null)
{
    $parts = explode('.', $key);
    $value = $GLOBALS['__CONFIG'] ?? [];
    foreach ($parts as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
        } else {
            return $default;
        }
    }
    return $value;
}

// --- Strefa czasowa -------------------------------------------------
date_default_timezone_set((string) config('app.timezone', 'Europe/Warsaw'));

// Ścieżka bazowa aplikacji (obsługa instalacji w podkatalogu, np. /dlpogotowie/public).
define('BASE', rtrim((string) config('app.base_path', ''), '/'));

// --- Obsługa błędów -------------------------------------------------
$isDev = config('app.env') === 'development';

if (!is_dir(LOG_PATH)) {
    @mkdir(LOG_PATH, 0750, true);
}

ini_set('log_errors', '1');
ini_set('error_log', LOG_PATH . '/php_error.log');
ini_set('display_errors', $isDev ? '1' : '0');
error_reporting($isDev ? E_ALL : E_ALL & ~E_DEPRECATED & ~E_NOTICE);

/**
 * Zapis do dziennika aplikacji (bez danych wrażliwych!).
 */
function app_log(string $channel, string $message): void
{
    $line = sprintf("[%s] [%s] %s\n", date('c'), $channel, $message);
    @file_put_contents(LOG_PATH . '/app.log', $line, FILE_APPEND | LOCK_EX);
}

// Globalny handler wyjątków — logujemy szczegóły, użytkownik widzi ogólny komunikat.
set_exception_handler(function (Throwable $e) use ($isDev) {
    app_log('exception', $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    if (PHP_SAPI !== 'cli') {
        if (($_SERVER['HTTP_ACCEPT'] ?? '') && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json')) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'Wystąpił błąd serwera.']);
        } else {
            echo 'Wystąpił błąd serwera. Spróbuj ponownie później.';
        }
    }
    exit;
});

// --- Wykrycie HTTPS -------------------------------------------------
function is_https(): bool
{
    if (config('app.force_https')) {
        return true;
    }
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    if (($_SERVER['SERVER_PORT'] ?? '') == 443) {
        return true;
    }
    if (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') {
        return true;
    }
    return false;
}

// --- Dołączenie modułów warstwy aplikacji ---------------------------
require_once APP_ROOT . '/app/helpers.php';
require_once APP_ROOT . '/app/security.php';
require_once APP_ROOT . '/app/database.php';
require_once APP_ROOT . '/app/csrf.php';
require_once APP_ROOT . '/app/validation.php';
require_once APP_ROOT . '/app/auth.php';
require_once APP_ROOT . '/app/booking.php';
require_once APP_ROOT . '/app/mailer.php';

// --- Bezpieczna sesja (poza CLI) ------------------------------------
if (PHP_SAPI !== 'cli') {
    start_secure_session();
    send_security_headers();
}
