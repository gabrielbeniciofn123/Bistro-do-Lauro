<?php
declare(strict_types=1);

function assert_realtime(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException('Falha: ' . $message);
    }
}

$root = dirname(__DIR__);
$apiSource = file_get_contents($root . '/assets/js/api.js');
$counterSource = file_get_contents($root . '/assets/js/counter.js');
$kitchenSource = file_get_contents($root . '/assets/js/kitchen.js');
$waiterSource = file_get_contents($root . '/assets/js/waiter.js');

assert_realtime(is_string($apiSource), 'não foi possível ler o cliente compartilhado');
assert_realtime(str_contains($apiSource, 'function startPolling(task, options = {})'), 'cliente deve oferecer polling compartilhado');
assert_realtime(str_contains($apiSource, 'if (running) return running'), 'polling deve impedir requisições sobrepostas');
assert_realtime(str_contains($apiSource, 'Atualização automática restabelecida.'), 'recuperação da sincronização deve ser informada');
assert_realtime(str_contains($apiSource, 'setConnection(false, errorLabel)'), 'falha da sincronização deve ficar visível no cabeçalho');

assert_realtime(is_string($counterSource) && substr_count($counterSource, 'PDV.startPolling(') === 2, 'balcão deve sincronizar pedidos e mesas com o monitor compartilhado');
assert_realtime(str_contains($counterSource, 'alertAudioContext ||= new AudioContext()'), 'aviso sonoro deve reutilizar o contexto de áudio');
assert_realtime(str_contains($counterSource, 'Novo pedido na Mesa'), 'novo pedido deve gerar também um aviso visual');
assert_realtime(!str_contains($counterSource, 'setInterval(() => pollOrders(false), 2000)'), 'balcão não deve manter o polling antigo sobreposto');

assert_realtime(is_string($kitchenSource) && str_contains($kitchenSource, 'PDV.startPolling(() => poll(false)'), 'cozinha deve usar o monitor compartilhado');
assert_realtime(!str_contains($kitchenSource, 'setInterval(() => poll(false), 2000)'), 'cozinha não deve manter o polling antigo sobreposto');

assert_realtime(is_string($waiterSource) && substr_count($waiterSource, 'PDV.startPolling(') === 2, 'garçom deve sincronizar mesas e pedidos');
assert_realtime(str_contains($waiterSource, 'if (areas.includes(selectedArea)) areaFilter.value = selectedArea'), 'atualização das mesas deve preservar o filtro de salão');
assert_realtime(!str_contains($waiterSource, 'setInterval(pollOrders, 2000)'), 'garçom não deve manter o polling antigo sobreposto');

echo "Atualização em tempo real validada com sucesso.\n";
