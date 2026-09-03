<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/includes/bootstrap.php';

require_method('POST');
Auth::requirePermission('tables.request_bill');
verify_csrf();
$data = json_input();
json_response(TableService::requestBill(request_int($data, 'table_id')));
