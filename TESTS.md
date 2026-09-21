# Checklista testów

Poniższa lista obejmuje testy funkcjonalne i bezpieczeństwa. Kolumnę „Wynik”
uzupełnij podczas weryfikacji na docelowym serwerze.

## Rezerwacje

| # | Test | Oczekiwany rezultat | Wynik |
|---|------|---------------------|-------|
| 1 | Rejestracja rezerwacji (poprawne dane) | Zgłoszenie zapisane, numer `PK-ROK-NNNN`, token, status „Nowe” | |
| 2 | Podwójna rezerwacja tego samego slotu | Drugi klient: „Ten termin został właśnie zajęty. Wybierz inną godzinę.” | |
| 3 | Nieprawidłowa data (przeszłość / spoza zakresu) | Błąd walidacji, brak zapisu | |
| 4 | Nieprawidłowa godzina (spoza dostępności) | „Wybrany termin nie jest dostępny.” | |
| 5 | Pusty formularz | Komunikaty walidacji przy polach, brak zapisu | |
| 6 | Zbyt długie dane (imię, opis) | Dane przycięte do limitu / błąd, brak awarii | |
| 7 | Brak zgody na politykę prywatności | Nie można wysłać (wymagane) | |
| 8 | Honeypot wypełniony (bot) | Żądanie odrzucone | |
| 9 | Rate limiting (wiele zgłoszeń z 1 IP) | Po przekroczeniu limitu: HTTP 429 | |

## Bezpieczeństwo

| # | Test | Oczekiwany rezultat | Wynik |
|---|------|---------------------|-------|
| 10 | XSS w opisie problemu (`<script>`) | Dane wyświetlane bezpiecznie (`htmlspecialchars`), brak wykonania | |
| 11 | SQL Injection w polach / parametrach | Brak wpływu (prepared statements) | |
| 12 | CSRF — POST bez tokenu | HTTP 419 / odrzucenie | |
| 13 | Błędne logowanie do panelu | „Nieprawidłowy login lub hasło.” (bez enumeracji) | |
| 14 | Brute force (wiele błędnych prób) | Tymczasowa blokada logowania | |
| 15 | Dostęp do panelu bez logowania | Przekierowanie na `/admin/login.php` | |
| 16 | Dostęp do cudzego statusu (zły token) | „Nie znaleziono zlecenia…” (ten sam komunikat) | |
| 17 | Próba otwarcia `config.php` przez HTTP | Brak dostępu (poza DocumentRoot / blokada) | |
| 18 | Wygaśnięcie sesji admina (bezczynność) | Wylogowanie po czasie `session_idle_timeout` | |

## Urządzenia / responsywność / dostępność

| # | Test | Oczekiwany rezultat | Wynik |
|---|------|---------------------|-------|
| 19 | Telefon (Android / iPhone) | Menu hamburger, kalendarz i formularz czytelne | |
| 20 | Tablet / laptop / desktop | Poprawny układ, brak przewijania poziomego | |
| 21 | Nawigacja klawiaturą | Widoczny focus, dostępne wszystkie akcje | |
| 22 | Wybór slotu palcem | Przyciski min. 44px, wygodne | |

## Panel administratora

| # | Test | Oczekiwany rezultat | Wynik |
|---|------|---------------------|-------|
| 23 | Dodanie dostępności (09:00–13:00, 30 min) | Sloty generowane automatycznie co 30 min | |
| 24 | Zablokowanie pojedynczego slotu | Slot znika z listy wolnych u klienta | |
| 25 | Usunięcie bloku dostępności | Terminy przestają być oferowane | |
| 26 | Zmiana statusu zgłoszenia | Status zaktualizowany + wpis w historii | |
| 27 | E-mail po potwierdzeniu / anulowaniu | Klient otrzymuje powiadomienie (gdy SMTP włączony) | |
