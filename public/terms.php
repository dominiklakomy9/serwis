<?php
/**
 * terms.php — Regulamin świadczenia usług (SZABLON do uzupełnienia).
 *
 * UWAGA: Profesjonalny szablon, nie indywidualna porada prawna.
 */
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';

$pageTitle = 'Regulamin';
$pageDescription = 'Regulamin korzystania z serwisu i umawiania terminów dostarczenia sprzętu.';
$canonicalPath = '/terms.php';

$owner = (string) config('app.owner');
$service = (string) config('app.name');
$email = (string) config('contact.email');

require __DIR__ . '/partials/header.php';
?>
<section class="section legal-page">
    <div class="container narrow">
        <h1>Regulamin</h1>
        <p class="muted">Ostatnia aktualizacja: <?= e(date('Y-m-d')) ?></p>

        <div class="notice">
            <strong>Uwaga:</strong> Poniższy dokument stanowi szablon i wymaga dostosowania do rzeczywistej działalności. Nie jest to indywidualna porada prawna.
        </div>

        <h2>1. Postanowienia ogólne</h2>
        <p>Regulamin określa zasady korzystania z serwisu internetowego oraz umawiania terminów dostarczenia sprzętu do serwisu „<?= e($service) ?>” prowadzonego przez <?= e($owner) ?>.</p>

        <h2>2. Zakres usług</h2>
        <p>Serwis świadczy usługi z zakresu diagnostyki, konfiguracji, konserwacji oraz pomocy komputerowej. Szczegółowy zakres prac ustalany jest indywidualnie po diagnostyce.</p>

        <h2>3. Rezerwacja terminu</h2>
        <ul>
            <li>Rezerwacja dotyczy <strong>terminu dostarczenia</strong> sprzętu, a nie czasu trwania naprawy.</li>
            <li>Umówienie terminu nie jest równoznaczne z rozpoczęciem naprawy.</li>
            <li>Rozpoczęcie naprawy następuje po diagnostyce i uzgodnieniu zakresu prac.</li>
        </ul>

        <h2>4. Diagnostyka i wycena</h2>
        <p>Czas trwania naprawy oraz jej koszt nie są znane z góry. Zakres prac i orientacyjny koszt ustalane są po diagnostyce, przed przystąpieniem do naprawy. [UZUPEŁNIJ ZASADY WYCENY / EWENTUALNEJ OPŁATY ZA DIAGNOSTYKĘ].</p>

        <h2>5. Dane na urządzeniu</h2>
        <p>Przed przekazaniem sprzętu Klient powinien we własnym zakresie wykonać kopię ważnych danych. Serwis dokłada staranności, jednak nie może zagwarantować zachowania danych podczas prac naprawczych.</p>

        <h2>6. Zmiana i anulowanie terminu</h2>
        <p>Termin można zmienić lub anulować, kontaktując się z serwisem. Prosimy o możliwie wczesną informację, aby termin mógł zostać zwolniony.</p>

        <h2>7. Odbiór sprzętu</h2>
        <p>[UZUPEŁNIJ ZASADY ODBIORU, TERMINY, EWENTUALNE OPŁATY ZA PRZECHOWYWANIE NIEODEBRANEGO SPRZĘTU].</p>

        <h2>8. Reklamacje</h2>
        <p>[UZUPEŁNIJ PROCEDURĘ REKLAMACYJNĄ ORAZ EWENTUALNĄ GWARANCJĘ NA WYKONANE USŁUGI].</p>

        <h2>9. Kontakt</h2>
        <p>Kontakt w sprawach związanych z regulaminem: <?= e($email) ?>.</p>
    </div>
</section>
<?php require __DIR__ . '/partials/footer.php'; ?>
