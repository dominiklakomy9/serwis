# Audyt bezpieczeństwa i uwagi produkcyjne

Poniższy audyt opisuje zastosowane zabezpieczenia oraz ryzyka pozostające po
stronie wdrożenia. **Żadna aplikacja nie jest „w 100% bezpieczna”** — poniżej
wskazano konkretne środki oraz obszary wymagające uwagi administratora.

---

## SECURITY

**Zastosowane zabezpieczenia**
- **SQL Injection** — wyłącznie PDO z prawdziwymi prepared statements
  (`ATTR_EMULATE_PREPARES = false`); brak sklejania zapytań z danych wejściowych.
- **XSS** — każde wyświetlenie danych użytkownika przez `e()` (`htmlspecialchars`,
  `ENT_QUOTES`). Restrykcyjne CSP (`default-src 'self'`, brak inline JS).
- **CSRF** — token synchronizacyjny w sesji, weryfikowany dla wszystkich metod
  POST (formularze + nagłówek `X-CSRF-Token` dla fetch); `hash_equals`.
- **Sesje** — ciasteczka `HttpOnly`, `SameSite=Lax`, `Secure` przy HTTPS;
  `session_regenerate_id(true)` po logowaniu; automatyczne wygasanie przy bezczynności.
- **Hasła** — `password_hash` (bcrypt cost 12), `password_verify`,
  `password_needs_rehash`. Weryfikacja w stałym czasie nawet dla nieistniejącego konta.
- **Brute-force** — limit nieudanych prób per IP w oknie czasowym; ten sam
  komunikat „Nieprawidłowy login lub hasło.” (brak enumeracji kont).
- **Rate limiting** — formularz rezerwacji i sprawdzanie statusu (per IP).
- **Podwójna rezerwacja** — transakcja + `SELECT ... FOR UPDATE` **oraz** twardy
  `UNIQUE` na kolumnie generowanej `active_slot_key` (gwarancja na poziomie bazy).
- **Publiczny status** — wymaga numeru + 64-znakowego tokenu (`random_bytes`),
  porównanie `hash_equals`, brak danych osobowych w odpowiedzi.
- **Nagłówki** — CSP, `X-Content-Type-Options`, `X-Frame-Options: DENY`,
  `Referrer-Policy`, `Permissions-Policy`, HSTS (HTTPS), usunięty `X-Powered-By`.
- **Błędy** — `display_errors=0` na produkcji, logowanie po stronie serwera;
  użytkownik nie widzi szczegółów.
- **Honeypot** — ukryte pole wykrywające boty w formularzu rezerwacji.

**Pozostałe ryzyka / do uzupełnienia**
- **CAPTCHA** jest przygotowana, ale domyślnie wyłączona — przy dużym ruchu botów
  warto ją włączyć.
- **`client_ip()`** ufa `REMOTE_ADDR`. Jeśli aplikacja stoi za proxy/CDN, należy
  bezpiecznie obsłużyć `X-Forwarded-For` tylko dla zaufanych adresów.
- **WAF / fail2ban** — rate limiting działa na poziomie aplikacji; dodatkowa
  ochrona sieciowa jest zalecana.
- **Rotacja/retencja logów** — skonfiguruj logrotate; logi mogą zawierać adresy IP.
- **Klient SMTP** weryfikuje certyfikat TLS (`verify_peer`) — wymaga poprawnego
  łańcucha certyfikatów serwera pocztowego.

## UX

- Wyraźne rozróżnienie: rezerwacja = **termin dostarczenia**, nie czas naprawy
  (hero, sekcja „Jak to działa”, strona rezerwacji, FAQ).
- Kalendarz pokazuje wyłącznie dni z wolnymi terminami; brak ujawniania grafiku.
- Walidacja inline + komunikaty przy polach; przycisk wysyłki aktywny dopiero po
  wyborze terminu.
- Przy przechwyceniu slotu przez innego klienta lista godzin odświeża się automatycznie.
- Ryzyko: brak natywnej zmiany/anulowania terminu przez klienta — realizowane
  kontaktem (zgodnie z założeniami). Można rozbudować w przyszłości.

## BACKEND

- Warstwa aplikacji rozdzielona (bootstrap, database, auth, csrf, validation,
  booking, mailer, security) — czytelna i modularna.
- Spójna strefa czasowa (UTC w bazie, konwersja/prezentacja w PHP).
- Ryzyko: e-maile wysyłane synchronicznie — przy wolnym SMTP wydłużają odpowiedź.
  Błąd wysyłki nie przerywa rezerwacji (logowany). W przyszłości warto rozważyć
  kolejkę.

## BAZA

- `utf8mb4`, InnoDB, klucze obce, indeksy, `UNIQUE`, `created_at`/`updated_at`.
- Ochrona integralności slotu przez kolumnę generowaną + `UNIQUE`.
- Architektura gotowa pod protokół przyjęcia (`service_orders`, `devices`,
  `service_order_status_history`).
- Ryzyko: `available_dates_in_month()` iteruje po dniach — dla bardzo dużej liczby
  bloków dostępności warto dodać cache. Dla jednoosobowego serwisu bez znaczenia.

## RODO — do uzupełnienia przez administratora

- Uzupełnić dane administratora (NIP/adres/kontakt) w `privacy.php` i `terms.php`.
- Określić **okres retencji** danych klientów i zgłoszeń.
- Wskazać **podmioty przetwarzające** (hosting, dostawca poczty).
- Zweryfikować treść dokumentów z prawnikiem (są to szablony, nie porada prawna).
- Przy włączeniu usług zewnętrznych (Analytics, Maps, reCAPTCHA, CDN, fonty)
  zaktualizować politykę i baner cookies.

## PRODUKCJA — przed publikacją

1. `config/config.php` poza `DocumentRoot`; `DocumentRoot` = `public/`.
2. Ustawić `app.base_url`, `app.force_https = true`, `app.env = 'production'`.
3. Wdrożyć ważny certyfikat SSL; wymusić HTTPS (odkomentować regułę w `.htaccess`).
4. Zmienić hasło administratora (`php bin/create_admin.php ...`).
5. Skonfigurować SMTP i ustawić `mail.enabled = true`.
6. Nadać prawa zapisu do `storage/logs/`; skonfigurować logrotate.
7. Zaktualizować `robots.txt` i `sitemap.xml` (adres domeny).
8. Uzupełnić dane kontaktowe (telefon, e-mail, obszar działania).
9. Ustawić harmonogram kopii zapasowych bazy i konfiguracji.
10. Zweryfikować checklistę z `TESTS.md`.
