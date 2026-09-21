<?php
/**
 * /api/status.php — publiczne sprawdzenie statusu zlecenia (POST, JSON).
 *
 * Wejście: { number, token }
 * Wyjście: { ok, number, status, status_label, updated_at }
 *
 * Zasady prywatności:
 *  - do sprawdzenia wymagany jest numer ORAZ nieprzewidywalny token,
 *  - nie zwracamy żadnych danych osobowych,
 *  - ten sam ogólny komunikat przy błędnym numerze i błędnym tokenie
 *    (brak możliwości enumeracji cudzych zleceń),
 *  - rate limiting utrudnia zgadywanie tokenów.
 */

declare(strict_types=1);
// Odszukanie warstwy aplikacji niezależnie od układu katalogów.
$__bootstrap = null;
foreach (['/app/bootstrap.php', '/../app/bootstrap.php', '/../../app/bootstrap.php'] as $__cand) {
    if (@is_file(__DIR__ . $__cand)) { $__bootstrap = __DIR__ . $__cand; break; }
}
require $__bootstrap;

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    json_response(['ok' => false, 'error' => 'Metoda niedozwolona.'], 405);
}

csrf_require(true);

if (rate_limit_exceeded('status_check', 20, 3600)) {
    json_response(['ok' => false, 'error' => 'Zbyt wiele prób. Spróbuj ponownie później.'], 429);
}

$input = request_input();
$number = v_clean_string($input['number'] ?? '', 20);
$token  = v_clean_string($input['token'] ?? '', 64);

// Format tokenu: 64 znaki hex.
$genericError = ['ok' => false, 'error' => 'Nie znaleziono zlecenia o podanych danych.'];

if ($number === '' || !preg_match('/^[a-f0-9]{64}$/i', $token)) {
    json_response($genericError, 404);
}

try {
    $pdo = db();
    $stmt = $pdo->prepare(
        'SELECT appointment_number, status, status_token, updated_at
         FROM appointments WHERE appointment_number = :num LIMIT 1'
    );
    $stmt->execute([':num' => $number]);
    $row = $stmt->fetch();

    // Porównanie tokenu w stałym czasie (ochrona przed timing attack).
    $tokenOk = $row && hash_equals((string) $row['status_token'], $token);

    if (!$row || !$tokenOk) {
        json_response($genericError, 404);
    }

    json_response([
        'ok'           => true,
        'number'       => $row['appointment_number'],
        'status'       => $row['status'],
        'status_label' => public_status_label($row['status']),
        'updated_at'   => $row['updated_at'],
    ]);
} catch (Throwable $e) {
    app_log('api', 'status.php: ' . $e->getMessage());
    json_response(['ok' => false, 'error' => 'Błąd serwera.'], 500);
}
