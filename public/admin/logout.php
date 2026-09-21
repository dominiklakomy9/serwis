<?php
/**
 * admin/logout.php — wylogowanie administratora.
 */
declare(strict_types=1);
// Odszukanie warstwy aplikacji niezależnie od układu katalogów.
$__bootstrap = null;
foreach (['/app/bootstrap.php', '/../app/bootstrap.php', '/../../app/bootstrap.php'] as $__cand) {
    if (@is_file(__DIR__ . $__cand)) { $__bootstrap = __DIR__ . $__cand; break; }
}
require $__bootstrap;

admin_logout();
redirect(u('/admin/login.php'));
