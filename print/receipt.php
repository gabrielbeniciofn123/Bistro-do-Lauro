<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/views/print-layout.php';

Auth::requireRoles('admin', 'counter');
$sessionId = filter_var($_GET['session_id'] ?? null, FILTER_VALIDATE_INT);
if ($sessionId === false || $sessionId < 1) {
    throw new DomainException('Venda inválida.');
}
$session = TableService::detailsBySession((int) $sessionId);
print_start('Comprovante interno #' . $session['id']);
?>
<p><strong>Mesa:</strong> <?= e($session['table_number']) ?></p><p><strong>Garçom:</strong> <?= e($session['waiter_name']) ?></p><p><strong>Fechamento:</strong> <?= e($session['closed_at'] ? date('d/m/Y H:i', strtotime($session['closed_at'])) : 'Em aberto') ?></p>
<hr class="receipt-divider">
<?php foreach ($session['orders'] as $order): foreach ($order['items'] as $item): if ($order['status'] === 'cancelled' || $item['status'] === 'cancelled') continue; ?>
<div class="receipt-item"><div class="summary-row"><span><?= (int) $item['quantity'] ?>× <?= e($item['product_name']) ?></span><span>R$ <?= e(number_format((float) $item['line_total'], 2, ',', '.')) ?></span></div></div>
<?php endforeach; endforeach; ?>
<hr class="receipt-divider"><div class="summary-row"><span>Subtotal</span><span>R$ <?= e(number_format((float) $session['subtotal'], 2, ',', '.')) ?></span></div><div class="summary-row"><span>Taxa</span><span>R$ <?= e(number_format((float) $session['service_fee'], 2, ',', '.')) ?></span></div><div class="summary-row"><span>Desconto</span><span>- R$ <?= e(number_format((float) $session['discount'], 2, ',', '.')) ?></span></div><div class="summary-row"><span>Acréscimo</span><span>R$ <?= e(number_format((float) $session['surcharge'], 2, ',', '.')) ?></span></div><div class="summary-row total"><strong>Total</strong><strong>R$ <?= e(number_format((float) $session['total'], 2, ',', '.')) ?></strong></div>
<hr class="receipt-divider"><p><strong>Pagamentos</strong></p><?php foreach ($session['payments'] as $payment): ?><div class="summary-row"><span><?= e($payment['payment_method']) ?></span><span>R$ <?= e(number_format((float) $payment['amount'], 2, ',', '.')) ?></span></div><?php endforeach; ?>
<?php print_end(); ?>
