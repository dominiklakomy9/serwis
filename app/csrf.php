<?php
/**
 * csrf.php — ochrona przed atakami CSRF (Cross-Site Request Forgery).
 *
 * Wzorzec: synchronizer token pattern. Token trzymany w sesji, przekazywany
 * w formularzu (pole ukryte) lub nagłówku X-CSRF-Token dla żądań fetch.
 */

declare(strict_types=1);

/**
 * Zwraca aktualny token CSRF (tworzy go, jeśli nie istnieje).
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Gotowe pole ukryte do wstawienia w formularzu.
 */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/**
 * Weryfikuje token z żądania. Zwraca true, jeśli poprawny.
 * Sprawdza pole POST 'csrf_token' oraz nagłówek 'X-CSRF-Token'.
 */
function csrf_validate(?string $token = null): bool
{
    if ($token === null) {
        $token = $_POST['csrf_token']
            ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
    }
    if (!is_string($token) || $token === '' || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Wymusza poprawny token CSRF lub kończy żądanie błędem.
 * $asJson = true dla endpointów API.
 */
function csrf_require(bool $asJson = false): void
{
    if (csrf_validate()) {
        return;
    }
    app_log('csrf', 'Odrzucone żądanie bez poprawnego tokenu CSRF z IP ' . client_ip());
    if ($asJson) {
        json_response(['ok' => false, 'error' => 'Nieprawidłowy token bezpieczeństwa. Odśwież stronę i spróbuj ponownie.'], 419);
    }
    http_response_code(419);
    exit('Nieprawidłowy token bezpieczeństwa. Odśwież stronę i spróbuj ponownie.');
}
