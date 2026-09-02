<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/views/layout.php';

Auth::requireRoles('kitchen', 'admin', 'counter');
render_app_start('Painel da cozinha', 'kitchen', ['subtitle' => 'Pedidos atualizados automaticamente', 'body_class' => 'kitchen-page']);
?>
<div id="kitchenApp">
    <header class="page-header"><div><span class="eyebrow">KDS · Produção</span><h2 style="color:white">Pedidos para preparar</h2><p>Inicie o preparo e marque como pronto assim que finalizar.</p></div><button class="btn btn-secondary" id="refreshKitchen" type="button">Atualizar agora</button></header>
    <div class="kds-grid" id="kitchenGrid"></div>
</div>
<?php render_app_end(['/assets/js/kitchen.js']); ?>
