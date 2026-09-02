<?php
declare(strict_types=1);

function print_start(string $title): void
{
    ?><!DOCTYPE html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow"><title><?= e($title) ?></title><link rel="stylesheet" href="/assets/css/app.css"><style>.print-screen-actions{display:flex;justify-content:center;gap:.5rem;margin:18px}.print-receipt{background:#fff;padding:8mm;margin:20px auto;box-shadow:0 8px 30px rgba(0,0,0,.12)}.receipt-divider{border:0;border-top:1px dashed #222;margin:10px 0}.receipt-center{text-align:center}.receipt-item{margin:8px 0}.receipt-item small{display:block}.print-receipt p{color:#111;margin:4px 0;line-height:1.35}</style></head><body><div class="print-screen-actions no-print"><button class="btn btn-primary" onclick="window.print()">Imprimir</button><button class="btn btn-secondary" onclick="window.close()">Fechar</button></div><main class="print-receipt"><header class="receipt-center"><h1>Bistrô São Lauro</h1><p><?= e($title) ?></p></header><hr class="receipt-divider"><?php
}

function print_end(): void
{
    ?><hr class="receipt-divider"><p class="receipt-center">Documento interno · <?= e(date('d/m/Y H:i')) ?></p></main></body></html><?php
}
