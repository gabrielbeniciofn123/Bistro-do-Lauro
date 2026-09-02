<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/includes/bootstrap.php';

require_method('POST');
$user = Auth::requireRoles('admin', 'counter', 'kitchen');
verify_csrf();
$data = json_input();
json_response(OrderService::changeStatus(request_int($data, 'order_id'), (string) ($data['status'] ?? ''), $user));
