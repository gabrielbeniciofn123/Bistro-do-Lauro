<?php
declare(strict_types=1);

final class OrderService
{
    private const ACTIVE_BOARD_STATUSES = ['new', 'accepted', 'preparing', 'ready'];
    private const TRANSITIONS = [
        'new' => ['accepted', 'preparing'],
        'accepted' => ['preparing'],
        'preparing' => ['ready'],
        'ready' => ['delivered'],
        'delivered' => [],
        'cancelled' => [],
    ];

    public static function create(array $data, array $user): array
    {
        $sessionId = isset($data['table_session_id']) && $data['table_session_id'] !== null
            ? request_int($data, 'table_session_id')
            : null;
        $tableId = isset($data['table_id']) && $data['table_id'] !== null
            ? request_int($data, 'table_id')
            : null;
        if (($sessionId === null) === ($tableId === null)) {
            throw new DomainException('Informe uma mesa disponível ou um atendimento aberto.');
        }
        $key = idempotency_key((string) ($data['idempotency_key'] ?? ''));
        $items = $data['items'] ?? null;
        if (!is_array($items) || $items === [] || count($items) > 100) {
            throw new DomainException('Adicione pelo menos um item válido ao pedido.');
        }
        foreach ($items as $item) {
            if (!is_array($item)) {
                throw new DomainException('Item do pedido inválido.');
            }
        }

        try {
            return Database::transaction(function (PDO $pdo) use ($sessionId, $tableId, $key, $items, $data, $user): array {
                $existing = self::findByIdempotencyKey($pdo, $key);
                if ($existing) {
                    $existing['duplicate'] = true;
                    return $existing;
                }

                $session = self::resolveSessionForOrder($pdo, $sessionId, $tableId, $user);

                $productIds = array_values(array_unique(array_map(
                    static fn (array $item): int => request_int($item, 'product_id'),
                    $items
                )));
                $products = self::loadProducts($pdo, $productIds);
                if (count($products) !== count($productIds)) {
                    throw new DomainException('Um dos produtos não está disponível. Atualize o cardápio.');
                }
                $modifierRules = self::loadModifierRules($pdo, $productIds);

                $normalizedItems = [];
                $orderTotalCents = 0;
                foreach ($items as $item) {
                    if (!is_array($item)) {
                        throw new DomainException('Item do pedido inválido.');
                    }
                    $productId = request_int($item, 'product_id');
                    $quantity = request_int($item, 'quantity');
                    if ($quantity > 99) {
                        throw new DomainException('A quantidade máxima por item é 99.');
                    }
                    $product = $products[$productId] ?? null;
                    if (!$product) {
                        throw new DomainException('Produto indisponível.');
                    }
                    $selectedIds = array_values(array_unique(array_filter(array_map('intval', (array) ($item['modifier_ids'] ?? [])), static fn (int $id): bool => $id > 0)));
                    $selectedModifiers = self::validateModifiers($productId, $selectedIds, $modifierRules);
                    $unitCents = self::toCents((string) $product['price']);
                    $modifiersCents = array_sum(array_map(static fn (array $modifier): int => self::toCents((string) $modifier['price_delta']), $selectedModifiers));
                    $lineCents = ($unitCents + $modifiersCents) * $quantity;
                    $orderTotalCents += $lineCents;
                    $notes = trim((string) ($item['notes'] ?? ''));
                    if (mb_strlen($notes) > 500) {
                        throw new DomainException('A observação de um item é muito longa.');
                    }
                    $normalizedItems[] = [
                        'product' => $product,
                        'quantity' => $quantity,
                        'notes' => $notes === '' ? null : $notes,
                        'modifiers' => $selectedModifiers,
                        'modifiers_total' => self::fromCents($modifiersCents),
                        'line_total' => self::fromCents($lineCents),
                    ];
                }
                if ($orderTotalCents <= 0) {
                    throw new DomainException('O total do pedido precisa ser maior que zero.');
                }

                $globalNotes = trim((string) ($data['notes'] ?? ''));
                if (mb_strlen($globalNotes) > 500) {
                    throw new DomainException('A observação geral é muito longa.');
                }
                $publicId = uuid_v4();
                $total = self::fromCents($orderTotalCents);
                $pdo->prepare(
                    "INSERT INTO orders (public_id, table_session_id, table_id, waiter_id, status, idempotency_key, subtotal, total, notes)
                     VALUES (:public_id, :session_id, :table_id, :waiter_id, 'new', :idempotency_key, :subtotal, :total, :notes)"
                )->execute([
                    'public_id' => $publicId,
                    'session_id' => $session['id'],
                    'table_id' => $session['table_id'],
                    'waiter_id' => $user['id'],
                    'idempotency_key' => $key,
                    'subtotal' => $total,
                    'total' => $total,
                    'notes' => $globalNotes === '' ? null : $globalNotes,
                ]);
                $orderId = (int) $pdo->lastInsertId();

                $itemInsert = $pdo->prepare(
                    "INSERT INTO order_items (order_id, product_id, product_name, unit_price, quantity, notes, modifiers_total, line_total, status)
                     VALUES (:order_id, :product_id, :product_name, :unit_price, :quantity, :notes, :modifiers_total, :line_total, 'active')"
                );
                $modifierInsert = $pdo->prepare(
                    'INSERT INTO order_item_modifiers (order_item_id, modifier_id, modifier_name, price_delta, quantity)
                     VALUES (:order_item_id, :modifier_id, :modifier_name, :price_delta, 1)'
                );
                foreach ($normalizedItems as $normalized) {
                    $itemInsert->execute([
                        'order_id' => $orderId,
                        'product_id' => $normalized['product']['id'],
                        'product_name' => $normalized['product']['name'],
                        'unit_price' => $normalized['product']['price'],
                        'quantity' => $normalized['quantity'],
                        'notes' => $normalized['notes'],
                        'modifiers_total' => $normalized['modifiers_total'],
                        'line_total' => $normalized['line_total'],
                    ]);
                    $orderItemId = (int) $pdo->lastInsertId();
                    foreach ($normalized['modifiers'] as $modifier) {
                        $modifierInsert->execute([
                            'order_item_id' => $orderItemId,
                            'modifier_id' => $modifier['id'],
                            'modifier_name' => $modifier['name'],
                            'price_delta' => $modifier['price_delta'],
                        ]);
                    }
                }

                $pdo->prepare(
                    'UPDATE table_sessions
                     SET subtotal = subtotal + :subtotal_increment,
                         total = total + :total_increment,
                         version = version + 1
                     WHERE id = :id'
                )->execute([
                    'subtotal_increment' => $total,
                    'total_increment' => $total,
                    'id' => $session['id'],
                ]);
                $pdo->prepare("UPDATE restaurant_tables SET status = 'occupied' WHERE id = :id AND status <> 'bill_requested'")
                    ->execute(['id' => $session['table_id']]);
                self::recordStatus($pdo, $orderId, 'new', (int) $user['id']);
                audit_log('order_created', 'order', $orderId, ['table' => $session['table_number'], 'total' => $total]);
                return self::details($orderId, $pdo);
            });
        } catch (PDOException $exception) {
            if ((string) $exception->getCode() === '23000') {
                $existing = self::findByIdempotencyKey(Database::connection(), $key);
                if ($existing) {
                    $existing['duplicate'] = true;
                    return $existing;
                }
            }
            throw $exception;
        }
    }

    public static function poll(array $user, int $sinceEventId = 0): array
    {
        $pdo = Database::connection();
        $lastEventId = (int) $pdo->query('SELECT COALESCE(MAX(id), 0) FROM order_status_history')->fetchColumn();
        $counts = self::statusCounts($pdo);
        if ($sinceEventId > 0 && $lastEventId <= $sinceEventId) {
            return ['changed' => false, 'last_event_id' => $lastEventId, 'orders' => [], 'counts' => $counts, 'server_time' => date(DATE_ATOM)];
        }

        $params = [];
        $where = "o.status IN ('new', 'accepted', 'preparing', 'ready')";
        if ($user['role'] === 'kitchen') {
            $where = "o.status IN ('new', 'accepted', 'preparing')";
        } elseif ($user['role'] === 'waiter') {
            $where = "o.waiter_id = :waiter_id AND ts.status IN ('open', 'payment_pending') AND o.status <> 'cancelled'";
            $params['waiter_id'] = $user['id'];
        }
        $statement = $pdo->prepare(
            "SELECT o.id, o.public_id, o.table_session_id, o.status, o.subtotal, o.total, o.notes,
                    o.created_at, o.updated_at, t.id AS table_id, t.number AS table_number,
                    a.name AS area_name, u.name AS waiter_name
             FROM orders o
             INNER JOIN table_sessions ts ON ts.id = o.table_session_id
             INNER JOIN restaurant_tables t ON t.id = o.table_id
             LEFT JOIN areas a ON a.id = t.area_id
             INNER JOIN users u ON u.id = o.waiter_id
             WHERE {$where}
             ORDER BY o.created_at"
        );
        $statement->execute($params);
        $orders = $statement->fetchAll();
        foreach ($orders as &$order) {
            $order['id'] = (int) $order['id'];
            $order['table_session_id'] = (int) $order['table_session_id'];
            $order['table_id'] = (int) $order['table_id'];
            $order['items'] = self::items((int) $order['id'], $pdo);
        }
        unset($order);
        return ['changed' => true, 'last_event_id' => $lastEventId, 'orders' => $orders, 'counts' => $counts, 'server_time' => date(DATE_ATOM)];
    }

    public static function changeStatus(int $orderId, string $newStatus, array $user): array
    {
        return Database::transaction(function (PDO $pdo) use ($orderId, $newStatus, $user): array {
            $statement = $pdo->prepare('SELECT id, status, table_id, table_session_id FROM orders WHERE id = :id FOR UPDATE');
            $statement->execute(['id' => $orderId]);
            $order = $statement->fetch();
            if (!$order) {
                throw new DomainException('Pedido não encontrado.');
            }
            $current = (string) $order['status'];
            if (!in_array($newStatus, self::TRANSITIONS[$current] ?? [], true)) {
                throw new DomainException('Essa mudança de status não é permitida.');
            }
            if ($user['role'] === 'kitchen' && !in_array($newStatus, ['preparing', 'ready'], true)) {
                throw new DomainException('A cozinha não pode realizar essa alteração.');
            }
            if ($user['role'] === 'waiter') {
                throw new DomainException('O garçom não pode alterar o preparo do pedido.');
            }
            $timestampColumn = match ($newStatus) {
                'accepted' => 'accepted_at', 'preparing' => 'preparing_at', 'ready' => 'ready_at', 'delivered' => 'delivered_at',
            };
            $pdo->prepare("UPDATE orders SET status = :status, {$timestampColumn} = NOW() WHERE id = :id")
                ->execute(['status' => $newStatus, 'id' => $orderId]);
            if ($newStatus !== 'new') {
                $pdo->prepare("UPDATE restaurant_tables SET status = 'occupied' WHERE id = :id AND status = 'waiting_order'")
                    ->execute(['id' => $order['table_id']]);
            }
            self::recordStatus($pdo, $orderId, $newStatus, (int) $user['id']);
            audit_log('order_status_changed', 'order', $orderId, ['from' => $current, 'to' => $newStatus]);
            return self::details($orderId, $pdo);
        });
    }

    public static function cancel(int $orderId, string $reason, array $user): array
    {
        $reason = trim($reason);
        if ($reason === '' || mb_strlen($reason) > 500) {
            throw new DomainException('Informe um motivo de cancelamento.');
        }
        return Database::transaction(function (PDO $pdo) use ($orderId, $reason, $user): array {
            $statement = $pdo->prepare(
                'SELECT o.*, ts.status AS session_status FROM orders o
                 INNER JOIN table_sessions ts ON ts.id = o.table_session_id
                 WHERE o.id = :id FOR UPDATE'
            );
            $statement->execute(['id' => $orderId]);
            $order = $statement->fetch();
            if (!$order || $order['status'] === 'cancelled') {
                throw new DomainException('Pedido não encontrado ou já cancelado.');
            }
            if ($order['session_status'] === 'closed') {
                throw new DomainException('Uma venda fechada não pode ser cancelada por esta tela.');
            }
            $details = self::details($orderId, $pdo);
            $pdo->prepare("UPDATE orders SET status = 'cancelled', cancelled_at = NOW() WHERE id = :id")->execute(['id' => $orderId]);
            $pdo->prepare(
                'INSERT INTO cancellations (order_id, cancelled_by, reason, amount, snapshot_json)
                 VALUES (:order_id, :cancelled_by, :reason, :amount, :snapshot)'
            )->execute([
                'order_id' => $orderId,
                'cancelled_by' => $user['id'],
                'reason' => $reason,
                'amount' => $order['total'],
                'snapshot' => json_encode($details, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            ]);
            $pdo->prepare(
                'UPDATE table_sessions
                 SET subtotal = GREATEST(0, subtotal - :subtotal_decrement),
                     total = GREATEST(0, total - :total_decrement),
                     version = version + 1
                 WHERE id = :id'
            )->execute([
                'subtotal_decrement' => $order['total'],
                'total_decrement' => $order['total'],
                'id' => $order['table_session_id'],
            ]);
            self::refreshWaitingTableStatus($pdo, (int) $order['table_id']);
            self::recordStatus($pdo, $orderId, 'cancelled', (int) $user['id'], $reason);
            audit_log('order_cancelled', 'order', $orderId, ['reason' => $reason, 'amount' => $order['total']]);
            return self::details($orderId, $pdo);
        });
    }

    public static function cancelItem(int $itemId, string $reason, array $user): array
    {
        $reason = trim($reason);
        if ($reason === '' || mb_strlen($reason) > 500) {
            throw new DomainException('Informe um motivo de cancelamento.');
        }
        return Database::transaction(function (PDO $pdo) use ($itemId, $reason, $user): array {
            $statement = $pdo->prepare(
                'SELECT oi.*, o.table_id, o.table_session_id, o.status AS order_status, ts.status AS session_status
                 FROM order_items oi
                 INNER JOIN orders o ON o.id = oi.order_id
                 INNER JOIN table_sessions ts ON ts.id = o.table_session_id
                 WHERE oi.id = :id FOR UPDATE'
            );
            $statement->execute(['id' => $itemId]);
            $item = $statement->fetch();
            if (!$item || $item['status'] === 'cancelled') {
                throw new DomainException('Item não encontrado ou já cancelado.');
            }
            if ($item['session_status'] === 'closed' || $item['order_status'] === 'cancelled') {
                throw new DomainException('Este item não pode mais ser cancelado.');
            }
            $snapshot = ['item' => $item, 'modifiers' => []];
            $modifiers = $pdo->prepare('SELECT * FROM order_item_modifiers WHERE order_item_id = :item_id');
            $modifiers->execute(['item_id' => $itemId]);
            $snapshot['modifiers'] = $modifiers->fetchAll();
            $pdo->prepare("UPDATE order_items SET status = 'cancelled' WHERE id = :id")->execute(['id' => $itemId]);
            $pdo->prepare(
                'UPDATE orders
                 SET subtotal = GREATEST(0, subtotal - :subtotal_decrement),
                     total = GREATEST(0, total - :total_decrement)
                 WHERE id = :id'
            )->execute([
                'subtotal_decrement' => $item['line_total'],
                'total_decrement' => $item['line_total'],
                'id' => $item['order_id'],
            ]);
            $pdo->prepare(
                'UPDATE table_sessions
                 SET subtotal = GREATEST(0, subtotal - :subtotal_decrement),
                     total = GREATEST(0, total - :total_decrement),
                     version = version + 1
                 WHERE id = :id'
            )->execute([
                'subtotal_decrement' => $item['line_total'],
                'total_decrement' => $item['line_total'],
                'id' => $item['table_session_id'],
            ]);
            $pdo->prepare(
                'INSERT INTO cancellations (order_id, order_item_id, cancelled_by, reason, amount, snapshot_json)
                 VALUES (:order_id, :item_id, :user_id, :reason, :amount, :snapshot)'
            )->execute([
                'order_id' => $item['order_id'], 'item_id' => $itemId, 'user_id' => $user['id'],
                'reason' => $reason, 'amount' => $item['line_total'],
                'snapshot' => json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            ]);
            $activeItems = $pdo->prepare("SELECT COUNT(*) FROM order_items WHERE order_id = :order_id AND status = 'active'");
            $activeItems->execute(['order_id' => $item['order_id']]);
            $historyStatus = (string) $item['order_status'];
            if ((int) $activeItems->fetchColumn() === 0) {
                $historyStatus = 'cancelled';
                $pdo->prepare("UPDATE orders SET status = 'cancelled', cancelled_at = NOW() WHERE id = :id")
                    ->execute(['id' => $item['order_id']]);
            }
            self::refreshWaitingTableStatus($pdo, (int) $item['table_id']);
            self::recordStatus($pdo, (int) $item['order_id'], $historyStatus, (int) $user['id'], 'Item cancelado: ' . $reason);
            audit_log('order_item_cancelled', 'order_item', $itemId, ['reason' => $reason, 'amount' => $item['line_total']]);
            return self::details((int) $item['order_id'], $pdo);
        });
    }

    public static function details(int $orderId, ?PDO $pdo = null): array
    {
        $pdo ??= Database::connection();
        $statement = $pdo->prepare(
            "SELECT o.*, t.number AS table_number, a.name AS area_name, u.name AS waiter_name
             FROM orders o INNER JOIN restaurant_tables t ON t.id = o.table_id
             LEFT JOIN areas a ON a.id = t.area_id INNER JOIN users u ON u.id = o.waiter_id
             WHERE o.id = :id LIMIT 1"
        );
        $statement->execute(['id' => $orderId]);
        $order = $statement->fetch();
        if (!$order) {
            throw new DomainException('Pedido não encontrado.');
        }
        $order['id'] = (int) $order['id'];
        $order['items'] = self::items($orderId, $pdo);
        return $order;
    }

    public static function items(int $orderId, ?PDO $pdo = null): array
    {
        $pdo ??= Database::connection();
        $statement = $pdo->prepare(
            'SELECT id, product_id, product_name, unit_price, quantity, notes, modifiers_total, line_total, status
             FROM order_items WHERE order_id = :order_id ORDER BY id'
        );
        $statement->execute(['order_id' => $orderId]);
        $items = $statement->fetchAll();
        $modifierStatement = $pdo->prepare(
            'SELECT id, modifier_id, modifier_name, price_delta, quantity FROM order_item_modifiers WHERE order_item_id = :item_id ORDER BY id'
        );
        foreach ($items as &$item) {
            $item['id'] = (int) $item['id'];
            $item['product_id'] = $item['product_id'] === null ? null : (int) $item['product_id'];
            $item['quantity'] = (int) $item['quantity'];
            $modifierStatement->execute(['item_id' => $item['id']]);
            $item['modifiers'] = $modifierStatement->fetchAll();
        }
        unset($item);
        return $items;
    }

    private static function findByIdempotencyKey(PDO $pdo, string $key): ?array
    {
        $statement = $pdo->prepare('SELECT id FROM orders WHERE idempotency_key = :key LIMIT 1');
        $statement->execute(['key' => $key]);
        $id = $statement->fetchColumn();
        return $id ? self::details((int) $id, $pdo) : null;
    }

    private static function loadProducts(PDO $pdo, array $ids): array
    {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $statement = $pdo->prepare(
            "SELECT id, name, price FROM products WHERE id IN ({$placeholders}) AND active = 1 AND available = 1 AND deleted_at IS NULL"
        );
        $statement->execute($ids);
        $products = [];
        foreach ($statement->fetchAll() as $product) {
            $product['id'] = (int) $product['id'];
            $products[$product['id']] = $product;
        }
        return $products;
    }

    private static function loadModifierRules(PDO $pdo, array $productIds): array
    {
        $placeholders = implode(',', array_fill(0, count($productIds), '?'));
        $statement = $pdo->prepare(
            "SELECT pmg.product_id, mg.id AS group_id, mg.min_choices, mg.max_choices, mg.required,
                    m.id, m.name, m.price_delta
             FROM product_modifier_groups pmg
             INNER JOIN modifier_groups mg ON mg.id = pmg.modifier_group_id AND mg.active = 1 AND mg.deleted_at IS NULL
             LEFT JOIN modifiers m ON m.modifier_group_id = mg.id AND m.active = 1 AND m.deleted_at IS NULL
             WHERE pmg.product_id IN ({$placeholders})"
        );
        $statement->execute($productIds);
        $rules = [];
        foreach ($statement->fetchAll() as $row) {
            $productId = (int) $row['product_id'];
            $groupId = (int) $row['group_id'];
            $rules[$productId][$groupId] ??= [
                'min' => (int) $row['min_choices'],
                'max' => (int) $row['max_choices'],
                'required' => (bool) $row['required'],
                'options' => [],
            ];
            if ($row['id'] !== null) {
                $row['id'] = (int) $row['id'];
                $rules[$productId][$groupId]['options'][$row['id']] = $row;
            }
        }
        return $rules;
    }

    private static function validateModifiers(int $productId, array $selectedIds, array $rules): array
    {
        $productRules = $rules[$productId] ?? [];
        $selected = [];
        $knownIds = [];
        foreach ($productRules as $rule) {
            $groupSelected = array_values(array_intersect($selectedIds, array_keys($rule['options'])));
            $count = count($groupSelected);
            $minimum = $rule['required'] ? max(1, $rule['min']) : $rule['min'];
            if ($count < $minimum || $count > $rule['max']) {
                throw new DomainException('Revise as escolhas obrigatórias de complementos.');
            }
            foreach ($groupSelected as $id) {
                $selected[] = $rule['options'][$id];
                $knownIds[] = $id;
            }
        }
        if (array_diff($selectedIds, $knownIds) !== []) {
            throw new DomainException('Um complemento selecionado não pertence ao produto.');
        }
        return $selected;
    }

    private static function resolveSessionForOrder(PDO $pdo, ?int $sessionId, ?int $tableId, array $user): array
    {
        if ($sessionId !== null) {
            $statement = $pdo->prepare(
                "SELECT ts.id, ts.table_id, ts.status, t.number AS table_number
                 FROM table_sessions ts
                 INNER JOIN restaurant_tables t ON t.id = ts.table_id
                 WHERE ts.id = :id FOR UPDATE"
            );
            $statement->execute(['id' => $sessionId]);
            $session = $statement->fetch();
            if (!$session || $session['status'] !== 'open') {
                throw new DomainException('A mesa não está aberta para receber pedidos.');
            }
            return $session;
        }

        $settings = $pdo->query('SELECT restaurant_open FROM restaurant_settings WHERE id = 1 FOR UPDATE')->fetch();
        if (!$settings || !(bool) $settings['restaurant_open']) {
            throw new DomainException('O restaurante está fechado para novos atendimentos.');
        }

        $statement = $pdo->prepare(
            'SELECT id, number, status, active FROM restaurant_tables WHERE id = :id AND deleted_at IS NULL FOR UPDATE'
        );
        $statement->execute(['id' => $tableId]);
        $table = $statement->fetch();
        if (!$table || !(bool) $table['active']) {
            throw new DomainException('Mesa não encontrada ou inativa.');
        }

        $existing = $pdo->prepare(
            "SELECT ts.id, ts.status, ts.subtotal,
                    (SELECT COUNT(*) FROM orders o WHERE o.table_session_id = ts.id AND o.status <> 'cancelled') AS order_count
             FROM table_sessions ts
             WHERE ts.table_id = :table_id AND ts.status IN ('open', 'payment_pending')
             LIMIT 1 FOR UPDATE"
        );
        $existing->execute(['table_id' => $tableId]);
        $existingSession = $existing->fetch();
        if ($existingSession) {
            $isEmptyLegacySession = $existingSession['status'] === 'open'
                && (int) $existingSession['order_count'] === 0
                && (float) $existingSession['subtotal'] === 0.0;
            if (!$isEmptyLegacySession) {
                throw new DomainException('Esta mesa já possui um atendimento aberto. Atualize as mesas antes de enviar.');
            }
            $pdo->prepare(
                "UPDATE table_sessions
                 SET status = 'cancelled', closed_at = NOW(), version = version + 1
                 WHERE id = :id"
            )->execute(['id' => $existingSession['id']]);
            audit_log('empty_table_session_cancelled', 'table_session', (int) $existingSession['id'], [
                'table_id' => $tableId,
                'reason' => 'Substituída pelo primeiro pedido enviado',
            ]);
            $table['status'] = 'available';
        }
        if ($table['status'] !== 'available') {
            throw new DomainException('Esta mesa foi ocupada enquanto o pedido era montado. Atualize as mesas e confira antes de enviar.');
        }

        $publicId = uuid_v4();
        $pdo->prepare(
            "INSERT INTO table_sessions (public_id, table_id, opened_by, status, opened_at)
             VALUES (:public_id, :table_id, :opened_by, 'open', NOW())"
        )->execute([
            'public_id' => $publicId,
            'table_id' => $tableId,
            'opened_by' => $user['id'],
        ]);
        $newSessionId = (int) $pdo->lastInsertId();
        audit_log('table_opened', 'table_session', $newSessionId, [
            'table_id' => $tableId,
            'table_number' => $table['number'],
            'opened_with_first_order' => true,
        ]);
        return [
            'id' => $newSessionId,
            'table_id' => (int) $tableId,
            'status' => 'open',
            'table_number' => $table['number'],
        ];
    }

    private static function recordStatus(PDO $pdo, int $orderId, string $status, int $userId, ?string $notes = null): void
    {
        $pdo->prepare(
            'INSERT INTO order_status_history (order_id, status, changed_by, notes) VALUES (:order_id, :status, :changed_by, :notes)'
        )->execute(['order_id' => $orderId, 'status' => $status, 'changed_by' => $userId, 'notes' => $notes]);
    }

    private static function statusCounts(PDO $pdo): array
    {
        $counts = ['new' => 0, 'accepted' => 0, 'preparing' => 0, 'ready' => 0];
        foreach ($pdo->query("SELECT status, COUNT(*) AS total FROM orders WHERE status IN ('new','accepted','preparing','ready') GROUP BY status")->fetchAll() as $row) {
            $counts[$row['status']] = (int) $row['total'];
        }
        return $counts;
    }

    private static function refreshWaitingTableStatus(PDO $pdo, int $tableId): void
    {
        $statement = $pdo->prepare(
            "UPDATE restaurant_tables AS t SET status = 'occupied'
             WHERE t.id = :table_id AND t.status = 'waiting_order'
               AND NOT EXISTS (SELECT 1 FROM orders o WHERE o.table_id = t.id AND o.status = 'new')"
        );
        $statement->execute(['table_id' => $tableId]);
    }

    private static function toCents(string $value): int
    {
        $value = trim($value);
        if (!preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', $value, $matches)) {
            throw new RuntimeException('Valor monetário inválido no banco.');
        }
        return ((int) $matches[1] * 100) + (int) str_pad($matches[2] ?? '0', 2, '0');
    }

    private static function fromCents(int $cents): string
    {
        return sprintf('%d.%02d', intdiv($cents, 100), $cents % 100);
    }
}
