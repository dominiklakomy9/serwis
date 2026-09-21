<?php
/**
 * helpers.php — drobne funkcje pomocnicze używane w całej aplikacji.
 */

declare(strict_types=1);

/**
 * Bezpieczne wypisanie danych w HTML (ochrona XSS).
 */
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Zwraca bazowy URL aplikacji bez końcowego ukośnika.
 */
function base_url(string $path = ''): string
{
    $base = rtrim((string) config('app.base_url', ''), '/');
    if ($path === '') {
        return $base;
    }
    return $base . '/' . ltrim($path, '/');
}

/**
 * Przekierowanie i zakończenie skryptu.
 */
function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

/**
 * Odpowiedź JSON dla endpointów API.
 */
function json_response(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Odczyt body żądania jako tablica (obsługuje JSON i form-data).
 */
function request_input(): array
{
    $ctype = $_SERVER['CONTENT_TYPE'] ?? '';
    if (str_contains($ctype, 'application/json')) {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }
    return $_POST;
}

/**
 * Adres IP klienta (z uwzględnieniem odwrotnego proxy, jeśli skonfigurowane).
 */
function client_ip(): string
{
    // Uwaga: X-Forwarded-For ufamy tylko, gdy aplikacja stoi za zaufanym proxy.
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    return substr($ip, 0, 45);
}

/**
 * Mapowanie statusów technicznych na polskie etykiety (panel + status page).
 */
function status_label(string $status): string
{
    static $map = [
        'pending'              => 'Nowe',
        'confirmed'            => 'Potwierdzone',
        'in_progress'          => 'W realizacji',
        'waiting_for_customer' => 'Oczekuje na klienta',
        'completed'            => 'Zakończone',
        'cancelled'            => 'Anulowane',
        'no_show'              => 'Nieobecność',
    ];
    return $map[$status] ?? $status;
}

/**
 * Publiczny, uproszczony opis statusu dla klienta (strona /status.php).
 */
function public_status_label(string $status): string
{
    static $map = [
        'pending'              => 'Oczekuje na potwierdzenie',
        'confirmed'            => 'Termin potwierdzony',
        'in_progress'          => 'W trakcie diagnostyki / naprawy',
        'waiting_for_customer' => 'Oczekujemy na kontakt / decyzję',
        'completed'            => 'Zakończone — sprzęt gotowy do odbioru',
        'cancelled'            => 'Anulowane',
        'no_show'              => 'Nie dostarczono sprzętu w terminie',
    ];
    return $map[$status] ?? 'W trakcie';
}

/**
 * Ludzki typ sprzętu po polsku.
 */
function device_type_label(string $type): string
{
    static $map = [
        'laptop'  => 'Laptop',
        'desktop' => 'Komputer stacjonarny',
        'printer' => 'Drukarka',
        'monitor' => 'Monitor',
        'other'   => 'Inne',
    ];
    return $map[$type] ?? $type;
}
