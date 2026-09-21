<?php
/**
 * admin/availability.php — zarządzanie dostępnością.
 *
 * Administrator może:
 *  - dodać blok dostępności (dzień, godzina OD, DO, interwał),
 *  - usunąć blok dostępności,
 *  - zablokować pojedynczy slot (nagłe obowiązki),
 *  - odblokować slot.
 *
 * Sloty są generowane automatycznie z bloków dostępności.
 */
declare(strict_types=1);
require __DIR__ . '/../../app/bootstrap.php';
admin_require_login();

$pdo = db();
$admin = admin_current();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_require();
    $action = (string) ($_POST['action'] ?? '');
    $errors = [];

    if ($action === 'add_block') {
        $dateObj = v_date($_POST['slot_date'] ?? '', $errors);
        $start = v_time($_POST['start_time'] ?? '', $errors);
        $end = v_time($_POST['end_time'] ?? '', $errors);
        $interval = (int) ($_POST['interval_minutes'] ?? 30);
        if ($interval < 10 || $interval > 240) {
            $errors['interval'] = 'Interwał musi mieścić się w zakresie 10–240 minut.';
        }
        if (!$errors && $start !== null && $end !== null && $end <= $start) {
            $errors['end'] = 'Godzina "do" musi być późniejsza niż "od".';
        }
        if (!$errors) {
            $stmt = $pdo->prepare(
                'INSERT INTO availability (slot_date, start_time, end_time, interval_minutes, created_by, created_at, updated_at)
                 VALUES (:d, :s, :e, :i, :by, UTC_TIMESTAMP(), UTC_TIMESTAMP())'
            );
            $stmt->execute([
                ':d' => $dateObj->format('Y-m-d'), ':s' => $start, ':e' => $end,
                ':i' => $interval, ':by' => $admin['id'],
            ]);
            app_log('availability', "Dodano blok {$dateObj->format('Y-m-d')} {$start}-{$end} (admin {$admin['id']})");
            $_SESSION['flash'] = 'Dodano dostępność.';
        } else {
            $_SESSION['flash_err'] = implode(' ', $errors);
        }
    } elseif ($action === 'delete_block') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('DELETE FROM availability WHERE id = :id');
        $stmt->execute([':id' => $id]);
        app_log('availability', "Usunięto blok #{$id} (admin {$admin['id']})");
        $_SESSION['flash'] = 'Usunięto blok dostępności.';
    } elseif ($action === 'block_slot') {
        $dateObj = v_date($_POST['slot_date'] ?? '', $errors);
        $time = v_time($_POST['slot_time'] ?? '', $errors);
        $reason = v_clean_string($_POST['reason'] ?? '', 190);
        if (!$errors) {
            $stmt = $pdo->prepare(
                'INSERT INTO blocked_slots (slot_date, slot_time, reason, created_by, created_at)
                 VALUES (:d, :t, :r, :by, UTC_TIMESTAMP())
                 ON DUPLICATE KEY UPDATE reason = VALUES(reason)'
            );
            $stmt->execute([':d' => $dateObj->format('Y-m-d'), ':t' => $time, ':r' => $reason ?: null, ':by' => $admin['id']]);
            $_SESSION['flash'] = 'Slot został zablokowany.';
        } else {
            $_SESSION['flash_err'] = 'Nieprawidłowe dane blokady slotu.';
        }
    } elseif ($action === 'unblock_slot') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('DELETE FROM blocked_slots WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $_SESSION['flash'] = 'Slot został odblokowany.';
    }
    redirect(u('/admin/availability.php'));
}

$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);
$flashErr = $_SESSION['flash_err'] ?? null; unset($_SESSION['flash_err']);

$today = (new DateTimeImmutable('today'))->format('Y-m-d');

// Bloki dostępności (od dziś).
$stmt = $pdo->prepare('SELECT * FROM availability WHERE slot_date >= :d ORDER BY slot_date ASC, start_time ASC');
$stmt->execute([':d' => $today]);
$blocks = $stmt->fetchAll();

// Zablokowane sloty (od dziś).
$stmt = $pdo->prepare('SELECT * FROM blocked_slots WHERE slot_date >= :d ORDER BY slot_date ASC, slot_time ASC');
$stmt->execute([':d' => $today]);
$blocked = $stmt->fetchAll();

$defaultInterval = (int) config('booking.default_interval', 30);

$adminTitle = 'Dostępność';
$adminActive = 'availability';
require __DIR__ . '/partials/header.php';
?>
<?php if ($flash): ?><div class="form-alert show ok" data-testid="avail-flash"><?= e($flash) ?></div><?php endif; ?>
<?php if ($flashErr): ?><div class="form-alert show error"><?= e($flashErr) ?></div><?php endif; ?>

<div class="admin-grid-2">
    <section class="panel">
        <h2>Dodaj dostępność</h2>
        <p class="muted small">System automatycznie wygeneruje sloty co wybrany interwał (np. 09:00–13:00 co 30 min).</p>
        <form method="post" action="<?= u('/admin/availability.php') ?>" data-testid="add-availability-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_block">
            <div class="form-row">
                <label for="aDate">Dzień</label>
                <input type="date" id="aDate" name="slot_date" min="<?= e($today) ?>" required data-testid="avail-date">
            </div>
            <div class="form-grid-2">
                <div class="form-row">
                    <label for="aStart">Godzina OD</label>
                    <input type="time" id="aStart" name="start_time" required data-testid="avail-start">
                </div>
                <div class="form-row">
                    <label for="aEnd">Godzina DO</label>
                    <input type="time" id="aEnd" name="end_time" required data-testid="avail-end">
                </div>
            </div>
            <div class="form-row">
                <label for="aInterval">Interwał (minuty)</label>
                <select id="aInterval" name="interval_minutes" data-testid="avail-interval">
                    <?php foreach ([15,20,30,45,60] as $iv): ?>
                        <option value="<?= $iv ?>" <?= $iv === $defaultInterval ? 'selected' : '' ?>><?= $iv ?> min</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary" data-testid="avail-add-btn">Dodaj dostępność</button>
        </form>

        <hr class="sep">

        <h2>Zablokuj pojedynczy slot</h2>
        <form method="post" action="<?= u('/admin/availability.php') ?>" data-testid="block-slot-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="block_slot">
            <div class="form-grid-2">
                <div class="form-row">
                    <label for="bDate">Dzień</label>
                    <input type="date" id="bDate" name="slot_date" min="<?= e($today) ?>" required>
                </div>
                <div class="form-row">
                    <label for="bTime">Godzina</label>
                    <input type="time" id="bTime" name="slot_time" required>
                </div>
            </div>
            <div class="form-row">
                <label for="bReason">Powód (widoczny tylko dla Ciebie)</label>
                <input type="text" id="bReason" name="reason" maxlength="190">
            </div>
            <button type="submit" class="btn btn-ghost">Zablokuj slot</button>
        </form>
    </section>

    <section class="panel">
        <h2>Zaplanowana dostępność</h2>
        <?php if (!$blocks): ?>
            <p class="muted">Brak zaplanowanej dostępności. Dodaj pierwszy blok po lewej.</p>
        <?php else: ?>
        <table class="data-table">
            <thead><tr><th>Dzień</th><th>Godziny</th><th>Interwał</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($blocks as $b): ?>
                <tr>
                    <td class="nowrap"><?= e($b['slot_date']) ?></td>
                    <td><?= e(substr($b['start_time'],0,5)) ?>–<?= e(substr($b['end_time'],0,5)) ?></td>
                    <td><?= (int) $b['interval_minutes'] ?> min</td>
                    <td>
                        <form method="post" action="<?= u('/admin/availability.php') ?>" onsubmit="return confirm('Usunąć ten blok dostępności?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete_block">
                            <input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm">Usuń</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>

        <h3 class="mt">Zablokowane sloty</h3>
        <?php if (!$blocked): ?>
            <p class="muted">Brak zablokowanych slotów.</p>
        <?php else: ?>
        <table class="data-table">
            <thead><tr><th>Dzień</th><th>Godzina</th><th>Powód</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($blocked as $bl): ?>
                <tr>
                    <td class="nowrap"><?= e($bl['slot_date']) ?></td>
                    <td><?= e(substr($bl['slot_time'],0,5)) ?></td>
                    <td><?= $bl['reason'] ? e($bl['reason']) : '<span class="muted">—</span>' ?></td>
                    <td>
                        <form method="post" action="<?= u('/admin/availability.php') ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="unblock_slot">
                            <input type="hidden" name="id" value="<?= (int) $bl['id'] ?>">
                            <button type="submit" class="btn btn-ghost btn-sm">Odblokuj</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </section>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>
