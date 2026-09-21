<?php
/**
 * Wspólny nagłówek stron publicznych.
 * Zmienne opcjonalne przed dołączeniem:
 *   $pageTitle, $pageDescription, $canonicalPath, $activeNav
 */
$service = (string) config('app.name');
$owner = (string) config('app.owner');
$tagline = (string) config('app.tagline');
$title = isset($pageTitle) ? $pageTitle . ' — ' . $service : $service;
$desc = $pageDescription ?? 'Pogotowie komputerowe: diagnostyka, konfiguracja, konserwacja i pomoc komputerowa. Umów termin dostarczenia sprzętu.';
$canonical = base_url($canonicalPath ?? '/');
$active = $activeNav ?? '';
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= e($title) ?></title>
    <meta name="description" content="<?= e($desc) ?>">
    <link rel="canonical" href="<?= e($canonical) ?>">
    <meta name="robots" content="index, follow">

    <!-- Open Graph -->
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="<?= e($service) ?>">
    <meta property="og:title" content="<?= e($title) ?>">
    <meta property="og:description" content="<?= e($desc) ?>">
    <meta property="og:url" content="<?= e($canonical) ?>">

    <link rel="icon" href="<?= u('/assets/images/favicon.svg') ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?= u('/assets/css/style.css') ?>">
</head>
<body data-base="<?= e(BASE) ?>" data-page="<?= e($active) ?>">
<a class="skip-link" href="#main">Przejdź do treści</a>
<header class="site-header">
    <div class="container header-inner">
        <a class="brand" href="<?= u('/index.php') ?>" aria-label="<?= e($service) ?> — strona główna">
            <span class="brand-mark" aria-hidden="true">DŁ</span>
            <span class="brand-text">
                <span class="brand-name"><?= e($owner) ?></span>
                <span class="brand-tag"><?= e($tagline) ?></span>
            </span>
        </a>

        <button class="nav-toggle" id="navToggle" aria-label="Otwórz menu" aria-expanded="false" aria-controls="mainNav">
            <span></span><span></span><span></span>
        </button>

        <nav class="main-nav" id="mainNav" aria-label="Menu główne">
            <a href="<?= u('/index.php') ?>#start" class="<?= $active === 'start' ? 'is-active' : '' ?>">Start</a>
            <a href="<?= u('/index.php') ?>#uslugi" class="<?= $active === 'uslugi' ? 'is-active' : '' ?>">Usługi</a>
            <a href="<?= u('/index.php') ?>#o-mnie">O mnie</a>
            <a href="<?= u('/index.php') ?>#jak-to-dziala">Jak to działa</a>
            <a href="<?= u('/booking.php') ?>" class="<?= $active === 'booking' ? 'is-active' : '' ?>">Umów dostarczenie</a>
            <a href="<?= u('/index.php') ?>#faq">FAQ</a>
            <a href="<?= u('/index.php') ?>#kontakt">Kontakt</a>
            <a href="<?= u('/status.php') ?>" class="<?= $active === 'status' ? 'is-active' : '' ?>">Status zlecenia</a>
            <a href="<?= u('/booking.php') ?>" class="btn btn-primary nav-cta" data-testid="header-book-btn">Umów dostarczenie sprzętu</a>
        </nav>
    </div>
</header>
<main id="main">
