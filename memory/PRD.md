# PRD — Dominik Łakomy - Pogotowie Komputerowe

## Problem statement (oryginał)
Kompletna, gotowa do wdrożenia strona jednoosobowego serwisu komputerowego
„Dominik Łakomy - Pogotowie Komputerowe”: prezentacja usług, system rezerwacji
TERMINU DOSTARCZENIA sprzętu (nie czasu naprawy), publiczny status zlecenia,
panel administratora, bezpieczeństwo security-first, RODO. Bez nazwy „BitFix”.

## Stack (decyzja użytkownika)
PHP 8.2+ / MySQL(MariaDB) / PDO + czysty HTML5/CSS3/JS. Bez frameworków.
Dostarczane jako gotowy kod do wdrożenia na własnym serwerze (NIE działa w
podglądzie Emergent — środowisko to React/FastAPI/Mongo).
E-mail: własny SMTP. CAPTCHA: opcja konfiguracyjna (wyłączona). Numeracja: PK-ROK-NNNN.
Admin startowy: dominiklakomy9@gmail.com (hash w schema.sql).

## Architektura
- `/public` = DocumentRoot (strony, /api, /admin, /assets)
- `/app` = warstwa logiki (bootstrap, database, auth, csrf, validation, booking, mailer, security, helpers) — poza DocumentRoot
- `/config` = config.php + config.example.php — poza DocumentRoot
- `/database/schema.sql` ; `/storage/logs` ; `/bin/create_admin.php`

## Zaimplementowane (2026-06)
- Strona główna: hero, usługi (8 kart, bez fikcyjnych cen), jak-to-działa (7 kroków),
  bezpieczeństwo danych, FAQ (10), kontakt (placeholdery).
- Rezerwacja: kalendarz (fetch dostępności per miesiąc/dzień), wybór slotu, formularz
  z walidacją, honeypot, zgoda RODO, potwierdzenie z numerem + tokenem.
- Ochrona podwójnej rezerwacji: transakcja + SELECT FOR UPDATE + UNIQUE na kolumnie
  generowanej `active_slot_key`. Zweryfikowane e2e (drugi klient dostaje „slot_taken”).
- Publiczny status (/status.php): numer + 64-hex token, hash_equals, brak danych osobowych.
- Panel admin: logowanie (brute-force lock, generyczny komunikat), dashboard, zgłoszenia
  (lista/filtry/szczegóły/zmiana statusu + historia), dostępność (bloki + blokada slotu),
  protokoły (scaffolding). Sesje bezpieczne, idle timeout, session_regenerate_id.
- Bezpieczeństwo: PDO prepared, CSRF (token+X-CSRF-Token), XSS (e()), CSP i nagłówki,
  rate limiting, logi w /storage/logs (bez haseł). Mailer SMTP (STARTTLS/SSL) bez zależności.
- SEO: title/description/OG/canonical/robots.txt/sitemap.xml/favicon.
- Dokumenty: README.md, TESTS.md, AUDIT.md, privacy.php, terms.php (szablony z placeholderami).

## Weryfikacja (curl e2e na lokalnej MariaDB)
Login admina, dodanie dostępności 09:00–11:00/30min → sloty ok, rezerwacja PK-2026-0001,
podwójna rezerwacja odrzucona, status ok, zły token odrzucony, CSRF odrzucony, walidacja
pustego formularza, brute-force lock po 5 próbach, brak dostępu do panelu bez logowania.
Wszystkie pliki PHP: `php -l` bez błędów.

## Backlog / przyszłość (P1/P2)
- Pełny moduł protokołu przyjęcia (formularz, zdjęcia, wydruk PDF) na gotowej strukturze bazy.
- Zmiana/anulowanie terminu przez klienta (self-service) zamiast kontaktu.
- Kolejka wysyłki e-maili (asynchronicznie).
- Panel: eksport CSV, wyszukiwarka zgłoszeń, statystyki.
- Włączenie CAPTCHA + baner cookies przy usługach zewnętrznych.

## Uwagi produkcyjne
Patrz AUDIT.md (sekcja PRODUKCJA) + README.md. Przed publikacją: config poza DocumentRoot,
HTTPS/force_https, zmiana hasła admina, konfiguracja SMTP, prawa do storage/logs, backupy.
