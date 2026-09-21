<?php
/**
 * admin/logout.php — wylogowanie administratora.
 */
declare(strict_types=1);
require __DIR__ . '/../../app/bootstrap.php';

admin_logout();
redirect('/admin/login.php');
