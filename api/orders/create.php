<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/includes/bootstrap.php';

require_method('POST');
$user = Auth::requireRoles('admin', 'counter', 'waiter');
verify_csrf();
json_response(OrderService::create(json_input(), $user), 201);
