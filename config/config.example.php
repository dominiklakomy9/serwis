<?php
/**
 * Konfiguracja aplikacji — SZABLON.
 *
 * Skopiuj ten plik do config.php i uzupełnij realnymi danymi:
 *     cp config/config.example.php config/config.php
 *
 * WAŻNE:
 * - Ten plik (config.php) NIE może znajdować się w katalogu publicznym (public/).
 *   Katalog /config leży poza DocumentRoot.
 * - Nie commituj config.php do repozytorium (patrz .gitignore).
 */

return [

    // --- Dane serwisu (wyświetlane na stronie) ---
    'app' => [
        'name'          => 'Dominik Łakomy - Pogotowie Komputerowe',
        'owner'         => 'Dominik Łakomy',
        'tagline'       => 'Pogotowie Komputerowe',
        // Bazowy adres URL bez końcowego ukośnika, np. https://twojadomena.pl
        'base_url'      => 'https://example.com',
        // Ścieżka bazowa, gdy aplikacja działa w podkatalogu (np. XAMPP: '/dlpogotowie/public').
        // Dla VirtualHost / domeny w katalogu głównym pozostaw pusty ''.
        'base_path'     => '',
        // Wymuś tryb HTTPS dla ciasteczek (Secure). Ustaw true na produkcji.
        'force_https'   => false,
        // Strefa czasowa
        'timezone'      => 'Europe/Warsaw',
        // Środowisko: 'production' lub 'development' (development pokazuje błędy)
        'env'           => 'production',
        // Prefiks numeru zgłoszenia -> np. PK-2026-0001
        'order_prefix'  => 'PK',
    ],

    // --- Dane kontaktowe (placeholdery — uzupełnij własne) ---
    'contact' => [
        'phone'          => '[NUMER TELEFONU]',
        'email'          => '[ADRES E-MAIL]',
        'service_area'   => '[OBSZAR DZIAŁANIA]',
    ],

    // --- Baza danych (MySQL / MariaDB) ---
    'db' => [
        'host'    => '127.0.0.1',
        'port'    => 3306,
        'name'    => 'pogotowie',
        'user'    => 'pogotowie_user',
        'pass'    => 'ZMIEN_TO_HASLO',
        'charset' => 'utf8mb4',
    ],

    // --- E-mail (własny serwer SMTP) ---
    'mail' => [
        'enabled'    => false,           // ustaw true, aby faktycznie wysyłać e-maile
        'from_email' => '[ADRES E-MAIL]',
        'from_name'  => 'Dominik Łakomy - Pogotowie Komputerowe',
        // Adres administratora do powiadomień o nowych zgłoszeniach
        'admin_email'=> '[ADRES E-MAIL ADMINA]',
        'smtp' => [
            'host'       => 'smtp.example.com',
            'port'       => 587,          // 587 = STARTTLS, 465 = SSL/TLS
            'encryption' => 'tls',        // 'tls' | 'ssl' | 'none'
            'username'   => 'login@example.com',
            'password'   => 'SMTP_HASLO',
            'timeout'    => 15,
        ],
    ],

    // --- Bezpieczeństwo ---
    'security' => [
        // Limit prób logowania admina w oknie czasowym
        'login_max_attempts' => 5,
        'login_window_sec'   => 900,   // 15 minut
        'login_lockout_sec'  => 900,   // blokada na 15 minut po przekroczeniu
        // Rate limiting formularza rezerwacji (per IP)
        'booking_max_per_hour' => 6,
        // Automatyczne wygaśnięcie sesji admina (bezczynność) w sekundach
        'session_idle_timeout' => 1800, // 30 minut
        // CAPTCHA — przygotowane jako opcja (domyślnie wyłączone)
        'captcha' => [
            'enabled'     => false,
            'provider'    => 'recaptcha', // 'recaptcha' | 'hcaptcha'
            'site_key'    => '',
            'secret_key'  => '',
        ],
    ],

    // --- Rezerwacje ---
    'booking' => [
        'default_interval'      => 30,   // minuty
        'max_problem_length'    => 1500, // limit znaków opisu problemu
        'max_days_in_advance'   => 60,   // jak daleko w przód można rezerwować
    ],
];
