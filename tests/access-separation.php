<?php
declare(strict_types=1);

require dirname(__DIR__) . '/includes/Auth.php';

function assert_access(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException('Falha: ' . $message);
    }
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrf_token(): string
{
    return 'test-csrf-token';
}

require dirname(__DIR__) . '/includes/views/layout.php';

$permissions = [
    'panel.waiter' => ['admin' => true, 'counter' => false, 'waiter' => true, 'kitchen' => false],
    'panel.counter' => ['admin' => true, 'counter' => true, 'waiter' => false, 'kitchen' => false],
    'tables.read' => ['admin' => true, 'counter' => true, 'waiter' => true, 'kitchen' => false],
    'tables.open' => ['admin' => true, 'counter' => false, 'waiter' => true, 'kitchen' => false],
    'tables.request_bill' => ['admin' => true, 'counter' => false, 'waiter' => true, 'kitchen' => false],
    'catalog.read' => ['admin' => true, 'counter' => false, 'waiter' => true, 'kitchen' => false],
    'orders.create' => ['admin' => true, 'counter' => false, 'waiter' => true, 'kitchen' => false],
];

foreach ($permissions as $permission => $roles) {
    foreach ($roles as $role => $expected) {
        assert_access(
            Auth::roleCan($role, $permission) === $expected,
            "matriz incorreta para {$role} em {$permission}"
        );
    }
}
assert_access(!Auth::roleCan('admin', 'permission.unknown'), 'permissão desconhecida deve ser negada');

$_SESSION['user'] = [
    'id' => 1,
    'name' => 'Admin Teste',
    'login' => 'admin_teste',
    'email' => null,
    'role' => 'admin',
];
$_SESSION['user_validated_at'] = time();

function render_test_layout(string $navigationRole, string $active): string
{
    ob_start();
    render_app_start('Área de teste', $active, ['navigation_role' => $navigationRole]);
    render_app_end();
    return (string) ob_get_clean();
}

$waiterLayout = render_test_layout('waiter', 'tables');
assert_access(str_contains($waiterLayout, 'data-app-area="waiter"'), 'layout do garçom deve declarar sua área');
assert_access(str_contains($waiterLayout, 'href="/garcom/"'), 'layout do garçom deve usar suas próprias rotas');
assert_access(!str_contains($waiterLayout, 'href="/balcao/'), 'layout do garçom não deve exibir rotas do balcão');
assert_access(str_contains($waiterLayout, '>Administração</span>'), 'administrador deve conseguir voltar ao painel administrativo');

$counterLayout = render_test_layout('counter', 'orders');
assert_access(str_contains($counterLayout, 'data-app-area="counter"'), 'layout do balcão deve declarar sua área');
assert_access(str_contains($counterLayout, 'href="/balcao/"'), 'layout do balcão deve usar suas próprias rotas');
assert_access(!str_contains($counterLayout, 'href="/garcom/'), 'layout do balcão não deve exibir rotas do garçom');

$root = dirname(__DIR__);
$routePermissions = [
    'garcom/index.php' => 'panel.waiter',
    'balcao/index.php' => 'panel.counter',
    'api/orders/create.php' => 'orders.create',
    'api/products/list.php' => 'catalog.read',
    'api/tables/open.php' => 'tables.open',
    'api/tables/request-bill.php' => 'tables.request_bill',
    'api/tables/list.php' => 'tables.read',
    'api/tables/details.php' => 'tables.read',
];
foreach ($routePermissions as $file => $permission) {
    $source = file_get_contents($root . '/' . $file);
    assert_access(
        is_string($source) && str_contains($source, "Auth::requirePermission('{$permission}')"),
        "{$file} deve exigir {$permission}"
    );
}

$counterSource = file_get_contents($root . '/assets/js/counter.js');
assert_access(is_string($counterSource) && !str_contains($counterSource, '/garcom/'), 'frontend do balcão não deve navegar para o garçom');
assert_access(str_contains((string) $counterSource, 'pdv_counter_${userId}_'), 'estado local do balcão deve ser isolado por usuário');

$waiterSource = file_get_contents($root . '/assets/js/waiter.js');
assert_access(str_contains((string) $waiterSource, 'pdv_waiter_${userId}_'), 'carrinho do garçom deve ser isolado por usuário');

echo "Separação entre garçom e balcão validada com sucesso.\n";
