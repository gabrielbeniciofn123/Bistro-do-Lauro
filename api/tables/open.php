<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/includes/bootstrap.php';

require_method('POST');
Auth::requirePermission('tables.open');
verify_csrf();
json_error('A mesa é aberta automaticamente quando o primeiro pedido é enviado.', 409);
