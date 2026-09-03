<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/includes/bootstrap.php';

require_method('POST');
$user = Auth::requirePermission('tables.open');
verify_csrf();
$data = json_input();
json_response(TableService::open(request_int($data, 'table_id'), (int) $user['id']), 201);
