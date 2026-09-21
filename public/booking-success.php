<?php
/**
 * booking-success.php — potwierdzenie po wysłaniu zgłoszenia.
 * Dane potwierdzenia pochodzą z sesji (ustawione w api/create_booking.php).
 */
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';

$booking = $_SESSION['last_booking'] ?? null;

$pageTitle = 'Zgłoszenie wysłane';
$pageDescription = 'Potwierdzenie umówienia terminu dostarczenia sprzętu.';
$canonicalPath = '/booking-success.php';
$activeNav = '';

require __DIR__ . '/partials/header.php';
?>
<section class="section success-page">
    <div class="container narrow">
        <?php if (!$booking): ?>
            <div class="card center">
                <h1>Brak danych zgłoszenia</h1>
                <p>Nie znaleziono informacji o zgłoszeniu. Mogło już zostać wyświetlone lub sesja wygasła.</p>
                <a href="<?= u('/booking.php') ?>" class="btn btn-primary">Umów dostarczenie sprzętu</a>
            </div>
        <?php else: ?>
            <div class="card success-card" data-testid="booking-success">
                <span class="success-check" aria-hidden="true">✓</span>
                <h1>Zgłoszenie zostało wysłane</h1>
                <p class="section-sub">Dziękujemy. Poniżej znajdują się dane Twojego zgłoszenia — zapisz je.</p>

                <dl class="success-details">
                    <div>
                        <dt>Numer zgłoszenia</dt>
                        <dd class="mono big" data-testid="success-number"><?= e($booking['number']) ?></dd>
                    </div>
                    <div>
                        <dt>Termin dostarczenia</dt>
                        <dd><?= e($booking['date']) ?>, godz. <?= e($booking['time']) ?></dd>
                    </div>
                    <div>
                        <dt>Status</dt>
                        <dd><span class="badge badge-pending"><?= e(status_label($booking['status'])) ?></span></dd>
                    </div>
                </dl>

                <div class="token-box">
                    <p class="token-label">Token do sprawdzania statusu — <strong>zachowaj go w bezpiecznym miejscu</strong>:</p>
                    <code class="token-value mono" data-testid="success-token"><?= e($booking['token']) ?></code>
                    <p class="muted small">Za pomocą numeru zgłoszenia i tokenu sprawdzisz status swojego zlecenia. Token jest poufny — nie udostępniaj go osobom trzecim.</p>
                </div>

                <div class="success-actions">
                    <a href="<?= u('/status.php') ?>?number=<?= e(urlencode($booking['number'])) ?>" class="btn btn-primary" data-testid="go-status">Sprawdź status zlecenia</a>
                    <a href="<?= u('/index.php') ?>" class="btn btn-ghost">Wróć na stronę główną</a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php
// Wyczyść dane potwierdzenia po wyświetleniu (jednorazowo).
unset($_SESSION['last_booking']);
require __DIR__ . '/partials/footer.php';
?>
