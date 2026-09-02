<?php
declare(strict_types=1);

if (getenv('PDV_TEST_CONFIRM') !== '1') {
    fwrite(STDERR, "Defina PDV_TEST_CONFIRM=1 para executar o teste.\n");
    exit(2);
}

$configFile = dirname(__DIR__) . '/config/database.php';
if (!is_file($configFile)) {
    fwrite(STDERR, "Crie config/database.php apontando para um banco de teste.\n");
    exit(2);
}
$databaseConfig = require $configFile;
if (!str_ends_with((string) ($databaseConfig['database'] ?? ''), '_test')) {
    fwrite(STDERR, "O nome do banco precisa terminar em _test.\n");
    exit(2);
}

require dirname(__DIR__) . '/includes/bootstrap.php';
$pdo = Database::connection();
$pdo->beginTransaction();

function assert_test(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException('Falha: ' . $message);
    }
}

function cents_to_money(int $cents): string
{
    return sprintf('%d.%02d', intdiv($cents, 100), $cents % 100);
}

try {
    $suffix = bin2hex(random_bytes(4));
    $users = [];
    $pdo->exec("INSERT INTO restaurant_settings (id, restaurant_name) VALUES (1, 'Restaurante Teste') ON DUPLICATE KEY UPDATE restaurant_name = VALUES(restaurant_name), restaurant_open = 1");
    $userInsert = $pdo->prepare('INSERT INTO users (name, login, password_hash, role, active) VALUES (:name, :login, :password, :role, 1)');
    foreach ([['Admin Teste', 'admin_' . $suffix, 'admin'], ['Garçom Teste', 'waiter_' . $suffix, 'waiter'], ['Caixa Teste', 'counter_' . $suffix, 'counter'], ['Cozinha Teste', 'kitchen_' . $suffix, 'kitchen']] as [$name, $login, $role]) {
        $userInsert->execute(['name' => $name, 'login' => $login, 'password' => password_hash('TesteSeguro123!', PASSWORD_DEFAULT), 'role' => $role]);
        $users[$role] = (int) $pdo->lastInsertId();
    }
    $_SESSION['user'] = ['id' => $users['admin'], 'name' => 'Admin Teste', 'login' => 'admin_' . $suffix, 'role' => 'admin'];
    $pdo->prepare('INSERT INTO areas (name, sort_order, active) VALUES (:name, 1, 1)')->execute(['name' => 'Salão Teste ' . $suffix]);
    $areaId = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO restaurant_tables (area_id, number, status, active) VALUES (:area, :number, :status, 1)')->execute(['area' => $areaId, 'number' => 'T' . $suffix, 'status' => 'available']);
    $tableId = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO categories (name, sort_order, active) VALUES (:name, 1, 1)')->execute(['name' => 'Categoria ' . $suffix]);
    $categoryId = (int) $pdo->lastInsertId();
    $productInsert = $pdo->prepare('INSERT INTO products (category_id, name, price, available, active) VALUES (:category, :name, :price, 1, 1)');
    $productInsert->execute(['category' => $categoryId, 'name' => 'Tilápia Teste', 'price' => '39.90']);
    $tilapiaId = (int) $pdo->lastInsertId();
    $productInsert->execute(['category' => $categoryId, 'name' => 'Suco Teste', 'price' => '8.00']);
    $juiceId = (int) $pdo->lastInsertId();
    $productInsert->execute(['category' => $categoryId, 'name' => 'Sobremesa Teste', 'price' => '15.00']);
    $dessertId = (int) $pdo->lastInsertId();

    $session = TableService::open($tableId, $users['waiter']);
    assert_test($session['status'] === 'open', 'mesa deve abrir');
    $waiter = ['id' => $users['waiter'], 'role' => 'waiter', 'name' => 'Garçom Teste'];
    $firstKey = 'test-first-' . $suffix;
    $order = OrderService::create([
        'table_session_id' => $session['id'], 'idempotency_key' => $firstKey,
        'items' => [
            ['product_id' => $tilapiaId, 'quantity' => 2, 'notes' => 'Uma tilápia sem cebola.', 'modifier_ids' => []],
            ['product_id' => $juiceId, 'quantity' => 1, 'notes' => '', 'modifier_ids' => []],
        ],
    ], $waiter);
    assert_test($order['total'] === '87.80', 'total do primeiro pedido');
    $duplicate = OrderService::create(['table_session_id' => $session['id'], 'idempotency_key' => $firstKey, 'items' => [['product_id' => $juiceId, 'quantity' => 1]]], $waiter);
    assert_test($duplicate['id'] === $order['id'] && !empty($duplicate['duplicate']), 'idempotência deve retornar o mesmo pedido');

    $kitchen = ['id' => $users['kitchen'], 'role' => 'kitchen', 'name' => 'Cozinha Teste'];
    OrderService::changeStatus($order['id'], 'preparing', $kitchen);
    OrderService::changeStatus($order['id'], 'ready', $kitchen);
    $counter = ['id' => $users['counter'], 'role' => 'counter', 'name' => 'Caixa Teste'];
    OrderService::changeStatus($order['id'], 'delivered', $counter);

    $second = OrderService::create([
        'table_session_id' => $session['id'],
        'idempotency_key' => 'test-second-' . $suffix,
        'items' => [['product_id' => $dessertId, 'quantity' => 1, 'notes' => '', 'modifier_ids' => []]],
    ], $waiter);
    assert_test($second['total'] === '15.00', 'segundo pedido deve ser independente');
    OrderService::changeStatus($second['id'], 'preparing', $kitchen);
    OrderService::changeStatus($second['id'], 'ready', $kitchen);
    OrderService::changeStatus($second['id'], 'delivered', $counter);
    $details = TableService::details($tableId);
    assert_test(count($details['orders']) === 2 && $details['subtotal'] === '102.80', 'mesa deve manter dois pedidos');

    TableService::requestBill($tableId);
    $repeatedBill = TableService::requestBill($tableId);
    assert_test($repeatedBill['status'] === 'payment_pending', 'solicitação de conta repetida deve ser idempotente');
    $totalCents = 10280;
    $paymentKey = 'test-payment-' . $suffix;
    $closed = PaymentService::closeTable([
        'table_session_id' => $session['id'], 'idempotency_key' => $paymentKey,
        'apply_service_fee' => false, 'discount' => '0.00', 'surcharge' => '0.00',
        'payments' => [
            ['method' => 'pix', 'amount' => '50.00'],
            ['method' => 'credit_card', 'amount' => cents_to_money($totalCents - 5000)],
        ],
    ], $counter);
    assert_test($closed['status'] === 'closed' && $closed['total'] === '102.80', 'mesa deve fechar com pagamento dividido');
    $duplicatePayment = PaymentService::closeTable([
        'table_session_id' => $session['id'], 'idempotency_key' => $paymentKey,
        'apply_service_fee' => false, 'discount' => '0.00', 'surcharge' => '0.00',
        'payments' => [['method' => 'pix', 'amount' => '102.80']],
    ], $counter);
    assert_test(!empty($duplicatePayment['duplicate']), 'fechamento repetido deve retornar a mesma venda');
    $tableStatus = $pdo->prepare('SELECT status FROM restaurant_tables WHERE id = :id');
    $tableStatus->execute(['id' => $tableId]);
    assert_test($tableStatus->fetchColumn() === 'available', 'mesa deve voltar a disponível');

    echo "Fluxo completo validado com sucesso.\n";
    $pdo->rollBack();
    exit(0);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, $exception->getMessage() . "\n" . $exception->getTraceAsString() . "\n");
    exit(1);
}
