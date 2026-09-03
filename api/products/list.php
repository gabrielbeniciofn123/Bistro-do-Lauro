<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/includes/bootstrap.php';

require_method('GET');
Auth::requirePermission('catalog.read');
header('Cache-Control: private, max-age=30');
json_response(CatalogService::catalog());
