<?php
/**
 * index.php — strona główna serwisu.
 */
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';

$pageTitle = null; // sama nazwa serwisu
$pageDescription = 'Dominik Łakomy — Pogotowie Komputerowe. Diagnostyka, konfiguracja, konserwacja i pomoc komputerowa. Umów termin dostarczenia sprzętu.';
$canonicalPath = '/';
$activeNav = 'start';

$phone = (string) config('contact.phone');
$email = (string) config('contact.email');
$area = (string) config('contact.service_area');

$services = [
    ['icon' => 'stethoscope', 'name' => 'Diagnostyka komputerów i laptopów', 'desc' => 'Ustalenie przyczyny problemu i zakresu koniecznych prac.'],
    ['icon' => 'wind',        'name' => 'Czyszczenie i konserwacja',        'desc' => 'Czyszczenie wnętrza, wymiana past i profilaktyka termiczna.'],
    ['icon' => 'download',    'name' => 'Instalacja i konfiguracja systemu','desc' => 'Instalacja systemu, sterowników i podstawowego oprogramowania.'],
    ['icon' => 'bug',         'name' => 'Rozwiązywanie problemów programowych','desc' => 'Błędy systemu, wolne działanie, problemy z aplikacjami.'],
    ['icon' => 'sliders',     'name' => 'Konfiguracja komputerów',          'desc' => 'Dostosowanie ustawień, kont i środowiska pracy.'],
    ['icon' => 'printer',     'name' => 'Drukarki i urządzenia peryferyjne','desc' => 'Konfiguracja drukarek, skanerów i innych urządzeń.'],
    ['icon' => 'headset',     'name' => 'Pomoc techniczna',                 'desc' => 'Wsparcie przy bieżących problemach z komputerem.'],
    ['icon' => 'wrench',      'name' => 'Inne problemy komputerowe',        'desc' => 'Nietypowe usterki — sprawdzimy, co można zrobić.'],
];

$steps = [
    ['n' => '01', 't' => 'Umów termin',           'd' => 'Wybierz godzinę dostarczenia sprzętu.'],
    ['n' => '02', 't' => 'Dostarcz sprzęt',        'd' => 'Przynieś urządzenie w umówionym terminie.'],
    ['n' => '03', 't' => 'Opisz problem',          'd' => 'Opowiedz, co się dzieje z urządzeniem.'],
    ['n' => '04', 't' => 'Diagnostyka',            'd' => 'Sprawdzam, na czym polega usterka.'],
    ['n' => '05', 't' => 'Ustalenie zakresu prac', 'd' => 'Uzgadniamy, co i w jakim zakresie zrobić.'],
    ['n' => '06', 't' => 'Naprawa',                'd' => 'Wykonuję ustalone czynności.'],
    ['n' => '07', 't' => 'Odbiór sprzętu',         'd' => 'Odbierasz gotowe urządzenie.'],
];

$faqs = [
    ['q' => 'Czy rezerwacja oznacza rozpoczęcie naprawy?', 'a' => 'Nie. Rezerwacja dotyczy wyłącznie terminu dostarczenia sprzętu. Naprawa rozpoczyna się po diagnostyce i ustaleniu zakresu prac.'],
    ['q' => 'Czy muszę podawać hasło?', 'a' => 'Tylko wtedy, gdy jest to niezbędne do wykonania konkretnej czynności. Nie przekazuj haseł bez potrzeby.'],
    ['q' => 'Ile trwa naprawa?', 'a' => 'Nie da się tego określić z góry. Czas zależy od rodzaju usterki i dostępności części — ustalamy go po diagnostyce.'],
    ['q' => 'Co powinienem zabrać ze sprzętem?', 'a' => 'Tylko niezbędne akcesoria, np. zasilacz do laptopa. Nie ma potrzeby przynoszenia zbędnych elementów.'],
    ['q' => 'Czy mogę dostarczyć sam komputer bez akcesoriów?', 'a' => 'Tak, o ile akcesoria nie są potrzebne do diagnozy. W razie potrzeby poproszę o dostarczenie brakujących elementów.'],
    ['q' => 'Co jeśli nie wiem, jaka jest usterka?', 'a' => 'To normalne. Wystarczy opisać objawy — resztę ustalę podczas diagnostyki.'],
    ['q' => 'Czy mogę zmienić termin?', 'a' => 'Tak. Skontaktuj się telefonicznie lub e-mailowo, aby ustalić nowy termin dostarczenia.'],
    ['q' => 'Czy mogę anulować termin?', 'a' => 'Tak. Prosimy o wcześniejszą informację, aby termin mógł zostać zwolniony dla innych.'],
    ['q' => 'Jak wygląda diagnostyka?', 'a' => 'Sprawdzam sprzęt pod kątem zgłaszanych objawów, ustalam prawdopodobną przyczynę i możliwy zakres prac.'],
    ['q' => 'Czy każda naprawa wymaga wcześniejszej wyceny?', 'a' => 'Zakres i orientacyjny koszt prac ustalamy po diagnostyce, przed przystąpieniem do naprawy.'],
];

require __DIR__ . '/partials/header.php';
?>

<!-- HERO -->
<section class="hero" id="start">
    <div class="container hero-inner">
        <div class="hero-content">
            <p class="eyebrow">Pogotowie komputerowe</p>
            <h1>Komputer nie działa tak,<br>jak powinien? <span class="accent">Sprawdźmy, co się dzieje.</span></h1>
            <p class="lead">Diagnostyka, konfiguracja, konserwacja i pomoc komputerowa — spokojnie i rzeczowo.</p>
            <div class="hero-actions">
                <a href="/booking.php" class="btn btn-primary btn-lg" data-testid="hero-book-btn">Umów dostarczenie sprzętu</a>
                <a href="#uslugi" class="btn btn-ghost btn-lg">Zobacz usługi</a>
            </div>
            <p class="hero-hint"><span class="dot" aria-hidden="true"></span> Przyjęcie sprzętu po wcześniejszym umówieniu terminu</p>
        </div>
        <div class="hero-panel" aria-hidden="true">
            <div class="panel-row"><span class="panel-dot dot-red"></span><span class="panel-dot dot-yellow"></span><span class="panel-dot dot-green"></span></div>
            <div class="panel-line"><span class="prompt">&gt;</span> diagnostyka --start</div>
            <div class="panel-line muted">skanowanie systemu…</div>
            <div class="panel-line muted">sprawdzanie dysku…</div>
            <div class="panel-line muted">analiza temperatur…</div>
            <div class="panel-line ok">✓ raport gotowy — ustalamy zakres prac</div>
        </div>
    </div>
</section>

<!-- USŁUGI -->
<section class="section" id="uslugi">
    <div class="container">
        <header class="section-head">
            <h2>Usługi</h2>
            <p class="section-sub">Zakres pomocy dla komputerów, laptopów i urządzeń peryferyjnych.</p>
        </header>
        <div class="cards-grid">
            <?php foreach ($services as $s): ?>
            <article class="card service-card">
                <span class="card-icon" data-icon="<?= e($s['icon']) ?>" aria-hidden="true"></span>
                <h3><?= e($s['name']) ?></h3>
                <p><?= e($s['desc']) ?></p>
                <p class="price-note">Wycena zależna od zakresu prac.</p>
            </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- JAK TO DZIAŁA -->
<section class="section section-alt" id="jak-to-dziala">
    <div class="container">
        <header class="section-head">
            <h2>Jak to działa</h2>
            <p class="section-sub">Prosty, przewidywalny proces — od umówienia terminu po odbiór sprzętu.</p>
        </header>
        <ol class="steps">
            <?php foreach ($steps as $st): ?>
            <li class="step">
                <span class="step-num"><?= e($st['n']) ?></span>
                <div>
                    <h3><?= e($st['t']) ?></h3>
                    <p><?= e($st['d']) ?></p>
                </div>
            </li>
            <?php endforeach; ?>
        </ol>
        <p class="callout">
            <strong>Ważne:</strong> Rezerwacja dotyczy terminu dostarczenia sprzętu, a nie czasu trwania naprawy.
        </p>
    </div>
</section>

<!-- BEZPIECZEŃSTWO DANYCH -->
<section class="section" id="bezpieczenstwo">
    <div class="container">
        <header class="section-head">
            <h2>Bezpieczeństwo danych</h2>
            <p class="section-sub">Kilka zasad, które warto zastosować przed przekazaniem sprzętu.</p>
        </header>
        <div class="cards-grid two-col">
            <div class="card">
                <h3>Zrób kopię ważnych danych</h3>
                <p>Przed przekazaniem sprzętu warto samodzielnie wykonać kopię istotnych plików. Prace serwisowe mogą wiązać się z ryzykiem dla danych.</p>
            </div>
            <div class="card">
                <h3>Nie przekazuj haseł bez potrzeby</h3>
                <p>Hasła podawaj wyłącznie wtedy, gdy są niezbędne do wykonania konkretnej czynności.</p>
            </div>
            <div class="card">
                <h3>Ograniczony dostęp do kont</h3>
                <p>Dostęp do kont przekazuj tylko w zakresie potrzebnym do wykonania usługi.</p>
            </div>
            <div class="card">
                <h3>Poinformuj o ważnych danych</h3>
                <p>Jeśli na urządzeniu znajdują się szczególnie ważne dane, warto o tym wcześniej powiedzieć.</p>
            </div>
            <div class="card">
                <h3>Tylko niezbędne akcesoria</h3>
                <p>Przekaż wyłącznie akcesoria potrzebne do diagnozy lub naprawy (np. zasilacz).</p>
            </div>
        </div>
    </div>
</section>

<!-- FAQ -->
<section class="section section-alt" id="faq">
    <div class="container narrow">
        <header class="section-head">
            <h2>Najczęstsze pytania</h2>
        </header>
        <div class="faq" data-testid="faq-list">
            <?php foreach ($faqs as $i => $f): ?>
            <details class="faq-item">
                <summary><?= e($f['q']) ?></summary>
                <div class="faq-body"><p><?= e($f['a']) ?></p></div>
            </details>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- KONTAKT / CTA -->
<section class="section contact-cta">
    <div class="container narrow center">
        <h2>Masz problem ze sprzętem?</h2>
        <p class="section-sub">Umów termin dostarczenia — resztę ustalimy na miejscu.</p>
        <div class="contact-actions">
            <a href="tel:<?= e(preg_replace('/\s+/', '', $phone)) ?>" class="btn btn-ghost">Zadzwoń</a>
            <a href="mailto:<?= e($email) ?>" class="btn btn-ghost">Napisz wiadomość</a>
            <a href="/booking.php" class="btn btn-primary" data-testid="cta-book-btn">Umów termin</a>
        </div>
        <p class="contact-meta">
            <?= e($phone) ?> &middot; <?= e($email) ?> &middot; <?= e($area) ?>
        </p>
    </div>
</section>

<?php require __DIR__ . '/partials/footer.php'; ?>
