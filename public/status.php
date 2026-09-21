<?php
/**
 * status.php — publiczne sprawdzenie statusu zlecenia (numer + token).
 * Zapytanie realizowane przez /api/status.php (fetch). Brak danych osobowych.
 */
declare(strict_types=1);
// Odszukanie warstwy aplikacji niezależnie od układu katalogów
// (public/ obok app/  LUB  wszystko w jednym katalogu, np. public_html na hostingu współdzielonym).
$__bootstrap = null;
foreach (['/app/bootstrap.php', '/../app/bootstrap.php', '/../../app/bootstrap.php'] as $__cand) {
    if (@is_file(__DIR__ . $__cand)) { $__bootstrap = __DIR__ . $__cand; break; }
}
require $__bootstrap;

$pageTitle = 'Status zlecenia';
$pageDescription = 'Sprawdź status swojego zlecenia, podając numer zgłoszenia oraz token.';
$canonicalPath = '/status.php';
$activeNav = 'status';

$prefillNumber = v_clean_string($_GET['number'] ?? '', 20);

require __DIR__ . '/partials/header.php';
?>
<section class="section status-page">
    <div class="container narrow">
        <header class="section-head">
            <h1>Status zlecenia</h1>
            <p class="section-sub">Podaj numer zgłoszenia oraz token, który otrzymałeś przy rezerwacji.</p>
        </header>

        <form class="card status-form" id="statusForm" data-testid="status-form" novalidate>
            <?= csrf_field() ?>
            <div class="form-row">
                <label for="sNumber">Numer zgłoszenia <span class="req">*</span></label>
                <input type="text" id="sNumber" name="number" maxlength="20" required
                       placeholder="np. PK-2026-0001" value="<?= e($prefillNumber) ?>" data-testid="status-number">
            </div>
            <div class="form-row">
                <label for="sToken">Token <span class="req">*</span></label>
                <input type="text" id="sToken" name="token" maxlength="64" required
                       placeholder="64-znakowy token z potwierdzenia" autocomplete="off" data-testid="status-token">
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary btn-lg" data-testid="status-submit">Sprawdź status</button>
            </div>
            <div class="form-alert" id="statusAlert" role="alert" aria-live="assertive"></div>
        </form>

        <div class="card status-result" id="statusResult" data-testid="status-result" hidden>
            <h2>Zlecenie: <span class="mono" id="rNumber"></span></h2>
            <p class="status-line">Status: <span class="badge" id="rStatus"></span></p>
            <p class="muted small">Ostatnia aktualizacja: <span id="rUpdated"></span></p>
        </div>
    </div>
</section>
<?php
$pageScripts = ['/assets/js/status.js'];
require __DIR__ . '/partials/footer.php';
?>
