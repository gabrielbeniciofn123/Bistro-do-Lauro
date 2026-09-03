<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/views/layout.php';

$user = Auth::requirePermission('panel.waiter');
$showOrders = ($_GET['view'] ?? '') === 'orders';
render_app_start($showOrders ? 'Meus pedidos' : 'Atendimento de mesas', $showOrders ? 'orders' : 'tables', [
    'subtitle' => 'Olá, ' . $user['name'],
    'navigation_role' => 'waiter',
]);
?>
<div id="waiterApp" data-area="waiter" data-user-id="<?= (int) $user['id'] ?>" data-initial-view="<?= $showOrders ? 'orders' : 'tables' ?>">
    <section id="tablesView" class="<?= $showOrders ? 'hidden' : '' ?>">
        <header class="page-header"><div><span class="eyebrow">Salão</span><h2>Selecione uma mesa</h2><p>Monte o primeiro pedido em uma mesa disponível ou continue um atendimento em andamento.</p></div><button class="btn btn-secondary" id="refreshTables" type="button">Atualizar</button></header>
        <div class="toolbar"><input class="input search" id="tableSearch" type="search" placeholder="Buscar mesa ou salão"><select class="input" id="areaFilter" style="max-width:220px"><option value="">Todos os salões</option></select></div>
        <div class="tables-grid" id="waiterTablesGrid" aria-live="polite"></div>
    </section>

    <section id="menuView" class="hidden">
        <header class="page-header"><div><button class="btn btn-ghost btn-sm" id="backToTables" type="button">← Mesas</button><span class="eyebrow" id="selectedArea">Atendimento</span><h2 id="selectedTable">Mesa</h2><p id="selectedSessionMeta"></p></div><button class="btn btn-secondary" id="requestBillButton" type="button">Solicitar conta</button></header>
        <div class="category-tabs" id="waiterCategories" aria-label="Categorias"></div>
        <div class="products-grid" id="waiterProducts"></div>
        <div class="cart-bar hidden" id="cartBar"><div><p id="cartItemCount">0 itens</p><strong id="cartTotal">R$ 0,00</strong></div><button class="btn btn-success" id="openCart" type="button">Ver pedido</button></div>
    </section>

    <section id="ordersView" class="<?= $showOrders ? '' : 'hidden' ?>">
        <header class="page-header"><div><span class="eyebrow">Acompanhamento</span><h2>Meus pedidos</h2><p>A situação é atualizada automaticamente.</p></div></header>
        <div class="board-list" id="waiterOrdersList"></div>
    </section>
</div>
<?php render_app_end(['/assets/js/waiter.js']); ?>
