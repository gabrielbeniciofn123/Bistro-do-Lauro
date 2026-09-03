<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/views/layout.php';

$user = Auth::requirePermission('panel.counter');
$views = ['orders', 'tables', 'history'];
$view = in_array($_GET['view'] ?? 'orders', $views, true) ? (string) ($_GET['view'] ?? 'orders') : 'orders';
$titles = ['orders' => 'Painel do balcão', 'tables' => 'Controle de mesas', 'history' => 'Histórico de vendas'];
$soundButton = $view === 'orders' ? '<button class="btn btn-secondary btn-sm" id="enableSound" type="button">Ativar som dos pedidos</button>' : '';
render_app_start($titles[$view], $view, [
    'subtitle' => 'Atualização automática a cada 2 segundos',
    'topbar_actions' => $soundButton,
    'navigation_role' => 'counter',
]);
?>
<div id="counterApp" data-area="counter" data-user-id="<?= (int) $user['id'] ?>" data-view="<?= e($view) ?>">
<?php if ($view === 'orders'): ?>
    <header class="page-header"><div><span class="eyebrow">Tempo real</span><h2>Fluxo de pedidos</h2><p>Novos pedidos, preparação e entrega em uma única tela.</p></div><button class="btn btn-secondary" id="refreshOrders" type="button">Atualizar agora</button></header>
    <div class="board">
        <section class="board-column"><header class="board-heading"><h3>Novos</h3><span class="badge badge-warning" id="newCount">0</span></header><div class="board-list" id="newOrders"></div></section>
        <section class="board-column"><header class="board-heading"><h3>Em preparo</h3><span class="badge badge-info" id="preparingCount">0</span></header><div class="board-list" id="preparingOrders"></div></section>
        <section class="board-column"><header class="board-heading"><h3>Prontos</h3><span class="badge badge-success" id="readyCount">0</span></header><div class="board-list" id="readyOrders"></div></section>
    </div>
<?php elseif ($view === 'tables'): ?>
    <header class="page-header"><div><span class="eyebrow">Salão</span><h2>Mesas do restaurante</h2><p>Abra detalhes, imprima a conta ou finalize o pagamento.</p></div><button class="btn btn-secondary" id="refreshCounterTables" type="button">Atualizar</button></header>
    <div class="tables-grid" id="counterTablesGrid"></div>
<?php else: ?>
    <header class="page-header"><div><span class="eyebrow">Vendas</span><h2>Histórico</h2><p>Consulte vendas finalizadas, pagamentos e comprovantes.</p></div></header>
    <section class="panel"><div class="panel-body" id="historyMount"></div></section>
<?php endif; ?>
</div>
<?php render_app_end(['/assets/js/counter.js', '/assets/js/history.js']); ?>
