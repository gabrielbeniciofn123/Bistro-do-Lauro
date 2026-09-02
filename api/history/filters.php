<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/includes/bootstrap.php';

require_method('GET');
Auth::requireRoles('admin', 'counter');
$pdo = Database::connection();
$tables = $pdo->query("SELECT id, number FROM restaurant_tables ORDER BY CAST(number AS UNSIGNED), number")->fetchAll();
$waiters = $pdo->query("SELECT id, name FROM users WHERE role = 'waiter' AND deleted_at IS NULL ORDER BY name")->fetchAll();
json_response(['tables' => $tables, 'waiters' => $waiters]);
