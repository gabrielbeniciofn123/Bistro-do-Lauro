<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/views/layout.php';

Auth::requireRoles('admin');
$views = ['dashboard', 'tables', 'products', 'categories', 'modifiers', 'users', 'history', 'settings'];
$view = in_array($_GET['view'] ?? 'dashboard', $views, true) ? (string) ($_GET['view'] ?? 'dashboard') : 'dashboard';
$titles = [
    'dashboard' => 'Visão geral', 'tables' => 'Mesas e salões', 'products' => 'Cardápio',
    'categories' => 'Categorias', 'modifiers' => 'Complementos', 'users' => 'Usuários',
    'history' => 'Histórico de vendas', 'settings' => 'Configurações',
];
render_app_start($titles[$view], $view, ['subtitle' => 'Administração do restaurante']);
?>
<div id="adminPage" data-view="<?= e($view) ?>">
<?php if ($view === 'dashboard'): ?>
    <header class="page-header"><div><span class="eyebrow">Hoje</span><h2>Operação em números</h2><p>Informações calculadas diretamente das vendas e pedidos.</p></div><a class="btn btn-secondary" href="/balcao/">Abrir painel do balcão</a></header>
    <section class="stats-grid" aria-label="Indicadores">
        <article class="stat-card"><span class="label">Vendas de hoje</span><strong id="statSales">—</strong><small>Mesas finalizadas</small></article>
        <article class="stat-card"><span class="label">Pedidos de hoje</span><strong id="statOrders">—</strong><small>Sem cancelados</small></article>
        <article class="stat-card"><span class="label">Ticket médio</span><strong id="statTicket">—</strong><small>Por mesa fechada</small></article>
        <article class="stat-card"><span class="label">Mesas abertas</span><strong id="statOpenTabs">—</strong><small><span id="statAvailable">—</span> disponíveis</small></article>
        <article class="stat-card"><span class="label">Em preparo</span><strong id="statPreparing">—</strong><small>Pedidos ativos</small></article>
        <article class="stat-card"><span class="label">Prontos</span><strong id="statReady">—</strong><small>Aguardando entrega</small></article>
    </section>
    <section class="panel"><header class="panel-header"><h3>Produtos mais vendidos hoje</h3></header><div class="table-wrap"><table class="data-table"><thead><tr><th>Produto</th><th>Quantidade</th><th>Total</th></tr></thead><tbody id="topProductsBody"></tbody></table></div></section>
<?php elseif ($view === 'tables'): ?>
    <header class="page-header"><div><span class="eyebrow">Organização</span><h2>Mesas e salões</h2><p>Cadastre os setores e defina todas as mesas da operação.</p></div></header>
    <div style="display:grid;gap:1rem">
        <section class="panel" data-admin-resource="areas"><header class="panel-header"><h3>Salões e setores</h3><button class="btn btn-primary btn-sm" data-add-resource="areas">Adicionar salão</button></header><div class="table-wrap"><table class="data-table"><thead><tr><th>Nome</th><th>Mesas</th><th>Ordem</th><th>Status</th><th></th></tr></thead><tbody data-resource-body="areas"></tbody></table></div></section>
        <section class="panel" data-admin-resource="tables"><header class="panel-header"><h3>Mesas</h3><button class="btn btn-primary btn-sm" data-add-resource="tables">Adicionar mesa</button></header><div class="table-wrap"><table class="data-table"><thead><tr><th>Mesa</th><th>Nome</th><th>Salão</th><th>Situação</th><th></th></tr></thead><tbody data-resource-body="tables"></tbody></table></div></section>
    </div>
<?php elseif ($view === 'modifiers'): ?>
    <header class="page-header"><div><span class="eyebrow">Personalização</span><h2>Grupos e opções</h2><p>Defina adicionais obrigatórios ou opcionais para os produtos.</p></div></header>
    <div style="display:grid;gap:1rem">
        <section class="panel" data-admin-resource="modifier_groups"><header class="panel-header"><h3>Grupos de complementos</h3><button class="btn btn-primary btn-sm" data-add-resource="modifier_groups">Adicionar grupo</button></header><div class="table-wrap"><table class="data-table"><thead><tr><th>Grupo</th><th>Escolhas</th><th>Opções</th><th>Status</th><th></th></tr></thead><tbody data-resource-body="modifier_groups"></tbody></table></div></section>
        <section class="panel" data-admin-resource="modifiers"><header class="panel-header"><h3>Opções de complementos</h3><button class="btn btn-primary btn-sm" data-add-resource="modifiers">Adicionar opção</button></header><div class="table-wrap"><table class="data-table"><thead><tr><th>Opção</th><th>Grupo</th><th>Acréscimo</th><th>Status</th><th></th></tr></thead><tbody data-resource-body="modifiers"></tbody></table></div></section>
    </div>
<?php elseif (in_array($view, ['products', 'categories', 'users'], true)): ?>
    <?php $resourceLabels = ['products' => ['Produtos', 'Adicionar produto'], 'categories' => ['Categorias', 'Adicionar categoria'], 'users' => ['Usuários', 'Adicionar usuário']]; ?>
    <header class="page-header"><div><span class="eyebrow">Cadastro</span><h2><?= e($resourceLabels[$view][0]) ?></h2><p>Todos os dados desta tela são gravados no banco do restaurante.</p></div><button class="btn btn-primary" data-add-resource="<?= e($view) ?>"><?= e($resourceLabels[$view][1]) ?></button></header>
    <section class="panel" data-admin-resource="<?= e($view) ?>"><div class="toolbar panel-body"><input class="input search" type="search" placeholder="Buscar..." data-resource-search="<?= e($view) ?>"></div><div class="table-wrap"><table class="data-table"><thead><tr id="resourceHead"></tr></thead><tbody data-resource-body="<?= e($view) ?>"></tbody></table></div></section>
<?php elseif ($view === 'settings'): ?>
    <header class="page-header"><div><span class="eyebrow">Operação</span><h2>Configurações do restaurante</h2><p>Identidade, funcionamento e taxa de serviço.</p></div></header>
    <form class="panel" id="settingsForm"><div class="panel-body form-grid">
        <label class="field full"><span>Nome do restaurante</span><input name="restaurant_name" required maxlength="150"></label>
        <label class="field"><span>Telefone</span><input name="phone" maxlength="30"></label>
        <label class="field"><span>WhatsApp</span><input name="whatsapp" maxlength="30"></label>
        <label class="field full"><span>Endereço</span><input name="address" maxlength="255"></label>
        <label class="field"><span>CNPJ (opcional)</span><input name="cnpj" maxlength="20"></label>
        <label class="field"><span>Moeda</span><select name="currency"><option value="BRL">Real brasileiro (BRL)</option></select></label>
        <label class="field"><span>Fuso horário</span><select name="timezone"><option value="America/Sao_Paulo">America/Sao_Paulo</option></select></label>
        <label class="field"><span>Taxa de serviço (%)</span><input name="service_fee_percent" inputmode="decimal" required></label>
        <label class="check-field"><input name="service_fee_enabled" type="checkbox"><span>Taxa de serviço ativada</span></label>
        <label class="check-field"><input name="restaurant_open" type="checkbox"><span>Restaurante aberto para novos pedidos</span></label>
    </div><footer class="panel-header" style="justify-content:flex-end"><button class="btn btn-primary" type="submit">Salvar configurações</button></footer></form>
<?php else: ?>
    <header class="page-header"><div><span class="eyebrow">Vendas</span><h2>Histórico</h2><p>Consulte vendas finalizadas e seus pagamentos.</p></div></header>
    <section class="panel"><div class="panel-body" id="historyAdminMount"></div></section>
<?php endif; ?>
</div>
<?php render_app_end(['/assets/js/admin.js', '/assets/js/history.js']); ?>
