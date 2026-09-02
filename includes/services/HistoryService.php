<?php
declare(strict_types=1);

final class HistoryService
{
    private const METHODS = ['cash', 'pix', 'debit_card', 'credit_card', 'other'];

    public static function list(array $filters): array
    {
        $from = self::date($filters['from'] ?? date('Y-m-d'));
        $to = self::date($filters['to'] ?? date('Y-m-d'));
        if ($from > $to) {
            throw new DomainException('O período informado é inválido.');
        }
        $params = ['from' => $from . ' 00:00:00', 'to' => $to . ' 23:59:59'];
        $where = ["ts.status = 'closed'", 'ts.closed_at BETWEEN :from AND :to'];
        if (!empty($filters['table_id'])) {
            $where[] = 'ts.table_id = :table_id';
            $params['table_id'] = (int) $filters['table_id'];
        }
        if (!empty($filters['waiter_id'])) {
            $where[] = 'ts.opened_by = :waiter_id';
            $params['waiter_id'] = (int) $filters['waiter_id'];
        }
        if (!empty($filters['payment_method'])) {
            $method = (string) $filters['payment_method'];
            if (!in_array($method, self::METHODS, true)) {
                throw new DomainException('Forma de pagamento inválida.');
            }
            $where[] = 'EXISTS (SELECT 1 FROM payments filter_payment WHERE filter_payment.table_session_id = ts.id AND filter_payment.payment_method = :payment_method)';
            $params['payment_method'] = $method;
        }

        $statement = Database::connection()->prepare(
            "SELECT ts.id, ts.public_id, ts.opened_at, ts.closed_at, ts.subtotal, ts.service_fee,
                    ts.discount, ts.surcharge, ts.total, t.number AS table_number, a.name AS area_name,
                    opener.name AS waiter_name, closer.name AS cashier_name,
                    GROUP_CONCAT(DISTINCT p.payment_method ORDER BY p.id SEPARATOR ',') AS payment_methods
             FROM table_sessions ts
             INNER JOIN restaurant_tables t ON t.id = ts.table_id
             LEFT JOIN areas a ON a.id = t.area_id
             INNER JOIN users opener ON opener.id = ts.opened_by
             LEFT JOIN users closer ON closer.id = ts.closed_by
             LEFT JOIN payments p ON p.table_session_id = ts.id
             WHERE " . implode(' AND ', $where) . "
             GROUP BY ts.id
             ORDER BY ts.closed_at DESC
             LIMIT 500"
        );
        $statement->execute($params);
        $items = $statement->fetchAll();
        foreach ($items as &$item) {
            $item['id'] = (int) $item['id'];
            $item['payment_methods'] = $item['payment_methods'] ? explode(',', $item['payment_methods']) : [];
        }
        unset($item);
        return ['items' => $items, 'from' => $from, 'to' => $to];
    }

    public static function details(int $sessionId): array
    {
        return TableService::detailsBySession($sessionId);
    }

    private static function date(mixed $value): string
    {
        $value = (string) $value;
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value) {
            throw new DomainException('Data inválida.');
        }
        return $value;
    }
}
