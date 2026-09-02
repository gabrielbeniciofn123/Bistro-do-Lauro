<?php
declare(strict_types=1);

final class DashboardService
{
    public static function summary(): array
    {
        $pdo = Database::connection();
        $metrics = $pdo->query(
            "SELECT
                COALESCE(SUM(CASE WHEN ts.status = 'closed' AND DATE(ts.closed_at) = CURDATE() THEN ts.total ELSE 0 END), 0) AS sales_today,
                COUNT(DISTINCT CASE WHEN ts.status = 'closed' AND DATE(ts.closed_at) = CURDATE() THEN ts.id END) AS closed_tabs_today,
                COUNT(DISTINCT CASE WHEN ts.status IN ('open', 'payment_pending') THEN ts.id END) AS open_tabs
             FROM table_sessions ts"
        )->fetch() ?: [];

        $ordersToday = (int) $pdo->query(
            "SELECT COUNT(*) FROM orders WHERE DATE(created_at) = CURDATE() AND status <> 'cancelled'"
        )->fetchColumn();
        $availableTables = (int) $pdo->query(
            "SELECT COUNT(*) FROM restaurant_tables WHERE active = 1 AND deleted_at IS NULL AND status = 'available'"
        )->fetchColumn();
        $preparing = (int) $pdo->query(
            "SELECT COUNT(*) FROM orders WHERE status IN ('accepted', 'preparing')"
        )->fetchColumn();
        $ready = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'ready'")->fetchColumn();

        $salesToday = (string) ($metrics['sales_today'] ?? '0.00');
        $closedTabs = (int) ($metrics['closed_tabs_today'] ?? 0);
        $ticket = $closedTabs > 0 ? number_format((float) $salesToday / $closedTabs, 2, '.', '') : '0.00';

        $topProducts = $pdo->query(
            "SELECT oi.product_name AS name, SUM(oi.quantity) AS quantity, SUM(oi.line_total) AS total
             FROM order_items oi
             INNER JOIN orders o ON o.id = oi.order_id
             WHERE oi.status = 'active' AND o.status <> 'cancelled' AND DATE(o.created_at) = CURDATE()
             GROUP BY oi.product_name
             ORDER BY quantity DESC, total DESC
             LIMIT 8"
        )->fetchAll();

        return [
            'sales_today' => $salesToday,
            'orders_today' => $ordersToday,
            'average_ticket' => $ticket,
            'open_tabs' => (int) ($metrics['open_tabs'] ?? 0),
            'available_tables' => $availableTables,
            'preparing_orders' => $preparing,
            'ready_orders' => $ready,
            'top_products' => $topProducts,
        ];
    }
}
