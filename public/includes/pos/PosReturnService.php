<?php
/**
 * ==============================================================================
 * GroCo Enterprise POS — Return & Refund Service
 * ==============================================================================
 * Itemized returns, original sale validation, non-duplication checks,
 * inventory restock, and multi-tender refund accounting.
 * ==============================================================================
 */

declare(strict_types=1);

namespace Groco\Pos;

use PDO;
use Exception;
use InvalidArgumentException;

class PosReturnService
{
    private PDO $pdo;
    private PosInventoryService $inventoryService;
    private PosAuditService $auditService;

    public function __construct(
        PDO $pdo,
        ?PosInventoryService $inventoryService = null,
        ?PosAuditService $auditService = null
    ) {
        $this->pdo = $pdo;
        $this->inventoryService = $inventoryService ?? new PosInventoryService($pdo);
        $this->auditService = $auditService ?? new PosAuditService($pdo);
    }

    /**
     * Process an itemized return against an original POS order or transaction.
     *
     * @param int $orderId Original Order ID
     * @param int $adminId Cashier / Supervisor performing return
     * @param array $returns Array of [productId => returnedQuantity]
     * @param string $refundMethod 'cash', 'card', 'mobile_banking', 'wallet'
     * @param string $reason Return rationale
     * @return array
     */
    public function processReturn(
        int $orderId,
        int $adminId,
        array $returns,
        string $refundMethod = 'cash',
        string $reason = ''
    ): array {
        if ($orderId <= 0 || empty($returns)) {
            throw new InvalidArgumentException("Original Order ID and return items list are required.");
        }

        // Fetch original order details
        $stmtOrder = $this->pdo->prepare("SELECT * FROM orders WHERE id = ? LIMIT 1");
        $stmtOrder->execute([$orderId]);
        $order = $stmtOrder->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            throw new Exception("Original Order #{$orderId} was not found.");
        }

        if ($order['status'] === 'cancelled') {
            throw new Exception("Cannot process return: order is already cancelled/voided.");
        }

        // Fetch original order items
        $stmtItems = $this->pdo->prepare("SELECT product_id, product_name, price, quantity FROM order_items WHERE order_id = ?");
        $stmtItems->execute([$orderId]);
        $origItems = [];
        foreach ($stmtItems->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $origItems[(int)$row['product_id']] = $row;
        }

        // Fetch previously returned quantities for this order
        $stmtPrev = $this->pdo->prepare("
            SELECT pri.product_id, SUM(pri.quantity) AS returned_qty
            FROM pos_return_items pri
            JOIN pos_returns pr ON pr.id = pri.pos_return_id
            WHERE pr.order_id = ?
            GROUP BY pri.product_id
        ");
        $stmtPrev->execute([$orderId]);
        $prevReturns = [];
        foreach ($stmtPrev->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $prevReturns[(int)$row['product_id']] = (float)$row['returned_qty'];
        }

        $refundTotal = 0.00;
        $itemsToReturn = [];

        foreach ($returns as $pid => $qty) {
            $pid = (int)$pid;
            $qty = (float)$qty;
            if ($qty <= 0) continue;

            if (!isset($origItems[$pid])) {
                throw new Exception("Product ID #{$pid} was not part of original Order #{$order['order_number']}.");
            }

            $origQty = (float)$origItems[$pid]['quantity'];
            $alreadyReturned = $prevReturns[$pid] ?? 0.00;
            $maxReturnable = round($origQty - $alreadyReturned, 3);

            if ($qty > $maxReturnable) {
                throw new Exception(
                    "Product '{$origItems[$pid]['product_name']}' return quantity ({$qty}) exceeds available returnable quantity ({$maxReturnable})."
                );
            }

            $unitPrice = (float)$origItems[$pid]['price'];
            $lineRefund = round($unitPrice * $qty, 2);
            $refundTotal += $lineRefund;

            $itemsToReturn[] = [
                'product_id'   => $pid,
                'product_name' => $origItems[$pid]['product_name'],
                'quantity'     => $qty,
                'unit_price'   => $unitPrice,
                'line_refund'  => $lineRefund
            ];
        }

        if ($refundTotal <= 0.00 || empty($itemsToReturn)) {
            throw new Exception("No valid items specified for return/refund.");
        }

        try {
            $this->pdo->beginTransaction();

            // 1. Create return header
            $stmtRet = $this->pdo->prepare("
                INSERT INTO pos_returns (
                    order_id, admin_id, refund_amount, refund_method, created_at
                ) VALUES (
                    :ord_id, :admin_id, :amount, :method, NOW()
                )
            ");
            $stmtRet->execute([
                'ord_id'   => $orderId,
                'admin_id' => $adminId,
                'amount'   => $refundTotal,
                'method'   => $refundMethod
            ]);
            $returnId = (int)$this->pdo->lastInsertId();

            // 2. Insert return line items & restock inventory
            $stmtRetItem = $this->pdo->prepare("
                INSERT INTO pos_return_items (pos_return_id, product_id, quantity)
                VALUES (?, ?, ?)
            ");

            foreach ($itemsToReturn as $item) {
                $stmtRetItem->execute([$returnId, $item['product_id'], $item['quantity']]);

                // Restock inventory with audit movement log
                $this->inventoryService->restock(
                    $item['product_id'],
                    $item['quantity'],
                    $adminId,
                    "POS Return #{$returnId} for Order #{$order['order_number']} ({$reason})"
                );
            }

            // 3. Post refund disbursement expense to financial ledger
            $stmtLedger = $this->pdo->prepare("
                INSERT INTO transactions (
                    type, category_id, amount, reference, payment_method, reconciled, created_at
                ) VALUES (
                    'expense', NULL, :amount, :ref, :method, 1, NOW()
                )
            ");
            $stmtLedger->execute([
                'amount' => $refundTotal,
                'ref'    => "POS Return #{$returnId} (Order #{$order['order_number']})",
                'method' => $refundMethod
            ]);

            // 4. Update pos_transactions status if linked
            $stmtTx = $this->pdo->prepare("SELECT id, status FROM pos_transactions WHERE order_id = ? LIMIT 1");
            $stmtTx->execute([$orderId]);
            $tx = $stmtTx->fetch(PDO::FETCH_ASSOC);
            if ($tx) {
                $this->pdo->prepare("UPDATE pos_transactions SET status = 'partial_return' WHERE id = ?")->execute([$tx['id']]);
            }

            // 5. Write audit log
            $this->auditService->log(
                'RETURN_CREATED',
                $adminId,
                1,
                null,
                null,
                $tx ? (int)$tx['id'] : null,
                null,
                [
                    'return_id'     => $returnId,
                    'order_id'      => $orderId,
                    'refund_amount' => $refundTotal,
                    'refund_method' => $refundMethod,
                    'reason'        => $reason,
                    'items_count'   => count($itemsToReturn)
                ]
            );

            $this->pdo->commit();

            return [
                'success'       => true,
                'return_id'     => $returnId,
                'order_id'      => $orderId,
                'order_number'  => $order['order_number'],
                'refund_amount' => $refundTotal,
                'refund_method' => $refundMethod,
                'items'         => $itemsToReturn
            ];

        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('[PosReturnService] Return processing failed: ' . $e->getMessage());
            throw $e;
        }
    }
}
