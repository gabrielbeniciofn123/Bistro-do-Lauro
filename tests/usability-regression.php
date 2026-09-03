<?php
declare(strict_types=1);

function assert_usability(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException('Falha: ' . $message);
    }
}

$root = dirname(__DIR__);
$waiterPage = file_get_contents($root . '/garcom/index.php');
$waiterSource = file_get_contents($root . '/assets/js/waiter.js');
$styles = file_get_contents($root . '/assets/css/app.css');
$databaseSource = file_get_contents($root . '/includes/Database.php');
$operations = file_get_contents($root . '/docs/OPERACAO.md');

assert_usability(is_string($waiterPage) && str_contains($waiterPage, 'id="productSearch"'), 'cardápio deve oferecer busca de produtos');
assert_usability(str_contains((string) $waiterPage, 'id="visibleProductCount"'), 'cardápio deve informar quantos produtos estão visíveis');
assert_usability(is_string($waiterSource) && str_contains($waiterSource, '.normalize("NFD")'), 'busca deve ignorar acentos');
assert_usability(str_contains((string) $waiterSource, 'data-clear-product-search'), 'resultado vazio deve permitir limpar a busca');
assert_usability(str_contains((string) $waiterSource, 'selectedInGroup.length > Number(option.dataset.groupMax)'), 'limite de complementos deve ter retorno imediato');
assert_usability(str_contains((string) $waiterSource, 'title: "Enviando pedido"'), 'envio deve apresentar estado de progresso');

assert_usability(is_string($styles) && str_contains($styles, '.cart-bar { position: fixed;'), 'carrinho deve permanecer acessível durante a navegação');
assert_usability(str_contains((string) $styles, '.product-card { min-height: 132px; display: grid; grid-template-columns: 96px minmax(0, 1fr); }'), 'produtos devem usar lista compacta no celular');
assert_usability(str_contains((string) $styles, '.modal-footer { position: sticky;'), 'ações do modal devem permanecer visíveis');
assert_usability(is_string($databaseSource) && str_contains($databaseSource, 'ROLLBACK TO SAVEPOINT'), 'falhas aninhadas devem preservar a atomicidade da operação');

assert_usability(is_string($operations), 'guia operacional deve existir');
foreach (['Publicação', 'Backup diário', 'Restauração', 'Homologação por perfil'] as $section) {
    assert_usability(str_contains((string) $operations, $section), "guia operacional deve conter a seção {$section}");
}

echo "Usabilidade e operação validadas com sucesso.\n";
