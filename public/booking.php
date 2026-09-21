<?php
/**
 * booking.php — strona rezerwacji terminu dostarczenia sprzętu.
 * Kalendarz + wybór godziny + formularz. Logika w /assets/js/booking.js.
 */
declare(strict_types=1);
// Odszukanie warstwy aplikacji niezależnie od układu katalogów
// (public/ obok app/  LUB  wszystko w jednym katalogu, np. public_html na hostingu współdzielonym).
$__bootstrap = null;
foreach (['/app/bootstrap.php', '/../app/bootstrap.php', '/../../app/bootstrap.php'] as $__cand) {
    if (@is_file(__DIR__ . $__cand)) { $__bootstrap = __DIR__ . $__cand; break; }
}
require $__bootstrap;

$pageTitle = 'Umów dostarczenie sprzętu';
$pageDescription = 'Wybierz termin dostarczenia sprzętu do serwisu. Rezerwujesz wyłącznie godzinę dostarczenia — nie czas naprawy.';
$canonicalPath = '/booking.php';
$activeNav = 'booking';
$maxProblem = (int) config('booking.max_problem_length', 1500);
$captchaEnabled = (bool) config('security.captcha.enabled');

require __DIR__ . '/partials/header.php';
?>
<section class="section booking-page">
    <div class="container">
        <header class="section-head">
            <h1>Umów dostarczenie sprzętu</h1>
            <p class="section-sub">Wybierz dzień i godzinę, w której możesz dostarczyć sprzęt do serwisu.</p>
            <p class="callout small"><strong>Pamiętaj:</strong> rezerwujesz termin <em>dostarczenia</em> sprzętu, a nie czas trwania naprawy.</p>
        </header>

        <div class="booking-layout">
            <!-- KROK 1: KALENDARZ -->
            <div class="booking-col">
                <div class="card">
                    <div class="calendar" id="calendar" data-testid="booking-calendar">
                        <div class="calendar-head">
                            <button type="button" class="cal-nav" id="calPrev" aria-label="Poprzedni miesiąc">&#8249;</button>
                            <h2 class="cal-title" id="calTitle" aria-live="polite">—</h2>
                            <button type="button" class="cal-nav" id="calNext" aria-label="Następny miesiąc">&#8250;</button>
                        </div>
                        <div class="calendar-weekdays">
                            <span>Pn</span><span>Wt</span><span>Śr</span><span>Cz</span><span>Pt</span><span>So</span><span>Nd</span>
                        </div>
                        <div class="calendar-grid" id="calGrid" role="grid" aria-label="Kalendarz dostępności"></div>
                        <p class="calendar-legend">
                            <span class="lg lg-free"></span> Dostępny
                            <span class="lg lg-none"></span> Brak wolnych terminów
                        </p>
                    </div>
                </div>
            </div>

            <!-- KROK 2: GODZINY + FORMULARZ -->
            <div class="booking-col">
                <div class="card">
                    <h2 class="step-title">Wybierz godzinę</h2>
                    <p class="selected-date" id="selectedDate" aria-live="polite">Najpierw wybierz dzień w kalendarzu.</p>
                    <div class="slots" id="slots" data-testid="slots-container" aria-live="polite"></div>
                </div>

                <form class="card booking-form" id="bookingForm" data-testid="booking-form" novalidate>
                    <h2 class="step-title">Twoje dane</h2>
                    <?= csrf_field() ?>
                    <input type="hidden" name="date" id="fDate" value="">
                    <input type="hidden" name="time" id="fTime" value="">
                    <!-- Honeypot (ukryte pole dla botów) -->
                    <div class="hp" aria-hidden="true">
                        <label>Nie wypełniaj tego pola<input type="text" name="website" tabindex="-1" autocomplete="off"></label>
                    </div>

                    <div class="form-row">
                        <label for="fName">Imię i nazwisko <span class="req">*</span></label>
                        <input type="text" id="fName" name="full_name" maxlength="120" required autocomplete="name" data-testid="input-name">
                        <small class="field-error" data-for="full_name"></small>
                    </div>

                    <div class="form-grid-2">
                        <div class="form-row">
                            <label for="fPhone">Telefon <span class="req">*</span></label>
                            <input type="tel" id="fPhone" name="phone" maxlength="30" required autocomplete="tel" inputmode="tel" data-testid="input-phone">
                            <small class="field-error" data-for="phone"></small>
                        </div>
                        <div class="form-row">
                            <label for="fEmail">E-mail <span class="opt">(opcjonalnie)</span></label>
                            <input type="email" id="fEmail" name="email" maxlength="190" autocomplete="email" data-testid="input-email">
                            <small class="field-error" data-for="email"></small>
                        </div>
                    </div>

                    <div class="form-grid-2">
                        <div class="form-row">
                            <label for="fType">Rodzaj sprzętu <span class="req">*</span></label>
                            <select id="fType" name="device_type" required data-testid="select-device-type">
                                <option value="">— wybierz —</option>
                                <option value="laptop">Laptop</option>
                                <option value="desktop">Komputer stacjonarny</option>
                                <option value="printer">Drukarka</option>
                                <option value="monitor">Monitor</option>
                                <option value="other">Inne</option>
                            </select>
                            <small class="field-error" data-for="device_type"></small>
                        </div>
                        <div class="form-row">
                            <label for="fManufacturer">Producent / model <span class="opt">(opcjonalnie)</span></label>
                            <input type="text" id="fManufacturer" name="device_manufacturer" maxlength="100" placeholder="np. Dell, Lenovo…" data-testid="input-manufacturer">
                        </div>
                    </div>

                    <div class="form-row">
                        <label for="fProblem">Opis problemu <span class="req">*</span></label>
                        <textarea id="fProblem" name="problem_description" rows="4" maxlength="<?= e((string) $maxProblem) ?>" required data-testid="input-problem"></textarea>
                        <small class="char-count"><span id="charCount">0</span>/<?= e((string) $maxProblem) ?></small>
                        <small class="field-error" data-for="problem_description"></small>
                    </div>

                    <div class="form-row check-row">
                        <label class="checkbox">
                            <input type="checkbox" id="fPrivacy" name="privacy_accepted" value="1" required data-testid="input-privacy">
                            <span>Zapoznałem/am się z <a href="<?= u('/privacy.php') ?>" target="_blank" rel="noopener">Polityką prywatności</a>. <span class="req">*</span></span>
                        </label>
                        <small class="field-error" data-for="privacy_accepted"></small>
                    </div>

                    <?php if ($captchaEnabled): ?>
                    <div class="form-row">
                        <!-- Miejsce na widget CAPTCHA (konfigurowalne) -->
                        <div id="captchaBox" data-sitekey="<?= e((string) config('security.captcha.site_key')) ?>"></div>
                        <input type="hidden" name="captcha_token" id="fCaptcha">
                    </div>
                    <?php endif; ?>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary btn-lg" id="submitBtn" data-testid="submit-booking" disabled>
                            Wyślij zgłoszenie
                        </button>
                        <p class="form-hint" id="formHint">Wybierz termin, aby aktywować formularz.</p>
                    </div>
                    <div class="form-alert" id="formAlert" role="alert" aria-live="assertive"></div>
                </form>
            </div>
        </div>
    </div>
</section>

<?php
$pageScripts = ['/assets/js/booking.js'];
require __DIR__ . '/partials/footer.php';
?>
