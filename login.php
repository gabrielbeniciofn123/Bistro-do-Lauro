<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

if (Auth::check()) {
    header('Location: ' . Auth::redirectPath((string) Auth::user()['role']));
    exit;
}

$error = null;
$installed = is_file(__DIR__ . '/config/database.php') && is_file(__DIR__ . '/config/install.lock');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf($_POST['_csrf'] ?? null);
        if (!$installed) {
            throw new DomainException('Conclua a instalação antes do primeiro acesso.');
        }
        if (!Auth::attempt((string) ($_POST['login'] ?? ''), (string) ($_POST['password'] ?? ''))) {
            throw new DomainException('Usuário ou senha inválidos.');
        }
        header('Location: ' . Auth::redirectPath((string) Auth::user()['role']));
        exit;
    } catch (DomainException $exception) {
        $error = $exception->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Entrar — Bistrô São Lauro PDV</title>
    <meta name="robots" content="noindex,nofollow">
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="auth-page">
<main class="auth-card">
    <a class="brand-lockup" href="/index.html"><span class="brand-mark">BS</span><span><strong>Bistrô São Lauro</strong><small>Gestão do restaurante</small></span></a>
    <div class="auth-heading"><span class="eyebrow">Acesso seguro</span><h1>Bem-vindo de volta</h1><p>Entre com seu usuário para acessar o PDV.</p></div>
    <?php if ($error): ?><div class="alert alert-error" role="alert"><?= e($error) ?></div><?php endif; ?>
    <?php if (!$installed): ?><div class="alert alert-warning">O sistema ainda não foi instalado. <a href="/install/">Abrir instalador</a>.</div><?php endif; ?>
    <form method="post" class="form-stack" autocomplete="on">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <label class="field"><span>Usuário ou e-mail</span><input name="login" type="text" autocomplete="username" required autofocus maxlength="120" value="<?= e($_POST['login'] ?? '') ?>"></label>
        <label class="field"><span>Senha</span><input name="password" type="password" autocomplete="current-password" required minlength="8"></label>
        <button class="btn btn-primary btn-block" type="submit" <?= $installed ? '' : 'disabled' ?>>Entrar no sistema</button>
    </form>
    <a class="auth-back" href="/index.html">← Voltar ao site</a>
</main>
</body>
</html>
