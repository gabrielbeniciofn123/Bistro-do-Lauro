<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/includes/bootstrap.php';

require_method('GET');
Auth::requireRoles('admin', 'counter', 'waiter');
header('Cache-Control: no-store');
json_response(['tables' => TableService::list(), 'server_time' => date(DATE_ATOM)]);
