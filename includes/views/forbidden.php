<!DOCTYPE html>
<html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Acesso negado</title><link rel="stylesheet" href="/assets/css/app.css"></head>
<body class="auth-page"><main class="auth-card"><span class="brand-mark">BS</span><h1>Acesso não permitido</h1><p>Seu usuário não possui permissão para abrir esta área.</p><a class="btn btn-primary" href="<?= e(Auth::redirectPath(Auth::user()['role'] ?? '')) ?>">Ir para minha área</a></main></body></html>
