# Dominik Łakomy - Pogotowie Komputerowe

Kompletna aplikacja WWW jednoosobowego serwisu komputerowego: strona prezentacyjna,
system rezerwacji **terminu dostarczenia sprzętu**, publiczne sprawdzanie statusu
zlecenia oraz panel administratora.

Stack: **PHP 8.2+**, **MySQL/MariaDB (PDO)**, czysty **HTML5 / CSS3 / JS (ES6)**.
Brak frameworków i zewnętrznych bibliotek.

> Uwaga: rezerwacja dotyczy wyłącznie **godziny dostarczenia** sprzętu, a nie czasu
> trwania naprawy.

---

## 1. Wymagania

- PHP **8.2+** z rozszerzeniami: `pdo_mysql`, `mbstring`, `openssl`
- MySQL **8.0+** lub MariaDB **10.5+**
- Serwer WWW (Apache/Nginx) z możliwością ustawienia `DocumentRoot`
- (Zalecane) certyfikat SSL / HTTPS

## 2. Struktura katalogów

```
/app          — warstwa aplikacji (logika, PDO, auth, mailer)  [poza DocumentRoot]
/config       — konfiguracja (config.php)                        [poza DocumentRoot]
/database     — schema.sql
/storage      — logi                                             [poza DocumentRoot]
/bin          — skrypty CLI (tworzenie admina)
/public       — KATALOG PUBLICZNY (DocumentRoot serwera)
    index.php, booking.php, booking-success.php, status.php,
    privacy.php, terms.php, robots.txt, sitemap.xml
    /api      — slots.php, create_booking.php, status.php
    /admin    — panel administratora
    /assets   — css, js, images
```

**Ważne:** publicznie dostępny jest wyłącznie katalog `public/`. Katalogi
`config/`, `app/`, `storage/`, `database/`, `bin/` muszą leżeć **poza** `DocumentRoot`.

## 3. Instalacja krok po kroku

### 3.1. Utworzenie bazy danych
```sql
CREATE DATABASE pogotowie CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'pogotowie_user'@'localhost' IDENTIFIED BY 'SILNE_HASLO';
GRANT ALL PRIVILEGES ON pogotowie.* TO 'pogotowie_user'@'localhost';
FLUSH PRIVILEGES;
```

### 3.2. Import schematu
```bash
mysql -u pogotowie_user -p pogotowie < database/schema.sql
```
Schemat tworzy tabele oraz konto administratora (patrz punkt 3.5).

### 3.3. Konfiguracja aplikacji
```bash
cp config/config.example.php config/config.php
```
Uzupełnij w `config/config.php`:
- dane bazy (`db.*`),
- dane kontaktowe (`contact.*`) — telefon, e-mail, obszar działania,
- `app.base_url` (adres domeny) oraz `app.force_https = true` na produkcji,
- dane SMTP (`mail.*`) i ustaw `mail.enabled = true`, aby wysyłać e-maile.

### 3.4. Konfiguracja serwera WWW

**Apache** — ustaw `DocumentRoot` na katalog `public/`:
```apache
<VirtualHost *:443>
    ServerName twojadomena.pl
    DocumentRoot /sciezka/do/projektu/public
    <Directory /sciezka/do/projektu/public>
        AllowOverride All
        Require all granted
    </Directory>
    # ... konfiguracja SSL ...
</VirtualHost>
```

**Nginx** (przykład):
```nginx
server {
    listen 443 ssl;
    server_name twojadomena.pl;
    root /sciezka/do/projektu/public;
    index index.php;

    location / { try_files $uri $uri/ =404; }
    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
    # Blokada dostępu do plików wrażliwych
    location ~ /\.(?!well-known) { deny all; }
}
```

### 3.5. Konto administratora

Schemat tworzy konto startowe:
- **E-mail:** `dominiklakomy9@gmail.com`
- **Hasło:** ustawione przy imporcie (hash bcrypt w `schema.sql`).

Aby zmienić hasło (zalecane po pierwszym logowaniu) lub utworzyć nowe konto:
```bash
php bin/create_admin.php dominiklakomy9@gmail.com "NoweSilneHaslo" "Dominik Łakomy"
```
Panel dostępny pod adresem: `https://twojadomena.pl/admin/`.

### 3.6. HTTPS
Na produkcji ustaw `app.force_https = true` w konfiguracji oraz odkomentuj
przekierowanie na HTTPS w `public/.htaccess`. Zapewnia to ciasteczka `Secure`
oraz nagłówek HSTS.

### 3.7. Konfiguracja e-mail (SMTP)
Uzupełnij `mail.smtp.*` (host, port, szyfrowanie, login, hasło) i ustaw
`mail.enabled = true`. Port 587 = STARTTLS, port 465 = SSL. Dane SMTP nigdy
nie są zapisywane w kodzie — wyłącznie w `config/config.php`.

### 3.8. Uprawnienia katalogu logów
```bash
chmod -R 750 storage
# katalog storage/logs musi być zapisywalny dla użytkownika serwera WWW
```

## 4. Kopie zapasowe (backup)

Regularnie wykonuj kopię:
- **bazy danych** — `mysqldump -u user -p pogotowie > backup_$(date +%F).sql`
- **konfiguracji** — plik `config/config.php`
- **dokumentów serwisowych** (gdy moduł protokołów zostanie rozbudowany)

Backupów **nie przechowuj w katalogu publicznym** (`public/`). Trzymaj je poza
serwerem WWW i szyfruj, jeśli zawierają dane osobowe.

## 5. Logi i audyt

Katalog `storage/logs/` zawiera:
- `app.log` — zdarzenia aplikacji (logowania admina, zmiany statusów, zmiany
  dostępności, odrzucone żądania CSRF, rate limit),
- `php_error.log` — błędy PHP.

W logach **nie zapisujemy haseł** ani zbędnych danych osobowych.

## 6. CAPTCHA (opcjonalnie)

Domyślnie wyłączona. Aby włączyć, ustaw w `config.php`:
```php
'captcha' => ['enabled' => true, 'provider' => 'recaptcha',
              'site_key' => '...', 'secret_key' => '...'],
```
oraz dodaj skrypt widgetu dostawcy (pamiętając o aktualizacji CSP i polityki
prywatności o wpływie usługi zewnętrznej).

## 7. Checklista testów

Patrz plik [`TESTS.md`](TESTS.md).

## 8. Audyt bezpieczeństwa i uwagi produkcyjne

Patrz plik [`AUDIT.md`](AUDIT.md).

---

© Dominik Łakomy - Pogotowie Komputerowe
