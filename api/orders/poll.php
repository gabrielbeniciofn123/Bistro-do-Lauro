<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/includes/bootstrap.php';

require_method('GET');
$user = Auth::requireRoles('admin', 'counter', 'waiter', 'kitchen');
$since = filter_var($_GET['since_event_id'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
header('Cache-Control: no-store');
json_response(OrderService::poll($user, $since === false ? 0 : (int) $since));
