<?php
/**
 * validation.php — walidacja i normalizacja danych wejściowych.
 *
 * Każda funkcja zwraca wartość znormalizowaną lub rzuca informację o błędzie
 * przez tablicę błędów przekazywaną referencyjnie. Walidacja jest zawsze
 * wykonywana po stronie serwera (JS traktujemy wyłącznie jako wygodę UX).
 */

declare(strict_types=1);

/**
 * Przycina i normalizuje ciąg (usuwa znaki sterujące, ogranicza długość).
 */
function v_clean_string($value, int $maxLen): string
{
    $s = is_string($value) ? $value : '';
    // Usuń znaki sterujące poza tabulatorem i nową linią.
    $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $s) ?? '';
    $s = trim($s);
    if (mb_strlen($s) > $maxLen) {
        $s = mb_substr($s, 0, $maxLen);
    }
    return $s;
}

/**
 * Walidacja imienia i nazwiska.
 */
function v_full_name($value, array &$errors): string
{
    $s = v_clean_string($value, 120);
    if (mb_strlen($s) < 3) {
        $errors['full_name'] = 'Podaj imię i nazwisko (min. 3 znaki).';
    } elseif (!preg_match('/^[\p{L}\p{M}\s\.\-\']+$/u', $s)) {
        $errors['full_name'] = 'Imię i nazwisko zawiera niedozwolone znaki.';
    }
    return $s;
}

/**
 * Walidacja polskiego numeru telefonu (elastyczna, ale rozsądna).
 */
function v_phone($value, array &$errors): string
{
    $s = v_clean_string($value, 30);
    // Zostaw tylko cyfry, spacje, +, -, nawiasy do oceny.
    $digits = preg_replace('/\D+/', '', $s);
    if (strlen($digits) < 9 || strlen($digits) > 15) {
        $errors['phone'] = 'Podaj poprawny numer telefonu (9–15 cyfr).';
    }
    return $s;
}

/**
 * Walidacja e-maila (opcjonalny — pusty jest dozwolony).
 */
function v_email_optional($value, array &$errors): ?string
{
    $s = v_clean_string($value, 190);
    if ($s === '') {
        return null;
    }
    if (!filter_var($s, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Podany adres e-mail jest nieprawidłowy.';
        return null;
    }
    return mb_strtolower($s);
}

/**
 * Walidacja typu sprzętu (musi być z listy).
 */
function v_device_type($value, array &$errors): string
{
    $allowed = ['laptop', 'desktop', 'printer', 'monitor', 'other'];
    $s = is_string($value) ? trim($value) : '';
    if (!in_array($s, $allowed, true)) {
        $errors['device_type'] = 'Wybierz rodzaj sprzętu z listy.';
        return 'other';
    }
    return $s;
}

/**
 * Walidacja opisu problemu.
 */
function v_problem($value, array &$errors): string
{
    $max = (int) config('booking.max_problem_length', 1500);
    $s = v_clean_string($value, $max);
    if (mb_strlen($s) < 5) {
        $errors['problem_description'] = 'Opisz krótko problem (min. 5 znaków).';
    }
    return $s;
}

/**
 * Walidacja daty w formacie YYYY-MM-DD. Zwraca obiekt DateTimeImmutable lub null.
 */
function v_date($value, array &$errors): ?DateTimeImmutable
{
    $s = is_string($value) ? trim($value) : '';
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $s);
    $err = DateTimeImmutable::getLastErrors();
    if (!$d || ($err && ($err['warning_count'] > 0 || $err['error_count'] > 0))) {
        $errors['date'] = 'Nieprawidłowa data.';
        return null;
    }

    $today = new DateTimeImmutable('today');
    if ($d < $today) {
        $errors['date'] = 'Nie można wybrać daty z przeszłości.';
        return null;
    }
    $maxDays = (int) config('booking.max_days_in_advance', 60);
    if ($d > $today->modify("+{$maxDays} days")) {
        $errors['date'] = 'Termin jest zbyt odległy.';
        return null;
    }
    return $d;
}

/**
 * Walidacja godziny w formacie HH:MM. Zwraca znormalizowane 'HH:MM:00' lub null.
 */
function v_time($value, array &$errors): ?string
{
    $s = is_string($value) ? trim($value) : '';
    if (!preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $s)) {
        $errors['time'] = 'Nieprawidłowa godzina.';
        return null;
    }
    return $s . ':00';
}
