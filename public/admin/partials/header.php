<?php
/**
 * Wspólny nagłówek panelu administratora (layout + sidebar + topbar).
 * Wymaga zalogowania. Ustaw $adminTitle i $adminActive przed dołączeniem.
 */
$admin = admin_current();
$title = ($adminTitle ?? 'Panel') . ' — Administracja';
$active = $adminActive ?? '';
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($title) ?></title>
    <link rel="icon" href="/assets/images/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body class="admin-body">
<div class="admin-shell">
    <aside class="admin-sidebar" id="adminSidebar">
        <div class="admin-brand">
            <span class="brand-mark" aria-hidden="true">DŁ</span>
            <span>
                <strong>Panel</strong><br>
                <small>Pogotowie Komputerowe</small>
            </span>
        </div>
        <nav class="admin-nav" aria-label="Nawigacja panelu">
            <a href="/admin/index.php" class="<?= $active === 'dashboard' ? 'is-active' : '' ?>" data-testid="nav-dashboard">Pulpit</a>
            <a href="/admin/appointments.php" class="<?= $active === 'appointments' ? 'is-active' : '' ?>" data-testid="nav-appointments">Zgłoszenia</a>
            <a href="/admin/availability.php" class="<?= $active === 'availability' ? 'is-active' : '' ?>" data-testid="nav-availability">Dostępność</a>
            <a href="/admin/orders.php" class="<?= $active === 'orders' ? 'is-active' : '' ?>" data-testid="nav-orders">Protokoły</a>
        </nav>
        <div class="admin-sidebar-foot">
            <a href="/index.php" target="_blank" rel="noopener">Zobacz stronę &#8599;</a>
        </div>
    </aside>

    <div class="admin-main">
        <header class="admin-topbar">
            <button class="admin-burger" id="adminBurger" aria-label="Menu">&#9776;</button>
            <h1 class="admin-page-title"><?= e($adminTitle ?? 'Panel') ?></h1>
            <div class="admin-user">
                <span class="admin-user-name"><?= e($admin['name'] ?: $admin['email']) ?></span>
                <a href="/admin/logout.php" class="btn btn-ghost btn-sm" data-testid="logout-btn">Wyloguj</a>
            </div>
        </header>
        <div class="admin-content">
