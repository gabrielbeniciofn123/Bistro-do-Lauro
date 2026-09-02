<?php
declare(strict_types=1);

final class PaymentService
{
    private const METHODS = ['cash', 'pix', 'debit_card', 'credit_card', 'other'];

    public static function closeTable(array $data, array $user): array
    {
        $sessionId = request_int($data, 'table_session_id');
        $baseKey = idempotency_key((string) ($data['idempotency_key'] ?? ''));
        $payments = $data['payments'] ?? null;
        if (!is_array($payments)) {
            throw new DomainException('Informe as formas de pagamento.');
        }

        return Database::transaction(function (PDO $pdo) use ($sessionId, $baseKey, $payments, $data, $user): array {
            $sessionStatement = $pdo->prepare(
                "SELECT ts.*, t.id AS locked_table_id FROM table_sessions ts
                 INNER JOIN restaurant_tables t ON t.id = ts.table_id
                 WHERE ts.id = :id FOR UPDATE"
            );
            $sessionStatement->execute(['id' => $sessionId]);
            $session = $sessionStatement->fetch();
            if (!$session) {
                throw new DomainException('Atendimento não encontrado.');
            }

            if ($session['status'] === 'closed' && is_string($session['closing_idempotency_key']) && hash_equals($session['closing_idempotency_key'], $baseKey)) {
                $result = TableService::detailsBySession($sessionId, $pdo);
                $result['duplicate'] = true;
                return $result;
            }
            if (!in_array($session['status'], ['open', 'payment_pending'], true)) {
                throw new DomainException('Esta mesa não pode ser finalizada novamente.');
            }

            $pendingOrders = $pdo->prepare(
                "SELECT COUNT(*) FROM orders WHERE table_session_id = :session_id AND status IN ('new', 'accepted', 'preparing', 'ready')"
            );
            $pendingOrders->execute(['session_id' => $sessionId]);
            if ((int) $pendingOrders->fetchColumn() > 0) {
                throw new DomainException('Finalize ou cancele todos os pedidos em produção antes de fechar a mesa.');
            }

            $subtotalStatement = $pdo->prepare(
                "SELECT COALESCE(SUM(total), 0) FROM orders WHERE table_session_id = :session_id AND status <> 'cancelled'"
            );
            $subtotalStatement->execute(['session_id' => $sessionId]);
            $subtotalCents = self::toCents((string) $subtotalStatement->fetchColumn());
            $settings = $pdo->query('SELECT service_fee_enabled, service_fee_percent FROM restaurant_settings WHERE id = 1 FOR UPDATE')->fetch();
            $applyServiceFee = !empty($data['apply_service_fee']) && (bool) ($settings['service_fee_enabled'] ?? false);
            $percentBasisPoints = self::percentToBasisPoints((string) ($settings['service_fee_percent'] ?? '0.00'));
            $serviceFeeCents = $applyServiceFee ? (int) round($subtotalCents * $percentBasisPoints / 10000) : 0;
            $discountCents = self::toCents(decimal_value($data['discount'] ?? '0'));
            $surchargeCents = self::toCents(decimal_value($data['surcharge'] ?? '0'));
            $totalCents = $subtotalCents + $serviceFeeCents + $surchargeCents - $discountCents;
            if ($totalCents < 0) {
                throw new DomainException('O desconto não pode ser maior que o valor da conta.');
            }

            $normalizedPayments = [];
            $paymentTotalCents = 0;
            foreach ($payments as $index => $payment) {
                if (!is_array($payment)) {
                    throw new DomainException('Pagamento inválido.');
                }
                $method = (string) ($payment['method'] ?? '');
                if (!in_array($method, self::METHODS, true)) {
                    throw new DomainException('Forma de pagamento inválida.');
                }
                $amount = decimal_value($payment['amount'] ?? '');
                $amountCents = self::toCents($amount);
                if ($amountCents <= 0) {
                    throw new DomainException('Os pagamentos precisam ser maiores que zero.');
                }
                $paymentTotalCents += $amountCents;
                $normalizedPayments[] = [
                    'method' => $method,
                    'amount' => $amount,
                    'reference' => mb_substr(trim((string) ($payment['reference'] ?? '')), 0, 120) ?: null,
                    'key' => hash('sha256', $baseKey . ':' . $index),
                ];
            }
            if ($totalCents > 0 && $normalizedPayments === []) {
                throw new DomainException('Adicione pelo menos uma forma de pagamento.');
            }
            if ($paymentTotalCents !== $totalCents) {
                throw new DomainException('A soma dos pagamentos deve ser igual ao total da conta.');
            }

            $insert = $pdo->prepare(
                'INSERT INTO payments (table_session_id, payment_method, amount, reference, idempotency_key, received_by)
                 VALUES (:session_id, :method, :amount, :reference, :key, :received_by)'
            );
            foreach ($normalizedPayments as $payment) {
                $insert->execute([
                    'session_id' => $sessionId,
                    'method' => $payment['method'],
                    'amount' => $payment['amount'],
                    'reference' => $payment['reference'],
                    'key' => $payment['key'],
                    'received_by' => $user['id'],
                ]);
            }

            $pdo->prepare(
                "UPDATE table_sessions SET status = 'closed', closing_idempotency_key = :closing_key,
                    subtotal = :subtotal, service_fee = :service_fee,
                    discount = :discount, surcharge = :surcharge, total = :total,
                    closed_by = :closed_by, closed_at = NOW(), version = version + 1
                 WHERE id = :id"
            )->execute([
                'subtotal' => self::fromCents($subtotalCents),
                'closing_key' => $baseKey,
                'service_fee' => self::fromCents($serviceFeeCents),
                'discount' => self::fromCents($discountCents),
                'surcharge' => self::fromCents($surchargeCents),
                'total' => self::fromCents($totalCents),
                'closed_by' => $user['id'],
                'id' => $sessionId,
            ]);
            $pdo->prepare("UPDATE restaurant_tables SET status = 'available' WHERE id = :id")
                ->execute(['id' => $session['table_id']]);
            audit_log('table_closed', 'table_session', $sessionId, [
                'subtotal' => self::fromCents($subtotalCents),
                'total' => self::fromCents($totalCents),
                'payments' => array_column($normalizedPayments, 'method'),
            ]);
            return TableService::detailsBySession($sessionId, $pdo);
        });
    }

    private static function toCents(string $value): int
    {
        if (!preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', trim($value), $matches)) {
            throw new DomainException('Valor monetário inválido.');
        }
        return ((int) $matches[1] * 100) + (int) str_pad($matches[2] ?? '0', 2, '0');
    }

    private static function fromCents(int $cents): string
    {
        return sprintf('%d.%02d', intdiv($cents, 100), $cents % 100);
    }

    private static function percentToBasisPoints(string $percent): int
    {
        if (!preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', trim($percent), $matches)) {
            return 0;
        }
        return ((int) $matches[1] * 100) + (int) str_pad($matches[2] ?? '0', 2, '0');
    }
}
