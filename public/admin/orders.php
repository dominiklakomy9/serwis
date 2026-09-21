<?php
/**
 * admin/orders.php — Protokoły przyjęcia sprzętu.
 *
 * W tej wersji moduł protokołów jest w trybie przygotowanym (architektura
 * bazy: service_orders, devices, service_order_status_history jest gotowa).
 * Widok listuje zgłoszenia gotowe do przekształcenia w protokół przyjęcia
 * (te, których sprzęt jest w realizacji) oraz istniejące protokoły.
 */
declare(strict_types=1);
require __DIR__ . '/../../app/bootstrap.php';
admin_require_login();

$pdo = db();

// Zgłoszenia, dla których można w przyszłości utworzyć protokół przyjęcia.
$candidates = $pdo->query(
    "SELECT a.id, a.appointment_number, a.slot_date, a.device_type, c.full_name
     FROM appointments a JOIN customers c ON c.id = a.customer_id
     WHERE a.status IN ('confirmed','in_progress','waiting_for_customer')
     ORDER BY a.slot_date ASC LIMIT 100"
)->fetchAll();

// Istniejące protokoły (jeśli powstaną w przyszłości).
$orders = $pdo->query(
    "SELECT o.order_number, o.received_at, o.released_at, c.full_name
     FROM service_orders o JOIN customers c ON c.id = o.customer_id
     ORDER BY o.created_at DESC LIMIT 100"
)->fetchAll();

$adminTitle = 'Protokoły przyjęcia';
$adminActive = 'orders';
require __DIR__ . '/partials/header.php';
?>
<div class="notice info">
    <strong>Moduł przygotowany.</strong> Struktura bazy pod elektroniczne protokoły przyjęcia
    (dane sprzętu, stan wizualny, akcesoria, zdjęcia, historia) jest gotowa i możliwa do
    rozbudowy. Poniżej zgłoszenia, dla których protokół można utworzyć w kolejnym etapie.
</div>

<section class="panel">
    <h2>Zgłoszenia gotowe do przyjęcia</h2>
    <?php if (!$candidates): ?>
        <p class="muted">Brak aktywnych zgłoszeń.</p>
    <?php else: ?>
    <div class="table-scroll">
    <table class="data-table">
        <thead><tr><th>Numer</th><th>Termin</th><th>Klient</th><th>Sprzęt</th><th>Akcje</th></tr></thead>
        <tbody>
        <?php foreach ($candidates as $c): ?>
            <tr>
                <td class="mono"><?= e($c['appointment_number']) ?></td>
                <td class="nowrap"><?= e($c['slot_date']) ?></td>
                <td><?= e($c['full_name']) ?></td>
                <td><?= e(device_type_label($c['device_type'])) ?></td>
                <td><a href="/admin/appointments.php?id=<?= (int) $c['id'] ?>" class="btn btn-ghost btn-sm">Otwórz zgłoszenie</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</section>

<?php if ($orders): ?>
<section class="panel">
    <h2>Protokoły</h2>
    <table class="data-table">
        <thead><tr><th>Numer</th><th>Klient</th><th>Przyjęto</th><th>Wydano</th></tr></thead>
        <tbody>
        <?php foreach ($orders as $o): ?>
            <tr>
                <td class="mono"><?= e($o['order_number']) ?></td>
                <td><?= e($o['full_name']) ?></td>
                <td><?= $o['received_at'] ? e($o['received_at']) : '<span class="muted">—</span>' ?></td>
                <td><?= $o['released_at'] ? e($o['released_at']) : '<span class="muted">—</span>' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>
<?php endif; ?>
<?php require __DIR__ . '/partials/footer.php'; ?>
