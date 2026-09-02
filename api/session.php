<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';

require_method('GET');
$user = Auth::requireLogin();
$settings = SettingsService::get();
json_response(['user' => $user, 'csrf_token' => csrf_token(), 'settings' => $settings, 'server_time' => date(DATE_ATOM)]);
