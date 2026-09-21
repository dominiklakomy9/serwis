<?php
/**
 * admin/service.php — pełny panel serwisowy pojedynczego zlecenia.
 * Protokół przyjęcia, zmiana statusu z historią oraz kroki naprawy
 * (z możliwością udostępnienia wybranych kroków klientowi).
 */
declare(strict_types=1);
$__bootstrap = null;
foreach (['/app/bootstrap.php', '/../app/bootstrap.php', '/../../app/bootstrap.php'] as $__cand) {
    if (@is_file(__DIR__ . $__cand)) { $__bootstrap = __DIR__ . $__cand; break; }
}
require $__bootstrap;
admin_require_login();

$pdo = db();
$admin = admin_current();
$id = (int) ($_GET['id'] ?? 0);

function dtlocal(?string $v): string
{
    return $v ? str_replace(' ', 'T', substr($v, 0, 16)) : '';
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_require();
    $id = (int) ($_POST['appointment_id'] ?? $id);
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'save_protocol') {
        $serial = v_clean_string($_POST['device_serial'] ?? '', 120);
        $visual = v_clean_string($_POST['visual_condition'] ?? '', 2000);
        $acc    = v_clean_string($_POST['accessories'] ?? '', 1000);
        $notes  = v_clean_string($_POST['technician_notes'] ?? '', 3000);
        $recRaw = trim((string) ($_POST['received_at'] ?? ''));
        $relRaw = trim((string) ($_POST['released_at'] ?? ''));
        $dtRe = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/';
        $recv = preg_match($dtRe, $recRaw) ? str_replace('T', ' ', $recRaw) . ':00' : null;
        $rel  = preg_match($dtRe, $relRaw) ? str_replace('T', ' ', $relRaw) . ':00' : null;

        $stmt = $pdo->prepare(
            'UPDATE appointments SET device_serial=:s, visual_condition=:v, accessories=:a,
             technician_notes=:n, received_at=:rc, released_at=:rl, updated_at=UTC_TIMESTAMP() WHERE id=:id'
        );
        $stmt->execute([
            ':s' => $serial ?: null, ':v' => $visual ?: null, ':a' => $acc ?: null,
            ':n' => $notes ?: null, ':rc' => $recv, ':rl' => $rel, ':id' => $id,
        ]);
        app_log('service', "Protokół zapisany #{$id} (admin {$admin['id']})");
        $_SESSION['flash'] = 'Zapisano dane protokołu.';
    } elseif ($action === 'change_status') {
        $ns = (string) ($_POST['status'] ?? '');
        $note = v_clean_string($_POST['note'] ?? '', 255);
        if (change_appointment_status($id, $ns, $admin['id'], $note !== '' ? $note : null)) {
            notify_status_change($id, $ns);
            $_SESSION['flash'] = 'Status został zaktualizowany.';
        } else {
            $_SESSION['flash'] = 'Nie udało się zmienić statusu.';
        }
    } elseif ($action === 'add_step') {
        $text = v_clean_string($_POST['step_text'] ?? '', 1000);
        $pub = isset($_POST['is_public']) ? 1 : 0;
        if ($text !== '') {
            $s = $pdo->prepare('INSERT INTO repair_steps (appointment_id, step_text, is_public, created_by, created_at) VALUES (:a,:t,:p,:by,UTC_TIMESTAMP())');
            $s->execute([':a' => $id, ':t' => $text, ':p' => $pub, ':by' => $admin['id']]);
            $_SESSION['flash'] = 'Dodano krok naprawy.';
        }
    } elseif ($action === 'toggle_step') {
        $sid = (int) ($_POST['step_id'] ?? 0);
        $s = $pdo->prepare('UPDATE repair_steps SET is_public = 1 - is_public WHERE id=:i AND appointment_id=:a');
        $s->execute([':i' => $sid, ':a' => $id]);
        $_SESSION['flash'] = 'Zmieniono widoczność kroku dla klienta.';
    } elseif ($action === 'delete_step') {
        $sid = (int) ($_POST['step_id'] ?? 0);
        $s = $pdo->prepare('DELETE FROM repair_steps WHERE id=:i AND appointment_id=:a');
        $s->execute([':i' => $sid, ':a' => $id]);
        $_SESSION['flash'] = 'Usunięto krok naprawy.';
    }
    redirect(u('/admin/service.php') . '?id=' . $id);
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$stmt = $pdo->prepare('SELECT a.*, c.full_name, c.phone, c.email FROM appointments a JOIN customers c ON c.id = a.customer_id WHERE a.id = :id');
$stmt->execute([':id' => $id]);
$a = $stmt->fetch();

$adminTitle = 'Panel serwisowy';
$adminActive = 'appointments';
require __DIR__ . '/partials/header.php';

if (!$a) {
    echo '<p class="muted">Nie znaleziono zgłoszenia.</p>';
    require __DIR__ . '/partials/footer.php';
    exit;
}

$hist = $pdo->prepare('SELECT * FROM appointment_status_history WHERE appointment_id=:id ORDER BY created_at ASC');
$hist->execute([':id' => $id]);
$history = $hist->fetchAll();

$rs = $pdo->prepare('SELECT * FROM repair_steps WHERE appointment_id=:id ORDER BY created_at ASC');
$rs->execute([':id' => $id]);
$repairSteps = $rs->fetchAll();

$statuses = ['pending','confirmed','in_progress','waiting_for_customer','completed','cancelled','no_show'];
?>
<?php if ($flash): ?><div class="form-alert show ok" data-testid="service-flash"><?= e($flash) ?></div><?php endif; ?>

<div class="service-head">
    <div>
        <a href="<?= u('/admin/appointments.php') ?>" class="back-link">&#8592; Lista zgłoszeń</a>
        <h2>Zlecenie <span class="mono"><?= e($a['appointment_number']) ?></span>
            <span class="badge badge-<?= e($a['status']) ?>"><?= e(status_label($a['status'])) ?></span>
        </h2>
    </div>
    <div class="service-actions">
        <a href="<?= u('/admin/protocol.php') ?>?id=<?= (int) $a['id'] ?>&type=intake" target="_blank" rel="noopener" class="btn btn-ghost btn-sm" data-testid="print-intake">Protokół przyjęcia</a>
        <a href="<?= u('/admin/protocol.php') ?>?id=<?= (int) $a['id'] ?>&type=repair" target="_blank" rel="noopener" class="btn btn-ghost btn-sm" data-testid="print-repair">Protokół naprawy</a>
    </div>
</div>

<div class="admin-grid-2">
    <div>
        <!-- Dane klienta / urządzenia -->
        <section class="panel">
            <h2>Klient i urządzenie</h2>
            <dl class="detail-dl">
                <dt>Klient</dt><dd><?= e($a['full_name']) ?></dd>
                <dt>Telefon</dt><dd><a href="tel:<?= e($a['phone']) ?>"><?= e($a['phone']) ?></a></dd>
                <dt>E-mail</dt><dd><?= $a['email'] ? e($a['email']) : '<span class="muted">— brak —</span>' ?></dd>
                <dt>Termin dostarczenia</dt><dd><strong><?= e($a['slot_date']) ?>, <?= e(substr($a['slot_time'],0,5)) ?></strong></dd>
                <dt>Rodzaj sprzętu</dt><dd><?= e(device_type_label($a['device_type'])) ?></dd>
                <dt>Producent / model</dt><dd><?= ($a['device_manufacturer'] || $a['device_model']) ? e(trim(($a['device_manufacturer']??'').' '.($a['device_model']??''))) : '<span class="muted">— brak —</span>' ?></dd>
            </dl>
            <h3>Opis problemu (od klienta)</h3>
            <p class="problem-text"><?= nl2br(e($a['problem_description'])) ?></p>
        </section>

        <!-- Protokół przyjęcia -->
        <section class="panel">
            <h2>Protokół przyjęcia</h2>
            <form method="post" action="<?= u('/admin/service.php') ?>" data-testid="protocol-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save_protocol">
                <input type="hidden" name="appointment_id" value="<?= (int) $a['id'] ?>">
                <div class="form-grid-2">
                    <div class="form-row">
                        <label for="serial">Numer seryjny</label>
                        <input type="text" id="serial" name="device_serial" maxlength="120" value="<?= e($a['device_serial'] ?? '') ?>" data-testid="input-serial">
                    </div>
                    <div class="form-row">
                        <label for="acc">Przekazane akcesoria</label>
                        <input type="text" id="acc" name="accessories" maxlength="1000" value="<?= e($a['accessories'] ?? '') ?>" placeholder="np. zasilacz, kabel" data-testid="input-accessories">
                    </div>
                </div>
                <div class="form-row">
                    <label for="visual">Stan wizualny przy przyjęciu</label>
                    <textarea id="visual" name="visual_condition" rows="2" maxlength="2000" data-testid="input-visual"><?= e($a['visual_condition'] ?? '') ?></textarea>
                </div>
                <div class="form-grid-2">
                    <div class="form-row">
                        <label for="recv">Data przyjęcia</label>
                        <input type="datetime-local" id="recv" name="received_at" value="<?= e(dtlocal($a['received_at'] ?? null)) ?>" data-testid="input-received">
                    </div>
                    <div class="form-row">
                        <label for="rel">Data wydania</label>
                        <input type="datetime-local" id="rel" name="released_at" value="<?= e(dtlocal($a['released_at'] ?? null)) ?>" data-testid="input-released">
                    </div>
                </div>
                <div class="form-row">
                    <label for="notes">Uwagi serwisanta (widoczne tylko dla Ciebie)</label>
                    <textarea id="notes" name="technician_notes" rows="3" maxlength="3000" data-testid="input-notes"><?= e($a['technician_notes'] ?? '') ?></textarea>
                </div>
                <button type="submit" class="btn btn-primary" data-testid="save-protocol">Zapisz protokół</button>
            </form>
        </section>
    </div>

    <div>
        <!-- Status -->
        <section class="panel">
            <h2>Status zlecenia</h2>
            <form method="post" action="<?= u('/admin/service.php') ?>" data-testid="status-change-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="change_status">
                <input type="hidden" name="appointment_id" value="<?= (int) $a['id'] ?>">
                <div class="form-row">
                    <label for="statusSel">Nowy status</label>
                    <select id="statusSel" name="status" data-testid="status-select">
                        <?php foreach ($statuses as $s): ?>
                            <option value="<?= e($s) ?>" <?= $a['status'] === $s ? 'selected' : '' ?>><?= e(status_label($s)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-row">
                    <label for="noteInput">Notatka wewnętrzna (opcjonalnie)</label>
                    <input type="text" id="noteInput" name="note" maxlength="255" data-testid="status-note">
                </div>
                <button type="submit" class="btn btn-primary" data-testid="status-save">Zapisz status</button>
            </form>

            <h3 class="mt">Historia zmian statusu</h3>
            <ul class="timeline" data-testid="status-history">
                <?php foreach ($history as $h): ?>
                <li>
                    <span class="badge badge-<?= e($h['new_status']) ?>"><?= e(status_label($h['new_status'])) ?></span>
                    <span class="muted small"><?= e($h['created_at']) ?></span>
                    <?php if ($h['note']): ?><div class="tl-note"><?= e($h['note']) ?></div><?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
        </section>

        <!-- Kroki naprawy -->
        <section class="panel">
            <h2>Ścieżka naprawy</h2>
            <p class="muted small">Dodawaj kroki naprawy. Kroki oznaczone jako widoczne pojawią się u klienta na stronie statusu.</p>
            <form method="post" action="<?= u('/admin/service.php') ?>" data-testid="add-step-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add_step">
                <input type="hidden" name="appointment_id" value="<?= (int) $a['id'] ?>">
                <div class="form-row">
                    <label for="stepText">Opis kroku</label>
                    <input type="text" id="stepText" name="step_text" maxlength="1000" placeholder="np. Zdemontowałem wyświetlacz" required data-testid="input-step">
                </div>
                <label class="checkbox" style="margin-bottom:12px;">
                    <input type="checkbox" name="is_public" value="1" checked data-testid="step-public">
                    <span>Widoczne dla klienta</span>
                </label>
                <button type="submit" class="btn btn-primary" data-testid="add-step">Dodaj krok</button>
            </form>

            <ul class="timeline mt" data-testid="repair-steps">
                <?php if (!$repairSteps): ?>
                    <li class="muted">Brak kroków naprawy.</li>
                <?php endif; ?>
                <?php foreach ($repairSteps as $st): ?>
                <li>
                    <div class="step-line">
                        <span><?= e($st['step_text']) ?></span>
                        <span class="badge <?= $st['is_public'] ? 'badge-completed' : 'badge-no_show' ?>"><?= $st['is_public'] ? 'Widoczne' : 'Ukryte' ?></span>
                    </div>
                    <span class="muted small"><?= e($st['created_at']) ?></span>
                    <div class="step-actions">
                        <form method="post" action="<?= u('/admin/service.php') ?>" style="display:inline;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="toggle_step">
                            <input type="hidden" name="appointment_id" value="<?= (int) $a['id'] ?>">
                            <input type="hidden" name="step_id" value="<?= (int) $st['id'] ?>">
                            <button type="submit" class="btn btn-ghost btn-sm"><?= $st['is_public'] ? 'Ukryj' : 'Pokaż klientowi' ?></button>
                        </form>
                        <form method="post" action="<?= u('/admin/service.php') ?>" style="display:inline;" onsubmit="return confirm('Usunąć ten krok?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete_step">
                            <input type="hidden" name="appointment_id" value="<?= (int) $a['id'] ?>">
                            <input type="hidden" name="step_id" value="<?= (int) $st['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm">Usuń</button>
                        </form>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
        </section>
    </div>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>
