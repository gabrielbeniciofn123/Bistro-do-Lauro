<?php
declare(strict_types=1);

function assert_table_status(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException('Falha: ' . $message);
    }
}

$root = dirname(__DIR__);
$waiterSource = file_get_contents($root . '/assets/js/waiter.js');
$counterSource = file_get_contents($root . '/assets/js/counter.js');
$openRouteSource = file_get_contents($root . '/api/tables/open.php');
$tableServiceSource = file_get_contents($root . '/includes/services/TableService.php');

assert_table_status(is_string($waiterSource), 'não foi possível ler o frontend do garçom');
assert_table_status(!str_contains($waiterSource, 'PDV.request("/api/tables/open.php"'), 'selecionar mesa não deve chamar a abertura persistente');
assert_table_status(str_contains($waiterSource, '{ table_id: state.session.table_id }'), 'primeiro pedido deve referenciar a mesa');
assert_table_status(str_contains($waiterSource, '{ table_session_id: state.session.id }'), 'pedidos seguintes devem referenciar a sessão aberta');

assert_table_status(is_string($counterSource), 'não foi possível ler o frontend do balcão');
assert_table_status(str_contains($counterSource, 'setInterval(() => loadTables().catch(() => {}), 2000)'), 'mesas do balcão devem atualizar a cada dois segundos');

assert_table_status(is_string($openRouteSource), 'não foi possível ler a rota antiga de abertura');
assert_table_status(str_contains($openRouteSource, "json_error('A mesa é aberta automaticamente"), 'rota antiga não deve ocupar mesa sem pedido');

assert_table_status(is_string($tableServiceSource), 'não foi possível ler o serviço de mesas');
assert_table_status(str_contains($tableServiceSource, "\$row['status'] = 'available'"), 'sessões vazias antigas devem ser apresentadas como disponíveis');

echo "Atualização do status das mesas validada com sucesso.\n";
