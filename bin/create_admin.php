<?php
/**
 * bin/create_admin.php — utworzenie / aktualizacja konta administratora z CLI.
 *
 * Użycie:
 *     php bin/create_admin.php <email> <haslo> ["Imię Nazwisko"]
 *
 * Skrypt haszuje hasło (bcrypt cost=12) i zapisuje konto w tabeli admins.
 * Jeśli konto o danym e-mailu istnieje — aktualizuje hasło i nazwę.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit("Ten skrypt można uruchomić wyłącznie z linii poleceń.\n");
}

require __DIR__ . '/../app/bootstrap.php';

$email = $argv[1] ?? null;
$password = $argv[2] ?? null;
$name = $argv[3] ?? 'Administrator';

if (!$email || !$password) {
    fwrite(STDERR, "Użycie: php bin/create_admin.php <email> <haslo> [\"Imię Nazwisko\"]\n");
    exit(1);
}

$email = mb_strtolower(trim($email));
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Nieprawidłowy adres e-mail.\n");
    exit(1);
}
if (strlen($password) < 8) {
    fwrite(STDERR, "Hasło powinno mieć co najmniej 8 znaków.\n");
    exit(1);
}

$hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

$pdo = db();
$stmt = $pdo->prepare(
    'INSERT INTO admins (email, password_hash, full_name, is_active, created_at, updated_at)
     VALUES (:email, :hash, :name, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP())
     ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash),
                             full_name = VALUES(full_name),
                             is_active = 1,
                             updated_at = UTC_TIMESTAMP()'
);
$stmt->execute([':email' => $email, ':hash' => $hash, ':name' => $name]);

echo "Konto administratora zapisane: {$email}\n";
