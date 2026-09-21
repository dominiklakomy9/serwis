<?php
/**
 * privacy.php — Polityka prywatności (SZABLON do uzupełnienia).
 *
 * UWAGA: To jest profesjonalny szablon, a NIE indywidualna porada prawna.
 * Uzupełnij dane administratora (placeholdery) i zweryfikuj treść przed publikacją.
 */
declare(strict_types=1);
// Odszukanie warstwy aplikacji niezależnie od układu katalogów
// (public/ obok app/  LUB  wszystko w jednym katalogu, np. public_html na hostingu współdzielonym).
$__bootstrap = null;
foreach (['/app/bootstrap.php', '/../app/bootstrap.php', '/../../app/bootstrap.php'] as $__cand) {
    if (@is_file(__DIR__ . $__cand)) { $__bootstrap = __DIR__ . $__cand; break; }
}
require $__bootstrap;

$pageTitle = 'Polityka prywatności';
$pageDescription = 'Informacje o przetwarzaniu danych osobowych w serwisie.';
$canonicalPath = '/privacy.php';

$owner = (string) config('app.owner');
$service = (string) config('app.name');
$email = (string) config('contact.email');
$phone = (string) config('contact.phone');
$area = (string) config('contact.service_area');

require __DIR__ . '/partials/header.php';
?>
<section class="section legal-page">
    <div class="container narrow">
        <h1>Polityka prywatności</h1>
        <p class="muted">Ostatnia aktualizacja: <?= e(date('Y-m-d')) ?></p>

        <div class="notice">
            <strong>Uwaga:</strong> Poniższy dokument stanowi szablon. Przed publikacją należy go dostosować do rzeczywistej działalności oraz — w razie potrzeby — skonsultować z prawnikiem. Nie jest to indywidualna porada prawna.
        </div>

        <h2>1. Administrator danych</h2>
        <p>Administratorem danych osobowych jest <strong><?= e($owner) ?></strong>, prowadzący serwis „<?= e($service) ?>”.</p>
        <ul>
            <li>Kontakt e-mail: <?= e($email) ?></li>
            <li>Telefon: <?= e($phone) ?></li>
            <li>Obszar działania: <?= e($area) ?></li>
            <li>[DANE FIRMY / NIP / ADRES — do uzupełnienia]</li>
        </ul>

        <h2>2. Jakie dane zbieramy</h2>
        <p>W związku z korzystaniem z formularza rezerwacji przetwarzamy następujące dane:</p>
        <ul>
            <li>imię i nazwisko,</li>
            <li>numer telefonu,</li>
            <li>adres e-mail (opcjonalnie),</li>
            <li>informacje o sprzęcie (rodzaj, producent/model),</li>
            <li>opis zgłaszanego problemu,</li>
            <li>termin rezerwacji dostarczenia sprzętu,</li>
            <li>dane techniczne niezbędne do działania serwisu (np. adres IP w logach, dane sesji).</li>
        </ul>
        <p>Nie zbieramy danych osobowych w zakresie szerszym, niż jest to konieczne do realizacji usługi.</p>

        <h2>3. Cele i podstawy przetwarzania</h2>
        <ul>
            <li>obsługa zgłoszenia i umówionego terminu dostarczenia sprzętu (realizacja usługi),</li>
            <li>kontakt w sprawie zgłoszenia,</li>
            <li>zapewnienie bezpieczeństwa i prawidłowego działania serwisu,</li>
            <li>ewentualne dochodzenie lub obrona roszczeń.</li>
        </ul>

        <h2>4. Okres przechowywania</h2>
        <p>Dane przechowujemy przez czas niezbędny do realizacji usługi oraz przez okres wynikający z przepisów prawa. [DOPRECYZUJ OKRES PRZECHOWYWANIA].</p>

        <h2>5. Odbiorcy danych</h2>
        <p>Dane mogą być przekazywane podmiotom wspierającym działanie serwisu (np. dostawca hostingu, dostawca poczty e-mail) wyłącznie w zakresie niezbędnym. [UZUPEŁNIJ LISTĘ PODMIOTÓW PRZETWARZAJĄCYCH].</p>

        <h2>6. Prawa osoby, której dane dotyczą</h2>
        <p>Przysługuje Ci prawo dostępu do danych, ich sprostowania, usunięcia, ograniczenia przetwarzania, przenoszenia oraz wniesienia sprzeciwu, a także prawo wniesienia skargi do organu nadzorczego (Prezes UODO).</p>

        <h2>7. Pliki cookies i technologie</h2>
        <p>Serwis wykorzystuje niezbędne pliki cookies (m.in. cookie sesji oraz token bezpieczeństwa CSRF), konieczne do prawidłowego działania rezerwacji i panelu.</p>
        <p>Serwis w domyślnej konfiguracji <strong>nie korzysta</strong> z zewnętrznych narzędzi analitycznych, map, reklam ani zewnętrznych czcionek/CDN. Jeżeli w przyszłości zostaną włączone usługi takie jak Google Analytics, Google Maps, reCAPTCHA, zewnętrzne czcionki lub CDN, niniejsza polityka wymaga aktualizacji o informacje o ich wpływie na prywatność i cookies. [UZUPEŁNIJ, JEŚLI DOTYCZY].</p>

        <h2>8. Bezpieczeństwo</h2>
        <p>Stosujemy techniczne i organizacyjne środki bezpieczeństwa (m.in. szyfrowanie połączenia HTTPS, ograniczenie dostępu do panelu, ochronę formularzy). Żadne zabezpieczenie nie gwarantuje jednak pełnego bezpieczeństwa — przed przekazaniem sprzętu zalecamy samodzielne wykonanie kopii ważnych danych.</p>

        <h2>9. Kontakt</h2>
        <p>W sprawach dotyczących danych osobowych skontaktuj się pod adresem: <?= e($email) ?>.</p>
    </div>
</section>
<?php require __DIR__ . '/partials/footer.php'; ?>
