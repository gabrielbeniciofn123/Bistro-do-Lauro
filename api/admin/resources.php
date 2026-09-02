<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/services/AdminService.php';

$user = Auth::requireRoles('admin');
header('Cache-Control: no-store');
$resource = (string) ($_GET['resource'] ?? $_POST['resource'] ?? '');
$action = (string) ($_GET['action'] ?? $_POST['action'] ?? 'list');

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'list') {
    $result = ['items' => AdminService::list($resource)];
    if ($resource === 'products' && isset($_GET['product_id'])) {
        $result['modifier_group_ids'] = AdminService::productModifierGroups((int) $_GET['product_id']);
    }
    json_response($result);
}

require_method('POST');
verify_csrf();
if ($action === 'save') {
    $data = $_POST;
    if (str_starts_with($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
        $data = json_input();
    }
    json_response(AdminService::save($resource, $data, $_FILES['image'] ?? null));
}
if ($action === 'deactivate') {
    $data = str_starts_with($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') ? json_input() : $_POST;
    json_response(['items' => AdminService::deactivate($resource, request_int($data, 'id'))]);
}
json_error('Ação administrativa inválida.', 404);
