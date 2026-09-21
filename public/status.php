<?php
/**
 * status.php — publiczny podgląd zlecenia dla klienta.
 *
 * Dostęp na dwa sposoby (BEZ przepisywania tokenu):
 *   1) Magiczny link z e-maila:  /status.php?nr=NUMER&token=TOKEN
 *   2) Formularz:  numer zlecenia + adres e-mail
 *
 * Klient widzi: aktualny status, historię zmian statusów, publiczne kroki
 * naprawy oraz może samodzielnie anulować termin (gdy jeszcze nie rozpoczęto).
 * Nie pokazujemy uwag wewnętrznych serwisanta.
 */
declare(strict_types=1);
$__bootstrap = null;
foreach (['/app/bootstrap.php', '/../app/bootstrap.php', '/../../app/bootstrap.php'] as $__cand) {
    if (@is_file(__DIR__ . $__cand)) { $__bootstrap = __DIR__ . $__cand; break; }
}
require $__bootstrap;

$pageTitle = 'Status zlecenia';
$pageDescription = 'Sprawdź status swojego zlecenia — numer zlecenia i adres e-mail lub link z wiadomości.';
$canonicalPath = '/status.php';
$activeNav = 'status';

$pdo = db();
$order = null;
$error = null;
$notice = null;
$authToken = '';
$authEmail = '';

function load_order(PDO $pdo, string $number)
{
    $s = $pdo->prepare('SELECT a.*, c.full_name, c.email FROM appointments a JOIN customers c ON c.id = a.customer_id WHERE a.appointment_number = :n LIMIT 1');
    $s->execute([':n' => $number]);
    return $s->fetch();
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'POST') {
    csrf_require();
    if (rate_limit_exceeded('status_client', 30, 3600)) {
        $error = 'Zbyt wiele prób. Spróbuj ponownie później.';
    } else {
        $action = (string) ($_POST['action'] ?? 'lookup');
        $number = v_clean_string($_POST['number'] ?? '', 20);
        $token  = v_clean_string($_POST['token'] ?? '', 64);
        $email  = mb_strtolower(v_clean_string($_POST['email'] ?? '', 190));
        $row = $number !== '' ? load_order($pdo, $number) : false;

        $ok = false;
        if ($row) {
            if ($token !== '' && preg_match('/^[a-f0-9]{64}$/i', $token) && hash_equals((string) $row['status_token'], $token)) {
                $ok = true; $authToken = $token;
            } elseif ($email !== '' && $row['email'] !== null && hash_equals(mb_strtolower((string) $row['email']), $email)) {
                $ok = true; $authEmail = $email;
            }
        }

        if ($ok) {
            if ($action === 'cancel') {
                if (in_array($row['status'], ['pending', 'confirmed'], true)) {
                    change_appointment_status((int) $row['id'], 'cancelled', null, 'Anulowane przez klienta');
                    notify_status_change((int) $row['id'], 'cancelled');
                    $row = load_order($pdo, $number);
                    $notice = 'Termin został anulowany.';
                } else {
                    $notice = 'Tego zlecenia nie można już anulować samodzielnie — skontaktuj się z serwisem.';
                }
            }
            $order = $row;
        } else {
            $error = 'Nie znaleziono zlecenia o podanych danych. Sprawdź numer zlecenia oraz adres e-mail.';
        }
    }
} elseif (isset($_GET['nr'])) {
    $number = v_clean_string($_GET['nr'], 20);
    $token  = v_clean_string($_GET['token'] ?? '', 64);
    $row = $number !== '' ? load_order($pdo, $number) : false;
    if ($row && preg_match('/^[a-f0-9]{64}$/i', $token) && hash_equals((string) $row['status_token'], $token)) {
        $order = $row; $authToken = $token;
    } elseif ($number !== '') {
        $error = 'Link jest nieprawidłowy lub wygasł. Sprawdź status, podając numer zlecenia i adres e-mail.';
    }
}

$history = [];
$publicSteps = [];
if ($order) {
    $h = $pdo->prepare('SELECT new_status, created_at FROM appointment_status_history WHERE appointment_id = :id ORDER BY created_at ASC');
    $h->execute([':id' => $order['id']]);
    $history = $h->fetchAll();
    $st = $pdo->prepare('SELECT step_text, created_at FROM repair_steps WHERE appointment_id = :id AND is_public = 1 ORDER BY created_at ASC');
    $st->execute([':id' => $order['id']]);
    $publicSteps = $st->fetchAll();
}

require __DIR__ . '/partials/header.php';
?>
<section class="section status-page">
    <div class="container narrow">
        <?php if ($notice): ?><div class="form-alert show ok" data-testid="status-notice"><?= e($notice) ?></div><?php endif; ?>

        <?php if (!$order): ?>
            <header class="section-head">
                <h1>Status zlecenia</h1>
                <p class="section-sub">Podaj numer zlecenia oraz adres e-mail użyty przy rezerwacji. Najszybciej wejdziesz też przez link z wiadomości e-mail.</p>
            </header>
            <form class="card status-form" method="post" action="<?= u('/status.php') ?>" data-testid="status-form" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="lookup">
                <div class="form-row">
                    <label for="sNumber">Numer zlecenia <span class="req">*</span></label>
                    <input type="text" id="sNumber" name="number" maxlength="20" required placeholder="np. PK-2026-0001" value="<?= e($_POST['number'] ?? ($_GET['nr'] ?? '')) ?>" data-testid="status-number">
                </div>
                <div class="form-row">
                    <label for="sEmail">Adres e-mail <span class="req">*</span></label>
                    <input type="email" id="sEmail" name="email" maxlength="190" required placeholder="e-mail podany przy rezerwacji" data-testid="status-email">
                </div>
                <?php if ($error): ?><div class="form-alert show error" data-testid="status-error"><?= e($error) ?></div><?php endif; ?>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary btn-lg" data-testid="status-submit">Sprawdź status</button>
                </div>
                <p class="form-hint">Podanie e-maila nie jest wymagane, jeśli korzystasz z linku otrzymanego w wiadomości.</p>
            </form>
        <?php else: ?>
            <div class="card order-panel" data-testid="status-result">
                <div class="order-top">
                    <div>
                        <p class="muted small">Zlecenie</p>
                        <h1 class="mono order-number" data-testid="order-number"><?= e($order['appointment_number']) ?></h1>
                    </div>
                    <span class="badge badge-<?= e($order['status']) ?> order-badge" data-testid="order-status"><?= e(public_status_label($order['status'])) ?></span>
                </div>

                <dl class="order-facts">
                    <div><dt>Termin dostarczenia</dt><dd><?= e($order['slot_date']) ?>, <?= e(substr($order['slot_time'],0,5)) ?></dd></div>
                    <div><dt>Urządzenie</dt><dd><?= e(device_type_label($order['device_type'])) ?></dd></div>
                    <?php if (!empty($order['received_at'])): ?><div><dt>Przyjęto</dt><dd><?= e($order['received_at']) ?></dd></div><?php endif; ?>
                    <?php if (!empty($order['released_at'])): ?><div><dt>Wydano</dt><dd><?= e($order['released_at']) ?></dd></div><?php endif; ?>
                </dl>

                <?php if ($publicSteps): ?>
                <h2 class="order-h2">Postęp naprawy</h2>
                <ul class="order-timeline" data-testid="repair-steps-public">
                    <?php foreach ($publicSteps as $st): ?>
                    <li>
                        <span class="ot-dot" aria-hidden="true"></span>
                        <div>
                            <p class="ot-text"><?= e($st['step_text']) ?></p>
                            <span class="muted small"><?= e($st['created_at']) ?></span>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>

                <h2 class="order-h2">Historia statusu</h2>
                <ul class="order-timeline subtle" data-testid="status-history-public">
                    <?php foreach ($history as $h): ?>
                    <li>
                        <span class="ot-dot" aria-hidden="true"></span>
                        <div>
                            <p class="ot-text"><?= e(public_status_label($h['new_status'])) ?></p>
                            <span class="muted small"><?= e($h['created_at']) ?></span>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>

                <?php if (in_array($order['status'], ['pending','confirmed'], true)): ?>
                <div class="order-cancel">
                    <p class="muted small">Nie możesz dostarczyć sprzętu w tym terminie?</p>
                    <div class="order-cancel-actions">
                        <form method="post" action="<?= u('/status.php') ?>" onsubmit="return confirm('Czy na pewno anulować ten termin?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="cancel">
                            <input type="hidden" name="number" value="<?= e($order['appointment_number']) ?>">
                            <input type="hidden" name="token" value="<?= e($authToken) ?>">
                            <input type="hidden" name="email" value="<?= e($authEmail) ?>">
                            <button type="submit" class="btn btn-danger" data-testid="cancel-appointment">Anuluj termin</button>
                        </form>
                        <a href="<?= u('/booking.php') ?>" class="btn btn-ghost">Umów nowy termin</a>
                    </div>
                </div>
                <?php endif; ?>

                <p class="order-back"><a href="<?= u('/status.php') ?>">&#8592; Sprawdź inne zlecenie</a></p>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php require __DIR__ . '/partials/footer.php'; ?>
