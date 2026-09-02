<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /login.php');
    exit;
}
verify_csrf($_POST['_csrf'] ?? null);
Auth::logout();
header('Location: /login.php');
exit;
