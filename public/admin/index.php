<?php
/**
 * admin/index.php — pulpit (dashboard) z podsumowaniem.
 */
declare(strict_types=1);
require __DIR__ . '/../../app/bootstrap.php';
admin_require_login();

$pdo = db();
$today = (new DateTimeImmutable('today'))->format('Y-m-d');

// Statystyki
$newCount        = (int) $pdo->query("SELECT COUNT(*) FROM appointments WHERE status='pending'")->fetchColumn();
$activeCount     = (int) $pdo->query("SELECT COUNT(*) FROM appointments WHERE status IN ('confirmed','in_progress','waiting_for_customer')")->fetchColumn();
$completedCount  = (int) $pdo->query("SELECT COUNT(*) FROM appointments WHERE status='completed'")->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE slot_date = :d AND status NOT IN ('cancelled','no_show')");
$stmt->execute([':d' => $today]);
$todayDeliveries = (int) $stmt->fetchColumn();

// Najbliższe terminy
$stmt = $pdo->prepare(
    "SELECT a.appointment_number, a.slot_date, a.slot_time, a.status, a.device_type, c.full_name, c.phone
     FROM appointments a JOIN customers c ON c.id = a.customer_id
     WHERE a.slot_date >= :d AND a.status NOT IN ('cancelled','no_show','completed')
     ORDER BY a.slot_date ASC, a.slot_time ASC LIMIT 8"
);
$stmt->execute([':d' => $today]);
$upcoming = $stmt->fetchAll();

// Nowe zgłoszenia
$newest = $pdo->query(
    "SELECT a.appointment_number, a.slot_date, a.slot_time, a.status, a.id, c.full_name
     FROM appointments a JOIN customers c ON c.id = a.customer_id
     WHERE a.status='pending' ORDER BY a.created_at DESC LIMIT 6"
)->fetchAll();

$adminTitle = 'Pulpit';
$adminActive = 'dashboard';
require __DIR__ . '/partials/header.php';
?>
<div class="stat-grid">
    <div class="stat-card" data-testid="stat-new">
        <span class="stat-label">Nowe zgłoszenia</span>
        <span class="stat-value"><?= $newCount ?></span>
    </div>
    <div class="stat-card">
        <span class="stat-label">Dzisiejsze dostarczenia</span>
        <span class="stat-value"><?= $todayDeliveries ?></span>
    </div>
    <div class="stat-card">
        <span class="stat-label">Aktywne zlecenia</span>
        <span class="stat-value"><?= $activeCount ?></span>
    </div>
    <div class="stat-card">
        <span class="stat-label">Zakończone</span>
        <span class="stat-value"><?= $completedCount ?></span>
    </div>
</div>

<div class="admin-grid-2">
    <section class="panel">
        <h2>Najbliższe terminy</h2>
        <?php if (!$upcoming): ?>
            <p class="muted">Brak nadchodzących terminów.</p>
        <?php else: ?>
        <table class="data-table">
            <thead><tr><th>Termin</th><th>Klient</th><th>Sprzęt</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($upcoming as $u): ?>
                <tr>
                    <td class="nowrap"><?= e($u['slot_date']) ?><br><span class="muted"><?= e(substr($u['slot_time'],0,5)) ?></span></td>
                    <td><?= e($u['full_name']) ?><br><span class="muted small"><?= e($u['phone']) ?></span></td>
                    <td><?= e(device_type_label($u['device_type'])) ?></td>
                    <td><span class="badge badge-<?= e($u['status']) ?>"><?= e(status_label($u['status'])) ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </section>

    <section class="panel">
        <h2>Nowe zgłoszenia</h2>
        <?php if (!$newest): ?>
            <p class="muted">Brak nowych zgłoszeń.</p>
        <?php else: ?>
        <ul class="mini-list">
            <?php foreach ($newest as $n): ?>
            <li>
                    <a href="<?= u('/admin/appointments.php') ?>?id=<?= (int) $n['id'] ?>">                    <span class="mono"><?= e($n['appointment_number']) ?></span>
                    — <?= e($n['full_name']) ?>
                    <span class="muted small"><?= e($n['slot_date']) ?> <?= e(substr($n['slot_time'],0,5)) ?></span>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
        <a href="<?= u('/admin/appointments.php') ?>" class="btn btn-ghost btn-sm">Wszystkie zgłoszenia</a>
    </section>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>
