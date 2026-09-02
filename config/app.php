<?php
declare(strict_types=1);

return [
    'name' => 'Bistrô São Lauro PDV',
    'timezone' => 'America/Sao_Paulo',
    'session_name' => 'bistro_pdv_session',
    'session_lifetime' => 43200,
    'upload_max_bytes' => 5 * 1024 * 1024,
    'allowed_image_types' => [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ],
];
