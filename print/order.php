<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/views/print-layout.php';

Auth::requireRoles('admin', 'counter', 'kitchen');
$orderId = filter_var($_GET['order_id'] ?? null, FILTER_VALIDATE_INT);
if ($orderId === false || $orderId < 1) {
    throw new DomainException('Pedido inválido.');
}
$order = OrderService::details((int) $orderId);
print_start('Pedido de produção #' . $order['id']);
?>
<p style="font-size:16px"><strong>MESA <?= e($order['table_number']) ?></strong></p>
<p>Garçom: <?= e($order['waiter_name']) ?> · <?= e(date('H:i', strtotime($order['created_at']))) ?></p>
<hr class="receipt-divider">
<?php foreach ($order['items'] as $item): if ($item['status'] === 'cancelled') continue; ?>
    <div class="receipt-item"><p style="font-size:14px"><strong><?= (int) $item['quantity'] ?>× <?= e($item['product_name']) ?></strong></p><?php if ($item['modifiers']): ?><small><?= e(implode(', ', array_column($item['modifiers'], 'modifier_name'))) ?></small><?php endif; ?><?php if ($item['notes']): ?><p style="font-size:13px"><strong>OBS: <?= e(mb_strtoupper($item['notes'])) ?></strong></p><?php endif; ?></div>
<?php endforeach; ?>
<?php print_end(); ?>
