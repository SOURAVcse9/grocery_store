<?php
/**
 * ==============================================================================
 * GroCo Enterprise POS — Offline Queue & Conflict-Free Sync Engine
 * ==============================================================================
 * Synchronizes client offline queues with the central server using idempotent
 * batch processing, conflict resolution, retry counters, and audit trails.
 * ==============================================================================
 */

declare(strict_types=1);

namespace Groco\Pos;

use PDO;
use Exception;
use Throwable;

class PosSyncService
{
    private PDO $pdo;
    private PosTransactionService $transactionService;
    private PosAuditService $auditService;

    public function __construct(
        PDO $pdo,
        ?PosTransactionService $transactionService = null,
        ?PosAuditService $auditService = null
    ) {
        $this->pdo = $pdo;
        $this->transactionService = $transactionService ?? new PosTransactionService($pdo);
        $this->auditService = $auditService ?? new PosAuditService($pdo);
    }

    /**
     * Synchronize a batch of offline transactions from a POS terminal.
     *
     * @param array $queueItems Array of queue items, each either a direct transaction payload
     *                          or a wrapped object with client_uuid, action_type, payload.
     * @return array Sync result summary
     */
    public function syncBatch(array $queueItems, int $storeId = 1, int $registerId = 1, int $terminalId = 1, ?int $cashierId = null, ?int $shiftId = null): array
    {
        $processed = 0;
        $synced = 0;
        $failed = 0;
        $results = [];

        foreach ($queueItems as $item) {
            $processed++;
            $uuid = trim((string)($item['client_uuid'] ?? ''));
            $action = (string)($item['action'] ?? ($item['action_type'] ?? 'sale'));
            $itemStoreId = (int)($item['store_id'] ?? $storeId);

            $payload = isset($item['payload']) 
                ? (is_array($item['payload']) ? $item['payload'] : json_decode((string)$item['payload'], true)) 
                : $item;

            if ($uuid === '' || empty($payload)) {
                $failed++;
                $results[] = [
                    'client_uuid' => $uuid,
                    'status'      => 'failed',
                    'error'       => 'Missing client_uuid or payload.'
                ];
                continue;
            }

            // Ensure store_id, register_id, terminal_id, cashier_id, shift_id are populated
            if (!isset($payload['store_id'])) $payload['store_id'] = $itemStoreId;
            if (!isset($payload['register_id'])) $payload['register_id'] = $registerId;
            if (!isset($payload['terminal_id'])) $payload['terminal_id'] = $terminalId;
            if (!isset($payload['cashier_id']) && $cashierId !== null) $payload['cashier_id'] = $cashierId;
            if (!isset($payload['shift_id']) && $shiftId !== null) $payload['shift_id'] = $shiftId;
            $payload['is_offline'] = true;
            $payload['client_uuid'] = $uuid;

            // Record or retrieve entry in pos_sync_queue
            $stmtQueue = $this->pdo->prepare("
                INSERT INTO pos_sync_queue (
                    client_uuid, store_id, action_type, payload, status, created_at
                ) VALUES (
                    :uuid, :store, :action, :payload, 'syncing', NOW()
                )
                ON DUPLICATE KEY UPDATE 
                    retry_count = retry_count + 1,
                    status = IF(status = 'synced', 'synced', 'syncing'),
                    updated_at = NOW()
            ");
            $stmtQueue->execute([
                'uuid'    => $uuid,
                'store'   => $itemStoreId,
                'action'  => $action,
                'payload' => json_encode($payload)
            ]);

            try {
                // Process sale transaction through idempotent engine
                $txResult = $this->transactionService->processSale($payload);

                // Update queue status as synced
                $stmtUp = $this->pdo->prepare("
                    UPDATE pos_sync_queue SET 
                        status = 'synced', 
                        synced_at = NOW(), 
                        error_message = NULL,
                        updated_at = NOW() 
                    WHERE client_uuid = ?
                ");
                $stmtUp->execute([$uuid]);

                $synced++;
                $results[] = [
                    'client_uuid'        => $uuid,
                    'status'             => 'synced',
                    'is_idempotent_hit'  => !empty($txResult['is_idempotent_hit']),
                    'transaction_id'     => $txResult['transaction_id'],
                    'transaction_number' => $txResult['transaction_number'],
                    'order_id'           => $txResult['order_id'],
                    'total_amount'       => $txResult['total_amount']
                ];
            } catch (Throwable $e) {
                $failed++;
                $errorMsg = $e->getMessage();

                // Update queue status as failed
                $stmtFail = $this->pdo->prepare("
                    UPDATE pos_sync_queue SET 
                        status = 'failed', 
                        error_message = ?, 
                        updated_at = NOW() 
                    WHERE client_uuid = ?
                ");
                $stmtFail->execute([$errorMsg, $uuid]);

                $this->auditService->log(
                    'OFFLINE_SYNC_FAILED',
                    $cashierId,
                    $itemStoreId,
                    $registerId,
                    $terminalId,
                    null,
                    null,
                    null,
                    ['client_uuid' => $uuid, 'error' => $errorMsg]
                );

                $results[] = [
                    'client_uuid' => $uuid,
                    'status'      => 'failed',
                    'error'       => $errorMsg
                ];
            }
        }

        return [
            'success'         => ($failed === 0),
            'processed_count' => $processed,
            'synced_count'    => $synced,
            'failed_count'    => $failed,
            'results'         => $results
        ];
    }

    /**
     * Get pending unsynced queue status summary.
     */
    public function getPendingQueueSummary(int $storeId = 1): array
    {
        $stmt = $this->pdo->prepare("
            SELECT status, COUNT(*) as count 
            FROM pos_sync_queue 
            WHERE store_id = ? 
            GROUP BY status
        ");
        $stmt->execute([$storeId]);
        $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        return [
            'pending' => (int)($rows['pending'] ?? 0),
            'syncing' => (int)($rows['syncing'] ?? 0),
            'synced'  => (int)($rows['synced'] ?? 0),
            'failed'  => (int)($rows['failed'] ?? 0),
            'total'   => array_sum($rows)
        ];
    }
}
