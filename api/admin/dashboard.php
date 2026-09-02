<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/services/DashboardService.php';

require_method('GET');
Auth::requireRoles('admin', 'counter');
header('Cache-Control: no-store');
json_response(DashboardService::summary());
