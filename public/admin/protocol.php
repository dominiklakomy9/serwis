<?php
/**
 * admin/protocol.php — protokół do druku (przyjęcia lub naprawy).
 *   ?id=<appointment>&type=intake|repair
 * Strona przeznaczona do wydruku (Ctrl+P) i przekazania klientowi.
 */
declare(strict_types=1);
$__bootstrap = null;
foreach (['/app/bootstrap.php', '/../app/bootstrap.php', '/../../app/bootstrap.php'] as $__cand) {
    if (@is_file(__DIR__ . $__cand)) { $__bootstrap = __DIR__ . $__cand; break; }
}
require $__bootstrap;
admin_require_login();

$pdo = db();
$id = (int) ($_GET['id'] ?? 0);
$type = ($_GET['type'] ?? 'intake') === 'repair' ? 'repair' : 'intake';

$stmt = $pdo->prepare('SELECT a.*, c.full_name, c.phone, c.email FROM appointments a JOIN customers c ON c.id = a.customer_id WHERE a.id = :id');
$stmt->execute([':id' => $id]);
$a = $stmt->fetch();
if (!$a) { http_response_code(404); exit('Nie znaleziono zlecenia.'); }

$steps = [];
if ($type === 'repair') {
    $rs = $pdo->prepare('SELECT * FROM repair_steps WHERE appointment_id=:id ORDER BY created_at ASC');
    $rs->execute([':id' => $id]);
    $steps = $rs->fetchAll();
}

$service = (string) config('app.name');
$owner   = (string) config('app.owner');
$phone   = (string) config('contact.phone');
$email   = (string) config('contact.email');
$title = $type === 'repair' ? 'Protokół naprawy' : 'Protokół przyjęcia sprzętu';
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($title) ?> — <?= e($a['appointment_number']) ?></title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; color: #111; background: #f3f4f6; margin: 0; padding: 24px; }
        .sheet { max-width: 800px; margin: 0 auto; background: #fff; padding: 40px; border: 1px solid #ddd; }
        .doc-head { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 3px solid #facc15; padding-bottom: 16px; margin-bottom: 24px; }
        .doc-head h1 { font-size: 20px; margin: 0 0 4px; }
        .doc-head .company { font-weight: 700; }
        .doc-head .muted { color: #666; font-size: 13px; }
        .doc-meta { text-align: right; font-size: 13px; }
        .doc-meta strong { font-size: 15px; }
        h2 { font-size: 14px; text-transform: uppercase; letter-spacing: .05em; color: #444; border-bottom: 1px solid #eee; padding-bottom: 6px; margin: 24px 0 12px; }
        table.kv { width: 100%; border-collapse: collapse; font-size: 14px; }
        table.kv td { padding: 7px 0; vertical-align: top; }
        table.kv td.k { color: #666; width: 200px; }
        .box { border: 1px solid #ddd; border-radius: 6px; padding: 12px; font-size: 14px; min-height: 44px; white-space: pre-wrap; }
        ol.steps { font-size: 14px; padding-left: 20px; }
        ol.steps li { margin-bottom: 8px; }
        ol.steps .date { color: #888; font-size: 12px; }
        .sign { display: flex; justify-content: space-between; margin-top: 60px; gap: 40px; }
        .sign div { flex: 1; border-top: 1px solid #333; padding-top: 6px; font-size: 12px; color: #555; text-align: center; }
        .print-bar { max-width: 800px; margin: 0 auto 16px; text-align: right; }
        .print-bar button { background: #facc15; border: none; padding: 10px 18px; border-radius: 6px; font-weight: 700; cursor: pointer; }
        @media print { body { background: #fff; padding: 0; } .sheet { border: none; max-width: none; } .print-bar { display: none; } }
    </style>
</head>
<body>
    <div class="print-bar"><button onclick="window.print()">Drukuj / zapisz PDF</button></div>
    <div class="sheet">
        <div class="doc-head">
            <div>
                <div class="company"><?= e($owner) ?></div>
                <div class="muted"><?= e($service) ?></div>
                <div class="muted"><?= e($phone) ?> &middot; <?= e($email) ?></div>
            </div>
            <div class="doc-meta">
                <strong><?= e($title) ?></strong><br>
                Nr: <?= e($a['appointment_number']) ?><br>
                Data: <?= e(date('Y-m-d')) ?>
            </div>
        </div>

        <h2>Dane klienta</h2>
        <table class="kv">
            <tr><td class="k">Imię i nazwisko</td><td><?= e($a['full_name']) ?></td></tr>
            <tr><td class="k">Telefon</td><td><?= e($a['phone']) ?></td></tr>
            <?php if ($a['email']): ?><tr><td class="k">E-mail</td><td><?= e($a['email']) ?></td></tr><?php endif; ?>
        </table>

        <h2>Urządzenie</h2>
        <table class="kv">
            <tr><td class="k">Rodzaj</td><td><?= e(device_type_label($a['device_type'])) ?></td></tr>
            <tr><td class="k">Producent / model</td><td><?= e(trim(($a['device_manufacturer']??'').' '.($a['device_model']??''))) ?: '—' ?></td></tr>
            <tr><td class="k">Numer seryjny</td><td><?= e($a['device_serial'] ?? '') ?: '—' ?></td></tr>
        </table>

        <?php if ($type === 'intake'): ?>
            <h2>Przyjęcie</h2>
            <table class="kv">
                <tr><td class="k">Data przyjęcia</td><td><?= e($a['received_at'] ?? '') ?: '—' ?></td></tr>
                <tr><td class="k">Przekazane akcesoria</td><td><?= e($a['accessories'] ?? '') ?: '—' ?></td></tr>
            </table>
            <h2>Stan wizualny</h2>
            <div class="box"><?= e($a['visual_condition'] ?? '') ?: '—' ?></div>
            <h2>Zgłaszany problem</h2>
            <div class="box"><?= e($a['problem_description']) ?></div>

            <div class="sign">
                <div>Podpis klienta</div>
                <div>Podpis serwisanta</div>
            </div>
        <?php else: ?>
            <h2>Przebieg naprawy</h2>
            <?php if (!$steps): ?>
                <p>Brak zarejestrowanych kroków naprawy.</p>
            <?php else: ?>
                <ol class="steps">
                    <?php foreach ($steps as $st): ?>
                        <li><?= e($st['step_text']) ?> <span class="date">(<?= e($st['created_at']) ?>)</span></li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
            <h2>Zakończenie</h2>
            <table class="kv">
                <tr><td class="k">Status</td><td><?= e(status_label($a['status'])) ?></td></tr>
                <tr><td class="k">Data wydania</td><td><?= e($a['released_at'] ?? '') ?: '—' ?></td></tr>
            </table>
            <?php if ($a['technician_notes']): ?>
                <h2>Uwagi serwisanta</h2>
                <div class="box"><?= e($a['technician_notes']) ?></div>
            <?php endif; ?>

            <div class="sign">
                <div>Podpis klienta (odbiór)</div>
                <div>Podpis serwisanta</div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
