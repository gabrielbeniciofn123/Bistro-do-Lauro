<?php
declare(strict_types=1);

$publicPage = __DIR__ . '/index.html';
if (!is_file($publicPage)) {
    http_response_code(404);
    exit('Página inicial não encontrada.');
}
header('Content-Type: text/html; charset=utf-8');
readfile($publicPage);
