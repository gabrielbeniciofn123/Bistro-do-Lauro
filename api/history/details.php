<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/includes/bootstrap.php';

require_method('GET');
Auth::requireRoles('admin', 'counter');
$sessionId = filter_var($_GET['session_id'] ?? null, FILTER_VALIDATE_INT);
if ($sessionId === false || $sessionId < 1) {
    json_error('Venda inválida.', 422);
}
header('Cache-Control: no-store');
json_response(HistoryService::details((int) $sessionId));
