<?php
/**
 * admin/appointments.php — lista zgłoszeń oraz widok szczegółów (?id=).
 * Obsługuje zmianę statusu (POST z CSRF) i zapis historii.
 */
declare(strict_types=1);
// Odszukanie warstwy aplikacji niezależnie od układu katalogów.
$__bootstrap = null;
foreach (['/app/bootstrap.php', '/../app/bootstrap.php', '/../../app/bootstrap.php'] as $__cand) {
    if (@is_file(__DIR__ . $__cand)) { $__bootstrap = __DIR__ . $__cand; break; }
}
require $__bootstrap;
admin_require_login();

$pdo = db();
$admin = admin_current();
$flash = null;

// --- Obsługa zmiany statusu ---
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_require();
    $apptId = (int) ($_POST['appointment_id'] ?? 0);
    $newStatus = (string) ($_POST['status'] ?? '');
    $note = v_clean_string($_POST['note'] ?? '', 255);

    if ($apptId > 0 && change_appointment_status($apptId, $newStatus, $admin['id'], $note !== '' ? $note : null)) {
        // Powiadomienia e-mail przy potwierdzeniu / anulowaniu.
        try {
            $q = $pdo->prepare('SELECT a.appointment_number, a.slot_date, a.slot_time, c.email, c.full_name
                                FROM appointments a JOIN customers c ON c.id=a.customer_id WHERE a.id=:id');
            $q->execute([':id' => $apptId]);
            $row = $q->fetch();
            if ($row && $row['email']) {
                $payload = ['number' => $row['appointment_number'], 'date' => $row['slot_date'], 'time' => substr($row['slot_time'],0,5)];
                if ($newStatus === 'confirmed') {
                    $t = mail_booking_confirmed($payload);
                    Mailer::send($row['email'], $row['full_name'], $t['subject'], $t['html']);
                } elseif ($newStatus === 'cancelled') {
                    $t = mail_booking_cancelled($payload);
                    Mailer::send($row['email'], $row['full_name'], $t['subject'], $t['html']);
                } elseif ($newStatus === 'completed') {
                    $t = mail_booking_completed($payload);
                    Mailer::send($row['email'], $row['full_name'], $t['subject'], $t['html']);
                }
            }
        } catch (Throwable $e) {
            app_log('mail', 'Powiadomienie o zmianie statusu: ' . $e->getMessage());
        }
        $flash = 'Status został zaktualizowany.';
    } else {
        $flash = 'Nie udało się zmienić statusu.';
    }
    // PRG — przekierowanie po POST.
    $_SESSION['flash'] = $flash;
    redirect(u('/admin/appointments.php') . '?id=' . $apptId);
}

if (!empty($_SESSION['flash'])) {
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
}

$detailId = (int) ($_GET['id'] ?? 0);

$adminTitle = 'Zgłoszenia';
$adminActive = 'appointments';
require __DIR__ . '/partials/header.php';

$statuses = ['pending','confirmed','in_progress','waiting_for_customer','completed','cancelled','no_show'];

if ($detailId > 0):
    // --- WIDOK SZCZEGÓŁÓW ---
    $stmt = $pdo->prepare(
        'SELECT a.*, c.full_name, c.phone, c.email
         FROM appointments a JOIN customers c ON c.id = a.customer_id
         WHERE a.id = :id LIMIT 1'
    );
    $stmt->execute([':id' => $detailId]);
    $a = $stmt->fetch();

    if (!$a):
        echo '<p class="muted">Nie znaleziono zgłoszenia.</p>';
    else:
        $hist = $pdo->prepare('SELECT * FROM appointment_status_history WHERE appointment_id = :id ORDER BY created_at ASC');
        $hist->execute([':id' => $detailId]);
        $history = $hist->fetchAll();
?>
    <?php if ($flash): ?><div class="form-alert show ok" data-testid="admin-flash"><?= e($flash) ?></div><?php endif; ?>
    <a href="<?= u('/admin/appointments.php') ?>" class="back-link">&#8592; Wróć do listy</a>

    <div class="admin-grid-2">
        <section class="panel">
            <h2>Zgłoszenie <span class="mono"><?= e($a['appointment_number']) ?></span></h2>
            <dl class="detail-dl">
                <dt>Data utworzenia</dt><dd><?= e($a['created_at']) ?></dd>
                <dt>Termin dostarczenia</dt><dd><strong><?= e($a['slot_date']) ?>, <?= e(substr($a['slot_time'],0,5)) ?></strong></dd>
                <dt>Status</dt><dd><span class="badge badge-<?= e($a['status']) ?>"><?= e(status_label($a['status'])) ?></span></dd>
                <dt>Klient</dt><dd><?= e($a['full_name']) ?></dd>
                <dt>Telefon</dt><dd><a href="tel:<?= e($a['phone']) ?>"><?= e($a['phone']) ?></a></dd>
                <dt>E-mail</dt><dd><?= $a['email'] ? e($a['email']) : '<span class="muted">— brak —</span>' ?></dd>
                <dt>Rodzaj sprzętu</dt><dd><?= e(device_type_label($a['device_type'])) ?></dd>
                <dt>Producent / model</dt><dd><?= $a['device_manufacturer'] || $a['device_model'] ? e(trim(($a['device_manufacturer']??'').' '.($a['device_model']??''))) : '<span class="muted">— brak —</span>' ?></dd>
            </dl>
            <h3>Opis problemu</h3>
            <p class="problem-text"><?= nl2br(e($a['problem_description'])) ?></p>
        </section>

        <section class="panel">
            <h2>Zmień status</h2>
            <form method="post" action="<?= u('/admin/appointments.php') ?>" data-testid="status-change-form">
                <?= csrf_field() ?>
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
                    <label for="noteInput">Notatka (opcjonalnie)</label>
                    <input type="text" id="noteInput" name="note" maxlength="255" data-testid="status-note">
                </div>
                <button type="submit" class="btn btn-primary" data-testid="status-save">Zapisz zmianę</button>
            </form>

            <h3 class="mt">Historia zmian</h3>
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
    </div>
<?php
    endif;
else:
    // --- WIDOK LISTY ---
    $filter = $_GET['status'] ?? '';
    $where = '';
    $params = [];
    if (in_array($filter, $statuses, true)) {
        $where = 'WHERE a.status = :st';
        $params[':st'] = $filter;
    }
    $sql = "SELECT a.id, a.appointment_number, a.slot_date, a.slot_time, a.status, a.device_type, c.full_name, c.phone
            FROM appointments a JOIN customers c ON c.id = a.customer_id
            {$where}
            ORDER BY a.slot_date DESC, a.slot_time DESC LIMIT 200";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
?>
    <?php if ($flash): ?><div class="form-alert show ok"><?= e($flash) ?></div><?php endif; ?>

    <div class="filter-bar">
        <a href="<?= u('/admin/appointments.php') ?>" class="chip <?= $filter === '' ? 'is-active' : '' ?>">Wszystkie</a>
        <?php foreach ($statuses as $s): ?>
            <a href="<?= u('/admin/appointments.php') ?>?status=<?= e($s) ?>" class="chip <?= $filter === $s ? 'is-active' : '' ?>"><?= e(status_label($s)) ?></a>
        <?php endforeach; ?>
    </div>

    <section class="panel">
        <?php if (!$rows): ?>
            <p class="muted">Brak zgłoszeń do wyświetlenia.</p>
        <?php else: ?>
        <div class="table-scroll">
        <table class="data-table" data-testid="appointments-table">
            <thead>
                <tr><th>Numer</th><th>Data</th><th>Godzina</th><th>Klient</th><th>Telefon</th><th>Sprzęt</th><th>Status</th><th>Akcje</th></tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td class="mono"><?= e($r['appointment_number']) ?></td>
                    <td class="nowrap"><?= e($r['slot_date']) ?></td>
                    <td><?= e(substr($r['slot_time'],0,5)) ?></td>
                    <td><?= e($r['full_name']) ?></td>
                    <td class="nowrap"><?= e($r['phone']) ?></td>
                    <td><?= e(device_type_label($r['device_type'])) ?></td>
                    <td><span class="badge badge-<?= e($r['status']) ?>"><?= e(status_label($r['status'])) ?></span></td>
                    <td><a href="<?= u('/admin/appointments.php') ?>?id=<?= (int) $r['id'] ?>" class="btn btn-ghost btn-sm" data-testid="view-appt-<?= (int) $r['id'] ?>">Szczegóły</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </section>
<?php endif; ?>
<?php require __DIR__ . '/partials/footer.php'; ?>
