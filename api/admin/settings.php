<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/services/SettingsService.php';

Auth::requireRoles('admin');
header('Cache-Control: no-store');
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    json_response(SettingsService::get());
}
require_method('POST');
verify_csrf();
json_response(SettingsService::update(json_input()));
