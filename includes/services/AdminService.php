<?php
declare(strict_types=1);

final class AdminService
{
    private const RESOURCES = ['areas', 'tables', 'categories', 'products', 'users', 'modifier_groups', 'modifiers'];
    private const ROLES = ['admin', 'counter', 'waiter', 'kitchen'];

    public static function list(string $resource): array
    {
        self::assertResource($resource);
        $pdo = Database::connection();
        $sql = match ($resource) {
            'areas' => "SELECT a.id, a.name, a.sort_order, a.active, a.created_at,
                              (SELECT COUNT(*) FROM restaurant_tables t WHERE t.area_id = a.id AND t.deleted_at IS NULL) AS table_count
                       FROM areas a WHERE a.deleted_at IS NULL ORDER BY a.sort_order, a.name",
            'tables' => "SELECT t.id, t.number, t.name, t.area_id, a.name AS area_name, t.status, t.active, t.created_at
                         FROM restaurant_tables t LEFT JOIN areas a ON a.id = t.area_id
                         WHERE t.deleted_at IS NULL ORDER BY a.sort_order, CAST(t.number AS UNSIGNED), t.number",
            'categories' => 'SELECT id, name, icon, sort_order, active, created_at FROM categories WHERE deleted_at IS NULL ORDER BY sort_order, name',
            'products' => "SELECT p.id, p.category_id, c.name AS category_name, p.name, p.description, p.price,
                                p.image_path, p.available, p.featured, p.sort_order, p.active, p.created_at
                           FROM products p INNER JOIN categories c ON c.id = p.category_id
                           WHERE p.deleted_at IS NULL ORDER BY c.sort_order, p.sort_order, p.name",
            'users' => 'SELECT id, name, login, email, role, active, last_login_at, created_at FROM users WHERE deleted_at IS NULL ORDER BY name',
            'modifier_groups' => "SELECT mg.id, mg.name, mg.min_choices, mg.max_choices, mg.required, mg.active, mg.sort_order,
                                        COUNT(m.id) AS modifier_count
                                  FROM modifier_groups mg LEFT JOIN modifiers m ON m.modifier_group_id = mg.id AND m.deleted_at IS NULL
                                  WHERE mg.deleted_at IS NULL GROUP BY mg.id ORDER BY mg.sort_order, mg.name",
            'modifiers' => "SELECT m.id, m.modifier_group_id, mg.name AS group_name, m.name, m.price_delta, m.active, m.sort_order
                            FROM modifiers m INNER JOIN modifier_groups mg ON mg.id = m.modifier_group_id
                            WHERE m.deleted_at IS NULL ORDER BY mg.sort_order, m.sort_order, m.name",
        };
        $rows = $pdo->query($sql)->fetchAll();
        foreach ($rows as &$row) {
            foreach (['active', 'available', 'featured', 'required'] as $boolean) {
                if (array_key_exists($boolean, $row)) {
                    $row[$boolean] = (bool) $row[$boolean];
                }
            }
        }
        unset($row);
        return $rows;
    }

    public static function save(string $resource, array $data, ?array $image = null): array
    {
        self::assertResource($resource);
        $id = isset($data['id']) && $data['id'] !== '' ? request_int($data, 'id') : null;

        try {
            return Database::transaction(function (PDO $pdo) use ($resource, $data, $image, $id): array {
                $savedId = match ($resource) {
                    'areas' => self::saveArea($pdo, $data, $id),
                    'tables' => self::saveTable($pdo, $data, $id),
                    'categories' => self::saveCategory($pdo, $data, $id),
                    'products' => self::saveProduct($pdo, $data, $image, $id),
                    'users' => self::saveUser($pdo, $data, $id),
                    'modifier_groups' => self::saveModifierGroup($pdo, $data, $id),
                    'modifiers' => self::saveModifier($pdo, $data, $id),
                };
                audit_log($id ? $resource . '_updated' : $resource . '_created', $resource, $savedId);
                return ['id' => $savedId, 'items' => self::list($resource)];
            });
        } catch (PDOException $exception) {
            if ((string) $exception->getCode() === '23000') {
                throw new DomainException('Já existe um cadastro com esses dados ou uma relação informada é inválida.');
            }
            throw $exception;
        }
    }

    public static function deactivate(string $resource, int $id): array
    {
        self::assertResource($resource);
        $table = match ($resource) {
            'tables' => 'restaurant_tables',
            default => $resource,
        };
        if ($resource === 'users' && $id === (int) (Auth::user()['id'] ?? 0)) {
            throw new DomainException('Você não pode desativar o próprio usuário.');
        }

        Database::transaction(function (PDO $pdo) use ($resource, $table, $id): void {
            if ($resource === 'users') {
                self::assertUserCanLoseAdminAccess($pdo, $id);
            }
            if ($resource === 'tables') {
                $check = $pdo->prepare("SELECT COUNT(*) FROM table_sessions WHERE table_id = :id AND status IN ('open', 'payment_pending')");
                $check->execute(['id' => $id]);
                if ((int) $check->fetchColumn() > 0) {
                    throw new DomainException('Finalize a mesa antes de desativá-la.');
                }
            }
            if ($resource === 'areas') {
                self::assertAreaHasNoTables($pdo, $id);
            }
            $statement = $pdo->prepare("UPDATE {$table} SET active = 0, deleted_at = NOW() WHERE id = :id AND deleted_at IS NULL");
            $statement->execute(['id' => $id]);
            if ($statement->rowCount() !== 1) {
                throw new DomainException('Registro não encontrado.');
            }
            if ($resource === 'tables') {
                $pdo->prepare("UPDATE restaurant_tables SET status = 'inactive' WHERE id = :id")->execute(['id' => $id]);
            }
            audit_log($resource . '_deactivated', $resource, $id);
        });

        return self::list($resource);
    }

    public static function productModifierGroups(int $productId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT modifier_group_id FROM product_modifier_groups WHERE product_id = :product_id ORDER BY sort_order'
        );
        $statement->execute(['product_id' => $productId]);
        return array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    private static function saveArea(PDO $pdo, array $data, ?int $id): int
    {
        $values = [
            'name' => self::requiredString($data, 'name', 100),
            'sort_order' => self::integer($data['sort_order'] ?? 0, 0),
            'active' => self::boolean($data['active'] ?? true),
        ];
        if ($id !== null && !$values['active']) {
            self::assertAreaHasNoTables($pdo, $id);
        }
        return self::upsert($pdo, 'areas', $values, $id);
    }

    private static function saveTable(PDO $pdo, array $data, ?int $id): int
    {
        $areaId = empty($data['area_id']) ? null : request_int($data, 'area_id');
        if ($areaId !== null) {
            $area = $pdo->prepare('SELECT id FROM areas WHERE id = :id AND active = 1 AND deleted_at IS NULL');
            $area->execute(['id' => $areaId]);
            if (!$area->fetchColumn()) {
                throw new DomainException('Selecione um salão ativo.');
            }
        }
        $active = self::boolean($data['active'] ?? true);
        $values = [
            'area_id' => $areaId,
            'number' => self::requiredString($data, 'number', 20),
            'name' => self::optionalString($data['name'] ?? null, 100),
            'status' => $active ? 'available' : 'inactive',
            'active' => $active,
        ];
        if ($id !== null) {
            $current = $pdo->prepare(
                "SELECT t.status,
                        (SELECT COUNT(*) FROM table_sessions ts WHERE ts.table_id = t.id AND ts.status IN ('open', 'payment_pending')) AS active_sessions
                 FROM restaurant_tables t WHERE t.id = :id AND t.deleted_at IS NULL FOR UPDATE"
            );
            $current->execute(['id' => $id]);
            $currentTable = $current->fetch();
            if (!$currentTable) {
                throw new DomainException('Mesa não encontrada.');
            }
            if ((int) $currentTable['active_sessions'] > 0) {
                if (!$active) {
                    throw new DomainException('Finalize o atendimento antes de desativar a mesa.');
                }
                $values['status'] = $currentTable['status'];
            }
        }
        return self::upsert($pdo, 'restaurant_tables', $values, $id);
    }

    private static function assertAreaHasNoTables(PDO $pdo, int $areaId): void
    {
        $tables = $pdo->prepare('SELECT COUNT(*) FROM restaurant_tables WHERE area_id = :id AND deleted_at IS NULL');
        $tables->execute(['id' => $areaId]);
        if ((int) $tables->fetchColumn() > 0) {
            throw new DomainException('Mova ou desative as mesas deste salão antes de desativá-lo.');
        }
    }

    private static function saveCategory(PDO $pdo, array $data, ?int $id): int
    {
        $values = [
            'name' => self::requiredString($data, 'name', 100),
            'icon' => self::optionalString($data['icon'] ?? null, 80),
            'sort_order' => self::integer($data['sort_order'] ?? 0, 0),
            'active' => self::boolean($data['active'] ?? true),
        ];
        return self::upsert($pdo, 'categories', $values, $id);
    }

    private static function saveProduct(PDO $pdo, array $data, ?array $image, ?int $id): int
    {
        $categoryId = request_int($data, 'category_id');
        $category = $pdo->prepare('SELECT id FROM categories WHERE id = :id AND deleted_at IS NULL');
        $category->execute(['id' => $categoryId]);
        if (!$category->fetchColumn()) {
            throw new DomainException('Categoria não encontrada.');
        }

        $imagePath = null;
        if ($id !== null) {
            $currentImage = $pdo->prepare('SELECT image_path FROM products WHERE id = :id AND deleted_at IS NULL');
            $currentImage->execute(['id' => $id]);
            $storedImage = $currentImage->fetchColumn();
            $imagePath = is_string($storedImage) && $storedImage !== '' ? $storedImage : null;
        }
        if ($image && ($image['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $imagePath = self::storeImage($image);
        }
        $values = [
            'category_id' => $categoryId,
            'name' => self::requiredString($data, 'name', 150),
            'description' => self::optionalString($data['description'] ?? null, 5000),
            'price' => decimal_value($data['price'] ?? ''),
            'image_path' => $imagePath,
            'available' => self::boolean($data['available'] ?? true),
            'featured' => self::boolean($data['featured'] ?? false),
            'sort_order' => self::integer($data['sort_order'] ?? 0, 0),
            'active' => self::boolean($data['active'] ?? true),
        ];
        $productId = self::upsert($pdo, 'products', $values, $id);

        if (array_key_exists('modifier_group_ids', $data)) {
            $rawGroups = is_array($data['modifier_group_ids'])
                ? $data['modifier_group_ids']
                : json_decode((string) $data['modifier_group_ids'], true, 16, JSON_THROW_ON_ERROR);
            $groups = array_values(array_unique(array_filter(array_map('intval', (array) $rawGroups), static fn (int $value): bool => $value > 0)));
            $pdo->prepare('DELETE FROM product_modifier_groups WHERE product_id = :product_id')->execute(['product_id' => $productId]);
            $insert = $pdo->prepare('INSERT INTO product_modifier_groups (product_id, modifier_group_id, sort_order) VALUES (:product, :group, :sort)');
            foreach ($groups as $sort => $groupId) {
                $insert->execute(['product' => $productId, 'group' => $groupId, 'sort' => $sort]);
            }
        }
        return $productId;
    }

    private static function saveUser(PDO $pdo, array $data, ?int $id): int
    {
        $role = (string) ($data['role'] ?? '');
        if (!in_array($role, self::ROLES, true)) {
            throw new DomainException('Função de usuário inválida.');
        }
        $login = mb_strtolower(self::requiredString($data, 'login', 80));
        if (!preg_match('/^[a-z0-9._-]{3,80}$/', $login)) {
            throw new DomainException('Login inválido. Use letras, números, ponto, hífen ou sublinhado.');
        }
        $email = self::optionalString($data['email'] ?? null, 190);
        if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new DomainException('E-mail inválido.');
        }
        $values = [
            'name' => self::requiredString($data, 'name', 120),
            'login' => $login,
            'email' => $email,
            'role' => $role,
            'active' => self::boolean($data['active'] ?? true),
        ];
        if ($id !== null && $id === (int) (Auth::user()['id'] ?? 0) && ($role !== 'admin' || !$values['active'])) {
            throw new DomainException('Você não pode remover o próprio acesso de administrador.');
        }
        if ($id !== null && ($role !== 'admin' || !$values['active'])) {
            self::assertUserCanLoseAdminAccess($pdo, $id);
        }
        $password = (string) ($data['password'] ?? '');
        if ($id === null && strlen($password) < 8) {
            throw new DomainException('A senha precisa ter pelo menos 8 caracteres.');
        }
        if ($password !== '') {
            if (strlen($password) < 8) {
                throw new DomainException('A senha precisa ter pelo menos 8 caracteres.');
            }
            $values['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        }
        return self::upsert($pdo, 'users', $values, $id);
    }

    private static function saveModifierGroup(PDO $pdo, array $data, ?int $id): int
    {
        $min = self::integer($data['min_choices'] ?? 0, 0, 99);
        $max = self::integer($data['max_choices'] ?? 1, 1, 99);
        if ($max < $min) {
            throw new DomainException('O máximo de escolhas deve ser maior ou igual ao mínimo.');
        }
        $values = [
            'name' => self::requiredString($data, 'name', 120),
            'min_choices' => $min,
            'max_choices' => $max,
            'required' => self::boolean($data['required'] ?? false),
            'active' => self::boolean($data['active'] ?? true),
            'sort_order' => self::integer($data['sort_order'] ?? 0, 0),
        ];
        return self::upsert($pdo, 'modifier_groups', $values, $id);
    }

    private static function saveModifier(PDO $pdo, array $data, ?int $id): int
    {
        $values = [
            'modifier_group_id' => request_int($data, 'modifier_group_id'),
            'name' => self::requiredString($data, 'name', 120),
            'price_delta' => decimal_value($data['price_delta'] ?? '0'),
            'active' => self::boolean($data['active'] ?? true),
            'sort_order' => self::integer($data['sort_order'] ?? 0, 0),
        ];
        return self::upsert($pdo, 'modifiers', $values, $id);
    }

    private static function upsert(PDO $pdo, string $table, array $values, ?int $id): int
    {
        $columns = array_keys($values);
        if ($id === null) {
            $sql = sprintf(
                'INSERT INTO %s (%s) VALUES (%s)',
                $table,
                implode(', ', $columns),
                implode(', ', array_map(static fn (string $column): string => ':' . $column, $columns))
            );
            $pdo->prepare($sql)->execute($values);
            return (int) $pdo->lastInsertId();
        }
        $assignments = implode(', ', array_map(static fn (string $column): string => $column . ' = :' . $column, $columns));
        $values['id'] = $id;
        $statement = $pdo->prepare("UPDATE {$table} SET {$assignments} WHERE id = :id AND deleted_at IS NULL");
        $statement->execute($values);
        if ($statement->rowCount() === 0) {
            $check = $pdo->prepare("SELECT id FROM {$table} WHERE id = :id AND deleted_at IS NULL");
            $check->execute(['id' => $id]);
            if (!$check->fetchColumn()) {
                throw new DomainException('Registro não encontrado.');
            }
        }
        return $id;
    }

    private static function storeImage(array $file): string
    {
        global $appConfig;
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new DomainException('Não foi possível receber a imagem.');
        }
        if ((int) ($file['size'] ?? 0) <= 0 || (int) $file['size'] > (int) $appConfig['upload_max_bytes']) {
            throw new DomainException('A imagem deve ter no máximo 5 MB.');
        }
        $temporary = (string) ($file['tmp_name'] ?? '');
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($temporary);
        $allowed = $appConfig['allowed_image_types'];
        if (!is_string($mime) || !isset($allowed[$mime]) || @getimagesize($temporary) === false) {
            throw new DomainException('Envie uma imagem JPG, PNG ou WebP válida.');
        }
        $directory = dirname(__DIR__, 2) . '/uploads/products';
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new RuntimeException('Não foi possível preparar a pasta de imagens.');
        }
        $filename = bin2hex(random_bytes(20)) . '.' . $allowed[$mime];
        if (!move_uploaded_file($temporary, $directory . '/' . $filename)) {
            throw new RuntimeException('Não foi possível salvar a imagem.');
        }
        return '/uploads/products/' . $filename;
    }

    private static function requiredString(array $data, string $key, int $max): string
    {
        $value = trim((string) ($data[$key] ?? ''));
        if ($value === '' || mb_strlen($value) > $max) {
            throw new DomainException('Preencha corretamente o campo ' . $key . '.');
        }
        return $value;
    }

    private static function optionalString(mixed $value, int $max): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        if (mb_strlen($value) > $max) {
            throw new DomainException('Um dos textos ultrapassou o limite permitido.');
        }
        return $value;
    }

    private static function boolean(mixed $value): int
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
    }

    private static function integer(mixed $value, int $min, int $max = 100000): int
    {
        $number = filter_var($value, FILTER_VALIDATE_INT);
        if ($number === false || $number < $min || $number > $max) {
            throw new DomainException('Número fora do intervalo permitido.');
        }
        return (int) $number;
    }

    private static function assertResource(string $resource): void
    {
        if (!in_array($resource, self::RESOURCES, true)) {
            throw new DomainException('Recurso administrativo inválido.');
        }
    }

    private static function assertUserCanLoseAdminAccess(PDO $pdo, int $userId): void
    {
        $target = $pdo->prepare("SELECT role, active FROM users WHERE id = :id AND deleted_at IS NULL FOR UPDATE");
        $target->execute(['id' => $userId]);
        $user = $target->fetch();
        if (!$user) {
            throw new DomainException('Usuário não encontrado.');
        }
        if ($user['role'] !== 'admin' || !(bool) $user['active']) {
            return;
        }
        $admins = $pdo->query("SELECT id FROM users WHERE role = 'admin' AND active = 1 AND deleted_at IS NULL FOR UPDATE")->fetchAll(PDO::FETCH_COLUMN);
        if (count($admins) <= 1) {
            throw new DomainException('Mantenha pelo menos um administrador ativo.');
        }
    }
}
