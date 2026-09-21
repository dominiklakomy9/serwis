<?php
/**
 * /api/slots.php — publiczny endpoint dostępności (tylko odczyt).
 *
 *  GET ?action=month&month=YYYY-MM  -> { ok, dates: ["YYYY-MM-DD", ...] }
 *  GET ?date=YYYY-MM-DD             -> { ok, slots: ["09:00","09:30", ...] }
 *
 * Klient widzi wyłącznie WOLNE terminy. Nie ujawniamy grafiku pracy,
 * powodów niedostępności ani żadnych danych osobowych.
 */

declare(strict_types=1);
// Odszukanie warstwy aplikacji niezależnie od układu katalogów.
$__bootstrap = null;
foreach (['/app/bootstrap.php', '/../app/bootstrap.php', '/../../app/bootstrap.php'] as $__cand) {
    if (@is_file(__DIR__ . $__cand)) { $__bootstrap = __DIR__ . $__cand; break; }
}
require $__bootstrap;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    json_response(['ok' => false, 'error' => 'Metoda niedozwolona.'], 405);
}

$action = $_GET['action'] ?? 'day';

try {
    if ($action === 'month') {
        $month = (string) ($_GET['month'] ?? '');
        if (!preg_match('/^(\d{4})-(\d{2})$/', $month, $m)) {
            json_response(['ok' => false, 'error' => 'Nieprawidłowy miesiąc.'], 400);
        }
        $year = (int) $m[1];
        $mon = (int) $m[2];
        if ($mon < 1 || $mon > 12) {
            json_response(['ok' => false, 'error' => 'Nieprawidłowy miesiąc.'], 400);
        }
        $dates = available_dates_in_month($year, $mon);
        json_response(['ok' => true, 'dates' => $dates]);
    }

    // Domyślnie: sloty na konkretny dzień.
    $errors = [];
    $date = v_date($_GET['date'] ?? '', $errors);
    if ($date === null) {
        json_response(['ok' => false, 'error' => 'Nieprawidłowa data.'], 400);
    }
    $slots = free_slots_for_date($date->format('Y-m-d'));
    json_response(['ok' => true, 'slots' => $slots]);
} catch (Throwable $e) {
    app_log('api', 'slots.php: ' . $e->getMessage());
    json_response(['ok' => false, 'error' => 'Błąd serwera.'], 500);
}
