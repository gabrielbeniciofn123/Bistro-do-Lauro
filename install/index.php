<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$lockFile = $root . '/config/install.lock';
if (is_file($lockFile)) {
    header('Location: /login.php');
    exit;
}

$appConfig = require $root . '/config/app.php';
date_default_timezone_set((string) $appConfig['timezone']);
session_name((string) $appConfig['session_name']);
session_start();
require_once $root . '/includes/security.php';

$error = null;
$success = false;
$defaults = [
    'db_host' => 'localhost',
    'db_port' => '3306',
    'db_name' => '',
    'db_user' => '',
    'restaurant_name' => 'Bistrô São Lauro',
    'admin_name' => '',
    'admin_login' => 'admin',
    'admin_email' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf($_POST['_csrf'] ?? null);
        if (is_file($lockFile)) {
            throw new DomainException('O sistema já foi instalado.');
        }

        $values = array_merge($defaults, array_intersect_key($_POST, $defaults));
        $host = trim((string) $values['db_host']);
        $port = filter_var($values['db_port'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]);
        $database = trim((string) $values['db_name']);
        $username = trim((string) $values['db_user']);
        $password = (string) ($_POST['db_password'] ?? '');
        $restaurantName = trim((string) $values['restaurant_name']);
        $adminName = trim((string) $values['admin_name']);
        $adminLogin = mb_strtolower(trim((string) $values['admin_login']));
        $adminEmail = mb_strtolower(trim((string) $values['admin_email']));
        $adminPassword = (string) ($_POST['admin_password'] ?? '');

        if ($host === '' || $port === false || !preg_match('/^[a-zA-Z0-9_$-]{1,64}$/', $database) || $username === '') {
            throw new DomainException('Preencha corretamente os dados do banco MySQL.');
        }
        if ($restaurantName === '' || mb_strlen($restaurantName) > 150 || $adminName === '' || mb_strlen($adminName) > 120) {
            throw new DomainException('Informe o nome do restaurante e do administrador.');
        }
        if (!preg_match('/^[a-z0-9._-]{3,80}$/', $adminLogin)) {
            throw new DomainException('O login deve ter de 3 a 80 caracteres e usar apenas letras, números, ponto, hífen ou sublinhado.');
        }
        if ($adminEmail !== '' && !filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
            throw new DomainException('Informe um e-mail válido.');
        }
        if (strlen($adminPassword) < 10) {
            throw new DomainException('A senha do administrador deve ter pelo menos 10 caracteres.');
        }

        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $database);
        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => true,
            PDO::MYSQL_ATTR_MULTI_STATEMENTS => true,
        ]);

        $schema = file_get_contents($root . '/database.sql');
        if ($schema === false || trim($schema) === '') {
            throw new RuntimeException('Arquivo database.sql não encontrado.');
        }
        $pdo->exec($schema);
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

        $pdo->beginTransaction();
        try {
            $settings = $pdo->prepare(
                'INSERT INTO restaurant_settings (id, restaurant_name, timezone, service_fee_enabled, service_fee_percent, restaurant_open)
                 VALUES (1, :name, :timezone, 1, 10.00, 1)
                 ON DUPLICATE KEY UPDATE restaurant_name = VALUES(restaurant_name), timezone = VALUES(timezone)'
            );
            $settings->execute(['name' => $restaurantName, 'timezone' => 'America/Sao_Paulo']);

            $existingAdmin = $pdo->prepare('SELECT id FROM users WHERE login = :login LIMIT 1 FOR UPDATE');
            $existingAdmin->execute(['login' => $adminLogin]);
            if ($existingAdmin->fetchColumn()) {
                throw new DomainException('Já existe um usuário com esse login.');
            }

            $createAdmin = $pdo->prepare(
                'INSERT INTO users (name, login, email, password_hash, role, active)
                 VALUES (:name, :login, :email, :password_hash, :role, 1)'
            );
            $createAdmin->execute([
                'name' => $adminName,
                'login' => $adminLogin,
                'email' => $adminEmail === '' ? null : $adminEmail,
                'password_hash' => password_hash($adminPassword, PASSWORD_DEFAULT),
                'role' => 'admin',
            ]);

            if (isset($_POST['demo_data'])) {
                install_demo_data($pdo);
            }

            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }

        $databaseConfig = [
            'host' => $host,
            'port' => (int) $port,
            'database' => $database,
            'username' => $username,
            'password' => $password,
            'charset' => 'utf8mb4',
            'app_secret' => bin2hex(random_bytes(32)),
        ];
        $configContent = "<?php\ndeclare(strict_types=1);\n\nreturn " . var_export($databaseConfig, true) . ";\n";
        $tempFile = tempnam($root . '/config', 'database.');
        if ($tempFile === false || file_put_contents($tempFile, $configContent, LOCK_EX) === false) {
            throw new RuntimeException('Não foi possível salvar a configuração. Verifique a permissão da pasta config.');
        }
        chmod($tempFile, 0600);
        if (!rename($tempFile, $root . '/config/database.php')) {
            @unlink($tempFile);
            throw new RuntimeException('Não foi possível ativar a configuração do banco.');
        }
        if (file_put_contents($lockFile, 'Instalado em ' . date(DATE_ATOM) . PHP_EOL, LOCK_EX) === false) {
            throw new RuntimeException('Não foi possível criar o bloqueio do instalador.');
        }

        session_regenerate_id(true);
        $success = true;
    } catch (Throwable $exception) {
        error_log('Falha na instalação: ' . $exception->getMessage());
        $error = $exception instanceof DomainException
            ? $exception->getMessage()
            : 'Não foi possível concluir a instalação. Confira os dados do MySQL e as permissões das pastas.';
    }
}

function install_demo_data(PDO $pdo): void
{
    $pdo->exec("INSERT IGNORE INTO areas (name, sort_order, active) VALUES ('Salão Principal', 1, 1)");
    $areaId = (int) $pdo->query("SELECT id FROM areas WHERE name = 'Salão Principal' LIMIT 1")->fetchColumn();
    $table = $pdo->prepare('INSERT IGNORE INTO restaurant_tables (area_id, number, status, active) VALUES (:area, :number, :status, 1)');
    foreach (['01', '02', '03', '04'] as $number) {
        $table->execute(['area' => $areaId, 'number' => $number, 'status' => 'available']);
    }

    $category = $pdo->prepare('INSERT IGNORE INTO categories (name, sort_order, active) VALUES (:name, :sort, 1)');
    foreach ([['Porções', 1], ['Pratos', 2], ['Bebidas', 3]] as [$name, $sort]) {
        $category->execute(['name' => $name, 'sort' => $sort]);
    }
    $ids = [];
    foreach ($pdo->query('SELECT id, name FROM categories')->fetchAll() as $row) {
        $ids[$row['name']] = (int) $row['id'];
    }

    $product = $pdo->prepare(
        'INSERT INTO products (category_id, name, description, price, available, sort_order, active)
         SELECT :category, :name, :description, :price, 1, :sort, 1
         WHERE NOT EXISTS (SELECT 1 FROM products WHERE name = :name_check)'
    );
    $demo = [
        [$ids['Porções'], 'Porção de Tilápia', 'Tilápia crocante para compartilhar.', '39.90', 1],
        [$ids['Pratos'], 'Filé à Parmegiana', 'Filé empanado com molho e queijo.', '49.90', 1],
        [$ids['Bebidas'], 'Suco de Maracujá', 'Suco preparado na hora.', '8.00', 1],
        [$ids['Bebidas'], 'Coca-Cola Zero', 'Lata 350 ml.', '7.00', 2],
    ];
    foreach ($demo as [$categoryId, $name, $description, $price, $sort]) {
        $product->execute([
            'category' => $categoryId,
            'name' => $name,
            'description' => $description,
            'price' => $price,
            'sort' => $sort,
            'name_check' => $name,
        ]);
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Instalar — Bistrô São Lauro PDV</title>
    <meta name="robots" content="noindex,nofollow">
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="auth-page install-page">
<main class="install-card">
    <div class="brand-lockup"><span class="brand-mark">BS</span><span><strong>Bistrô São Lauro</strong><small>Instalação segura do PDV</small></span></div>
    <?php if ($success): ?>
        <section class="success-panel"><span class="status-icon success">✓</span><h1>Instalação concluída</h1><p>O banco, as configurações e o administrador foram criados. O instalador foi bloqueado automaticamente.</p><a class="btn btn-primary" href="/login.php">Fazer primeiro acesso</a></section>
    <?php else: ?>
        <header class="install-heading"><span class="eyebrow">Primeira configuração</span><h1>Prepare o restaurante para operar</h1><p>Informe o banco criado na HostGator e defina o primeiro administrador.</p></header>
        <?php if ($error): ?><div class="alert alert-error" role="alert"><?= e($error) ?></div><?php endif; ?>
        <form method="post" class="install-form">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <fieldset><legend>Banco de dados MySQL</legend><div class="form-grid">
                <label class="field"><span>Servidor</span><input name="db_host" required value="<?= e($_POST['db_host'] ?? $defaults['db_host']) ?>"></label>
                <label class="field"><span>Porta</span><input name="db_port" type="number" min="1" max="65535" required value="<?= e($_POST['db_port'] ?? $defaults['db_port']) ?>"></label>
                <label class="field"><span>Nome do banco</span><input name="db_name" required value="<?= e($_POST['db_name'] ?? '') ?>"></label>
                <label class="field"><span>Usuário do banco</span><input name="db_user" required value="<?= e($_POST['db_user'] ?? '') ?>"></label>
                <label class="field full"><span>Senha do banco</span><input name="db_password" type="password" autocomplete="new-password"></label>
            </div></fieldset>
            <fieldset><legend>Restaurante e administrador</legend><div class="form-grid">
                <label class="field full"><span>Nome do restaurante</span><input name="restaurant_name" maxlength="150" required value="<?= e($_POST['restaurant_name'] ?? $defaults['restaurant_name']) ?>"></label>
                <label class="field"><span>Nome do administrador</span><input name="admin_name" maxlength="120" required value="<?= e($_POST['admin_name'] ?? '') ?>"></label>
                <label class="field"><span>Login</span><input name="admin_login" maxlength="80" required value="<?= e($_POST['admin_login'] ?? $defaults['admin_login']) ?>"></label>
                <label class="field"><span>E-mail (opcional)</span><input name="admin_email" type="email" maxlength="190" value="<?= e($_POST['admin_email'] ?? '') ?>"></label>
                <label class="field"><span>Senha (mínimo 10 caracteres)</span><input name="admin_password" type="password" minlength="10" autocomplete="new-password" required></label>
            </div></fieldset>
            <label class="check-field"><input type="checkbox" name="demo_data" value="1" <?= isset($_POST['demo_data']) ? 'checked' : '' ?>><span>Instalar mesas e produtos de demonstração (podem ser desativados depois)</span></label>
            <button class="btn btn-primary btn-block" type="submit">Instalar sistema</button>
        </form>
    <?php endif; ?>
</main>
</body>
</html>
