<?php
declare(strict_types=1);

final class TableService
{
    public static function list(): array
    {
        $rows = Database::connection()->query(
            "SELECT t.id, t.number, t.name, t.status, t.area_id, a.name AS area_name,
                    ts.id AS session_id, ts.public_id AS session_public_id, ts.status AS session_status, ts.opened_at,
                    ts.opened_by, u.name AS waiter_name, ts.subtotal, ts.total,
                    (SELECT COUNT(*) FROM orders o WHERE o.table_session_id = ts.id AND o.status <> 'cancelled') AS order_count
             FROM restaurant_tables t
             LEFT JOIN areas a ON a.id = t.area_id
             LEFT JOIN table_sessions ts ON ts.table_id = t.id AND ts.status IN ('open', 'payment_pending')
             LEFT JOIN users u ON u.id = ts.opened_by
             WHERE t.active = 1 AND t.deleted_at IS NULL
             ORDER BY a.sort_order, CAST(t.number AS UNSIGNED), t.number"
        )->fetchAll();
        foreach ($rows as &$row) {
            $row['id'] = (int) $row['id'];
            $row['area_id'] = $row['area_id'] === null ? null : (int) $row['area_id'];
            $row['session_id'] = $row['session_id'] === null ? null : (int) $row['session_id'];
            $row['opened_by'] = $row['opened_by'] === null ? null : (int) $row['opened_by'];
            $row['order_count'] = (int) $row['order_count'];
            if ($row['session_status'] === 'open' && $row['order_count'] === 0 && (float) $row['subtotal'] === 0.0) {
                $row['status'] = 'available';
                $row['session_id'] = null;
                $row['session_public_id'] = null;
                $row['session_status'] = null;
                $row['opened_at'] = null;
                $row['opened_by'] = null;
                $row['waiter_name'] = null;
                $row['subtotal'] = null;
                $row['total'] = null;
            }
        }
        unset($row);
        return $rows;
    }

    public static function open(int $tableId, int $userId): array
    {
        return Database::transaction(function (PDO $pdo) use ($tableId, $userId): array {
            $settings = $pdo->query('SELECT restaurant_open FROM restaurant_settings WHERE id = 1 FOR UPDATE')->fetch();
            if (!$settings || !(bool) $settings['restaurant_open']) {
                throw new DomainException('O restaurante está fechado para novos atendimentos.');
            }
            $statement = $pdo->prepare('SELECT id, number, status, active FROM restaurant_tables WHERE id = :id AND deleted_at IS NULL FOR UPDATE');
            $statement->execute(['id' => $tableId]);
            $table = $statement->fetch();
            if (!$table || !(bool) $table['active']) {
                throw new DomainException('Mesa não encontrada ou inativa.');
            }

            $existing = $pdo->prepare("SELECT id FROM table_sessions WHERE table_id = :table_id AND status IN ('open', 'payment_pending') LIMIT 1 FOR UPDATE");
            $existing->execute(['table_id' => $tableId]);
            $existingId = $existing->fetchColumn();
            if ($existingId) {
                return self::detailsBySession((int) $existingId, $pdo);
            }
            if ($table['status'] !== 'available') {
                throw new DomainException('A mesa não está disponível para abertura.');
            }

            $publicId = uuid_v4();
            $pdo->prepare(
                "INSERT INTO table_sessions (public_id, table_id, opened_by, status, opened_at)
                 VALUES (:public_id, :table_id, :opened_by, 'open', NOW())"
            )->execute(['public_id' => $publicId, 'table_id' => $tableId, 'opened_by' => $userId]);
            $sessionId = (int) $pdo->lastInsertId();
            $pdo->prepare("UPDATE restaurant_tables SET status = 'occupied' WHERE id = :id")->execute(['id' => $tableId]);
            audit_log('table_opened', 'table_session', $sessionId, ['table_id' => $tableId, 'table_number' => $table['number']]);
            return self::detailsBySession($sessionId, $pdo);
        });
    }

    public static function details(int $tableId): array
    {
        $statement = Database::connection()->prepare(
            "SELECT id FROM table_sessions WHERE table_id = :table_id AND status IN ('open', 'payment_pending') ORDER BY id DESC LIMIT 1"
        );
        $statement->execute(['table_id' => $tableId]);
        $sessionId = $statement->fetchColumn();
        if (!$sessionId) {
            throw new DomainException('Esta mesa não possui atendimento aberto.');
        }
        return self::detailsBySession((int) $sessionId);
    }

    public static function detailsBySession(int $sessionId, ?PDO $pdo = null): array
    {
        $pdo ??= Database::connection();
        $statement = $pdo->prepare(
            "SELECT ts.*, t.number AS table_number, t.name AS table_name, t.status AS table_status,
                    a.name AS area_name, opener.name AS waiter_name, closer.name AS closed_by_name
             FROM table_sessions ts
             INNER JOIN restaurant_tables t ON t.id = ts.table_id
             LEFT JOIN areas a ON a.id = t.area_id
             INNER JOIN users opener ON opener.id = ts.opened_by
             LEFT JOIN users closer ON closer.id = ts.closed_by
             WHERE ts.id = :id LIMIT 1"
        );
        $statement->execute(['id' => $sessionId]);
        $session = $statement->fetch();
        if (!$session) {
            throw new DomainException('Atendimento não encontrado.');
        }

        $orderStatement = $pdo->prepare(
            "SELECT o.id, o.public_id, o.status, o.subtotal, o.total, o.notes, o.created_at, o.updated_at,
                    u.name AS waiter_name
             FROM orders o INNER JOIN users u ON u.id = o.waiter_id
             WHERE o.table_session_id = :session_id ORDER BY o.id"
        );
        $orderStatement->execute(['session_id' => $sessionId]);
        $orders = $orderStatement->fetchAll();
        foreach ($orders as &$order) {
            $order['id'] = (int) $order['id'];
            $order['items'] = OrderService::items((int) $order['id'], $pdo);
        }
        unset($order);

        $paymentStatement = $pdo->prepare(
            'SELECT id, payment_method, amount, reference, created_at FROM payments WHERE table_session_id = :session_id ORDER BY id'
        );
        $paymentStatement->execute(['session_id' => $sessionId]);

        $session['id'] = (int) $session['id'];
        $session['table_id'] = (int) $session['table_id'];
        $session['opened_by'] = (int) $session['opened_by'];
        $session['closed_by'] = $session['closed_by'] === null ? null : (int) $session['closed_by'];
        $session['orders'] = $orders;
        $session['payments'] = $paymentStatement->fetchAll();
        return $session;
    }

    public static function requestBill(int $tableId): array
    {
        return Database::transaction(function (PDO $pdo) use ($tableId): array {
            $table = $pdo->prepare('SELECT id, status FROM restaurant_tables WHERE id = :id FOR UPDATE');
            $table->execute(['id' => $tableId]);
            $row = $table->fetch();
            if (!$row || !in_array($row['status'], ['occupied', 'waiting_order', 'bill_requested'], true)) {
                throw new DomainException('A mesa não possui atendimento para solicitar conta.');
            }
            $session = $pdo->prepare("SELECT id, status FROM table_sessions WHERE table_id = :table_id AND status IN ('open', 'payment_pending') LIMIT 1 FOR UPDATE");
            $session->execute(['table_id' => $tableId]);
            $sessionRow = $session->fetch();
            if (!$sessionRow) {
                throw new DomainException('Atendimento aberto não encontrado.');
            }
            $sessionId = (int) $sessionRow['id'];
            if ($sessionRow['status'] === 'payment_pending') {
                return self::detailsBySession($sessionId, $pdo);
            }
            $pdo->prepare("UPDATE restaurant_tables SET status = 'bill_requested' WHERE id = :id")->execute(['id' => $tableId]);
            $pdo->prepare("UPDATE table_sessions SET status = 'payment_pending', version = version + 1 WHERE id = :id")->execute(['id' => $sessionId]);
            audit_log('bill_requested', 'table_session', $sessionId);
            return self::detailsBySession($sessionId, $pdo);
        });
    }
}
