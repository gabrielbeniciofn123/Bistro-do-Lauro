<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/includes/bootstrap.php';

require_method('GET');
Auth::requireRoles('admin', 'counter', 'waiter');
$tableId = filter_var($_GET['table_id'] ?? null, FILTER_VALIDATE_INT);
if ($tableId === false || $tableId < 1) {
    json_error('Mesa inválida.', 422);
}
header('Cache-Control: no-store');
json_response(TableService::details((int) $tableId));
