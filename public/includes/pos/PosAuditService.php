<?php
/**
 * ==============================================================================
 * GroCo Enterprise POS — Audit Service
 * ==============================================================================
 * Immutable structured audit logging for all retail point-of-sale activities.
 * ==============================================================================
 */

declare(strict_types=1);

namespace Groco\Pos;

use PDO;
use PDOException;

class PosAuditService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function log(
        string $eventType,
        ?int $adminId = null,
        ?int $storeId = 1,
        ?int $registerId = null,
        ?int $terminalId = null,
        ?int $transactionId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?array $metadata = null
    ): void {
        try {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            
            // Mask any sensitive keys if accidentally present in metadata
            $sanitizedMeta = $this->sanitizePayload($metadata);
            $sanitizedOld = $this->sanitizePayload($oldValues);
            $sanitizedNew = $this->sanitizePayload($newValues);

            $stmt = $this->pdo->prepare("
                INSERT INTO pos_audit_logs (
                    event_type, admin_id, store_id, register_id, terminal_id, 
                    transaction_id, ip_address, old_values, new_values, metadata, created_at
                ) VALUES (
                    :event, :admin, :store, :reg, :term, 
                    :tx, :ip, :old, :new, :meta, NOW()
                )
            ");

            $stmt->execute([
                'event' => $eventType,
                'admin' => $adminId,
                'store' => $storeId ?: 1,
                'reg'   => $registerId,
                'term'  => $terminalId,
                'tx'    => $transactionId,
                'ip'    => $ip,
                'old'   => $sanitizedOld !== null ? json_encode($sanitizedOld, JSON_UNESCAPED_SLASHES) : null,
                'new'   => $sanitizedNew !== null ? json_encode($sanitizedNew, JSON_UNESCAPED_SLASHES) : null,
                'meta'  => $sanitizedMeta !== null ? json_encode($sanitizedMeta, JSON_UNESCAPED_SLASHES) : null,
            ]);
        } catch (PDOException $e) {
            error_log('[PosAuditService] Failed to write audit log: ' . $e->getMessage());
        }
    }

    private function sanitizePayload(?array $data): ?array
    {
        if ($data === null) return null;
        $sensitiveKeys = ['password', 'secret', 'card_number', 'cvv', 'token', 'auth_token'];
        $clean = [];
        foreach ($data as $k => $v) {
            if (in_array(strtolower((string)$k), $sensitiveKeys, true)) {
                $clean[$k] = '[REDACTED]';
            } elseif (is_array($v)) {
                $clean[$k] = $this->sanitizePayload($v);
            } else {
                $clean[$k] = $v;
            }
        }
        return $clean;
    }
}
