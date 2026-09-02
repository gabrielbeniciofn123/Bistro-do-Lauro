<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/views/print-layout.php';

Auth::requireRoles('admin', 'counter', 'waiter');
$sessionId = filter_var($_GET['session_id'] ?? null, FILTER_VALIDATE_INT);
if ($sessionId === false || $sessionId < 1) {
    throw new DomainException('Atendimento inválido.');
}
$session = TableService::detailsBySession((int) $sessionId);
$settings = SettingsService::get();
$serviceFee = $session['status'] === 'closed'
    ? (float) $session['service_fee']
    : ((bool) $settings['service_fee_enabled'] ? (float) $session['subtotal'] * (float) $settings['service_fee_percent'] / 100 : 0.0);
$estimatedTotal = $session['status'] === 'closed' ? (float) $session['total'] : (float) $session['subtotal'] + $serviceFee;
print_start('Conta da Mesa ' . $session['table_number']);
?>
<p><strong>Mesa:</strong> <?= e($session['table_number']) ?> · <?= e($session['area_name'] ?? '') ?></p>
<p><strong>Garçom:</strong> <?= e($session['waiter_name']) ?></p>
<p><strong>Abertura:</strong> <?= e(date('d/m/Y H:i', strtotime($session['opened_at']))) ?></p>
<hr class="receipt-divider">
<?php foreach ($session['orders'] as $order): ?>
    <p><strong>Pedido #<?= (int) $order['id'] ?></strong> · <?= e(date('H:i', strtotime($order['created_at']))) ?></p>
    <?php foreach ($order['items'] as $item): if ($item['status'] === 'cancelled') continue; ?>
        <div class="receipt-item"><div class="summary-row"><span><?= (int) $item['quantity'] ?>× <?= e($item['product_name']) ?></span><span>R$ <?= e(number_format((float) $item['line_total'], 2, ',', '.')) ?></span></div><?php if ($item['notes']): ?><small>Obs.: <?= e($item['notes']) ?></small><?php endif; ?></div>
    <?php endforeach; ?>
<?php endforeach; ?>
<hr class="receipt-divider">
<div class="summary-row"><span>Subtotal</span><strong>R$ <?= e(number_format((float) $session['subtotal'], 2, ',', '.')) ?></strong></div>
<?php if ($serviceFee > 0): ?><div class="summary-row"><span>Taxa de serviço</span><strong>R$ <?= e(number_format($serviceFee, 2, ',', '.')) ?></strong></div><?php endif; ?>
<div class="summary-row total"><span>Total estimado</span><strong>R$ <?= e(number_format($estimatedTotal, 2, ',', '.')) ?></strong></div>
<p class="receipt-center"><small>Valores sujeitos à conferência no fechamento.</small></p>
<?php print_end(); ?>
