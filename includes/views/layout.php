<?php
declare(strict_types=1);

function role_label(string $role): string
{
    return match ($role) {
        'admin' => 'Administrador',
        'counter' => 'Balcão / Caixa',
        'waiter' => 'Garçom',
        'kitchen' => 'Cozinha',
        default => 'Usuário',
    };
}

function nav_items(string $role): array
{
    return match ($role) {
        'admin' => [
            ['dashboard', '/admin/?view=dashboard', '▦', 'Dashboard'],
            ['tables', '/admin/?view=tables', '▦', 'Mesas e salões'],
            ['products', '/admin/?view=products', '◫', 'Cardápio'],
            ['categories', '/admin/?view=categories', '≡', 'Categorias'],
            ['modifiers', '/admin/?view=modifiers', '＋', 'Complementos'],
            ['users', '/admin/?view=users', '◎', 'Usuários'],
            ['orders', '/balcao/', '◴', 'Pedidos'],
            ['kitchen', '/cozinha/', '♨', 'Cozinha'],
            ['history', '/admin/?view=history', '⌁', 'Histórico'],
            ['settings', '/admin/?view=settings', '⚙', 'Configurações'],
        ],
        'counter' => [
            ['orders', '/balcao/', '▦', 'Pedidos'],
            ['tables', '/balcao/?view=tables', '▦', 'Mesas'],
            ['history', '/balcao/?view=history', '⌁', 'Histórico'],
        ],
        'waiter' => [
            ['tables', '/garcom/', '▦', 'Mesas'],
            ['orders', '/garcom/?view=orders', '◴', 'Meus pedidos'],
        ],
        'kitchen' => [
            ['kitchen', '/cozinha/', '♨', 'Painel da cozinha'],
        ],
        default => [],
    };
}

function render_app_start(string $title, string $active, array $options = []): void
{
    $user = Auth::requireLogin();
    $bodyClass = $options['body_class'] ?? '';
    $subtitle = $options['subtitle'] ?? role_label($user['role']);
    $newOrderCount = (int) ($options['new_order_count'] ?? 0);
    ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= e($title) ?> — Bistrô São Lauro PDV</title>
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="<?= e($bodyClass) ?>">
<div class="app-shell">
    <aside class="sidebar" id="sidebar">
        <a class="brand-lockup" href="<?= e(Auth::redirectPath($user['role'])) ?>"><span class="brand-mark">BS</span><span><strong>Bistrô São Lauro</strong><small>Sistema de gestão</small></span></a>
        <nav class="nav-list" aria-label="Menu principal">
            <?php foreach (nav_items($user['role']) as [$key, $href, $icon, $label]): ?>
                <a class="nav-link <?= $key === $active ? 'active' : '' ?>" href="<?= e($href) ?>"><span aria-hidden="true"><?= e($icon) ?></span><span><?= e($label) ?></span><?php if ($key === 'orders'): ?><span class="count <?= $newOrderCount > 0 ? '' : 'hidden' ?>" data-new-order-count><?= $newOrderCount ?></span><?php endif; ?></a>
            <?php endforeach; ?>
        </nav>
        <div class="sidebar-footer">
            <div class="user-chip"><span class="avatar"><?= e(mb_strtoupper(mb_substr($user['name'], 0, 1))) ?></span><div><strong><?= e($user['name']) ?></strong><small><?= e(role_label($user['role'])) ?></small></div></div>
            <form method="post" action="/logout.php"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><button class="nav-link" type="submit"><span aria-hidden="true">↪</span><span>Sair</span></button></form>
        </div>
    </aside>
    <div class="sidebar-scrim hidden" id="sidebarScrim"></div>
    <main class="app-main">
        <header class="topbar">
            <button class="icon-btn mobile-menu-button" id="mobileMenuButton" type="button" aria-label="Abrir menu" aria-controls="sidebar" aria-expanded="false">☰</button>
            <div class="topbar-title"><h1><?= e($title) ?></h1><p><?= e($subtitle) ?></p></div>
            <div class="topbar-actions"><span class="connection-status" id="connectionStatus"><span>Conectado</span></span><?= $options['topbar_actions'] ?? '' ?></div>
        </header>
        <div class="page-content">
    <?php
}

function render_app_end(array $scripts = []): void
{
    ?>
        </div>
    </main>
</div>
<div class="modal-backdrop hidden" id="globalModal" role="presentation"><section class="modal" role="dialog" aria-modal="true" aria-labelledby="globalModalTitle"><header class="modal-header"><h2 id="globalModalTitle">Detalhes</h2><button class="icon-btn" type="button" data-close-modal aria-label="Fechar">×</button></header><div class="modal-body" id="globalModalBody"></div><footer class="modal-footer" id="globalModalFooter"></footer></section></div>
<div class="toast-region" id="toastRegion" aria-live="polite" aria-atomic="true"></div>
<script src="/assets/js/api.js"></script>
<?php foreach ($scripts as $script): ?><script src="<?= e($script) ?>"></script><?php endforeach; ?>
</body>
</html>
    <?php
}
