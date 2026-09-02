<?php
declare(strict_types=1);

function audit_log(string $action, ?string $entityType = null, int|string|null $entityId = null, array $details = []): void
{
    try {
        $statement = Database::connection()->prepare(
            'INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address, created_at)
             VALUES (:user_id, :action, :entity_type, :entity_id, :details, :ip_address, NOW())'
        );
        $statement->execute([
            'user_id' => $_SESSION['user']['id'] ?? null,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId === null ? null : (string) $entityId,
            'details' => $details === [] ? null : json_encode($details, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'ip_address' => client_ip(),
        ]);
    } catch (Throwable $exception) {
        error_log('Falha ao registrar auditoria: ' . $exception->getMessage());
    }
}
