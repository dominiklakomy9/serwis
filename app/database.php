<?php
/**
 * database.php — połączenie z bazą przez PDO (wzorzec singletona).
 *
 * Zwraca współdzielone połączenie PDO skonfigurowane bezpiecznie:
 *  - wyjątki zamiast cichych błędów,
 *  - prawdziwe prepared statements (emulacja wyłączona),
 *  - domyślny tryb pobierania jako tablica asocjacyjna.
 */

declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host    = (string) config('db.host', '127.0.0.1');
    $port    = (int) config('db.port', 3306);
    $name    = (string) config('db.name', '');
    $user    = (string) config('db.user', '');
    $pass    = (string) config('db.pass', '');
    $charset = (string) config('db.charset', 'utf8mb4');

    $dsn = "mysql:host={$host};port={$port};dbname={$name};charset={$charset}";

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_STRINGIFY_FETCHES  => false,
    ];

    try {
        $pdo = new PDO($dsn, $user, $pass, $options);
    } catch (PDOException $e) {
        // Logujemy szczegóły po stronie serwera, użytkownikowi pokazujemy ogólny błąd.
        app_log('db', 'Połączenie nieudane: ' . $e->getMessage());
        throw new RuntimeException('Błąd połączenia z bazą danych.');
    }

    // Spójna strefa czasowa sesji MySQL (UTC — konwersję robimy w PHP).
    $pdo->exec("SET time_zone = '+00:00'");

    return $pdo;
}
