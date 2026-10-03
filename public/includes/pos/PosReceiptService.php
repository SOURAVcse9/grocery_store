<?php
/**
 * ==============================================================================
 * GroCo Enterprise POS — Receipt & Invoice Generation Service
 * ==============================================================================
 * Formats 80mm, 58mm, and A4 enterprise sales receipts with barcode, tax summary,
 * tender allocations, dynamic store metadata, and reprint audit tracking.
 * ==============================================================================
 */

declare(strict_types=1);

namespace Groco\Pos;

use PDO;
use Exception;

class PosReceiptService
{
    private PDO $pdo;
    private PosAuditService $auditService;

    public function __construct(PDO $pdo, ?PosAuditService $auditService = null)
    {
        $this->pdo = $pdo;
        $this->auditService = $auditService ?? new PosAuditService($pdo);
    }

    /**
     * Get complete printable receipt data object for a given Order ID or Transaction ID.
     */
    public function getReceiptData(int $orderOrTxId, bool $isTxId = false, ?int $reprintAdminId = null): array
    {
        if ($isTxId) {
            $stmt = $this->pdo->prepare("SELECT * FROM pos_transactions WHERE id = ? LIMIT 1");
            $stmt->execute([$orderOrTxId]);
            $tx = $stmt->fetch(PDO::FETCH_ASSOC);
            $orderId = $tx ? (int)$tx['order_id'] : 0;
        } else {
            $orderId = $orderOrTxId;
            $stmt = $this->pdo->prepare("SELECT * FROM pos_transactions WHERE order_id = ? LIMIT 1");
            $stmt->execute([$orderId]);
            $tx = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        $stmtOrder = $this->pdo->prepare("SELECT * FROM orders WHERE id = ? LIMIT 1");
        $stmtOrder->execute([$orderId]);
        $order = $stmtOrder->fetch(PDO::FETCH_ASSOC);

        if (!$order && !$tx) {
            throw new Exception("Sales record not found for Receipt generation.");
        }

        // Fetch Store info
        $storeId = (int)($tx['store_id'] ?? 1);
        $stmtStore = $this->pdo->prepare("SELECT * FROM pos_stores WHERE id = ? LIMIT 1");
        $stmtStore->execute([$storeId]);
        $store = $stmtStore->fetch(PDO::FETCH_ASSOC);

        if (!$store) {
            $store = [
                'name'       => 'GroCo Superstore',
                'address'    => 'Road 4, Mid Badda, Dhaka, Bangladesh',
                'phone'      => '+8801700000000',
                'vat_reg_no' => 'BIN-192837465',
                'currency'   => 'BDT'
            ];
        }

        // Fetch Cashier info
        $cashierName = 'Counter Cashier';
        if ($tx && !empty($tx['cashier_id'])) {
            $stmtCashier = $this->pdo->prepare("SELECT full_name, username FROM admins WHERE id = ? LIMIT 1");
            $stmtCashier->execute([$tx['cashier_id']]);
            $cashier = $stmtCashier->fetch(PDO::FETCH_ASSOC);
            if ($cashier) {
                $cashierName = $cashier['full_name'] . " (@{$cashier['username']})";
            }
        }

        // Fetch Customer info
        $customerName = 'Walk-in Customer';
        $customerPhone = 'N/A';
        $userId = (int)($order['user_id'] ?? ($tx['customer_id'] ?? 0));
        if ($userId > 0) {
            $stmtCust = $this->pdo->prepare("SELECT full_name, phone FROM users WHERE id = ? LIMIT 1");
            $stmtCust->execute([$userId]);
            $cust = $stmtCust->fetch(PDO::FETCH_ASSOC);
            if ($cust) {
                $customerName = $cust['full_name'];
                $customerPhone = $cust['phone'];
            }
        }

        // Fetch Line Items
        $items = [];
        if ($tx) {
            $stmtItems = $this->pdo->prepare("SELECT * FROM pos_transaction_items WHERE transaction_id = ?");
            $stmtItems->execute([$tx['id']]);
            $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);
        }
        if (empty($items) && $order) {
            $stmtLegacy = $this->pdo->prepare("
                SELECT oi.*, COALESCE(oi.product_name, p.name, 'Product') AS product_name, COALESCE(p.unit, 'pcs') AS unit 
                FROM order_items oi
                LEFT JOIN products p ON p.id = oi.product_id
                WHERE oi.order_id = ?
            ");
            $stmtLegacy->execute([$order['id']]);
            foreach ($stmtLegacy->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $items[] = [
                    'product_name' => $row['product_name'],
                    'product_sku'  => $row['product_sku'] ?? 'N/A',
                    'unit'         => $row['unit'] ?? 'pcs',
                    'unit_price'   => (float)$row['price'],
                    'quantity'     => (float)$row['quantity'],
                    'discount'     => 0.00,
                    'line_total'   => (float)$row['line_total']
                ];
            }
        }

        // Fetch Payments Breakdown
        $payments = [];
        if ($tx) {
            $stmtPay = $this->pdo->prepare("SELECT * FROM pos_payments WHERE transaction_id = ?");
            $stmtPay->execute([$tx['id']]);
            $payments = $stmtPay->fetchAll(PDO::FETCH_ASSOC);
        }

        // Log reprint if requested
        if ($reprintAdminId !== null) {
            $this->auditService->log(
                'RECEIPT_REPRINTED',
                $reprintAdminId,
                $storeId,
                null,
                null,
                $tx ? (int)$tx['id'] : null,
                null,
                ['order_id' => $orderId, 'reprinted_by' => $reprintAdminId]
            );
        }

        return [
            'store'              => $store,
            'transaction_number' => $tx['transaction_number'] ?? ($order['order_number'] ?? 'N/A'),
            'order_number'       => $order['order_number'] ?? ($tx['transaction_number'] ?? 'N/A'),
            'date_time'          => $tx['created_at'] ?? ($order['created_at'] ?? date('Y-m-d H:i:s')),
            'cashier'            => $cashierName,
            'customer_name'      => $customerName,
            'customer_phone'     => $customerPhone,
            'items'              => $items,
            'subtotal'           => (float)($tx['subtotal'] ?? ($order['subtotal'] ?? 0.00)),
            'discount_amount'    => (float)($tx['discount_amount'] ?? ($order['discount_amount'] ?? 0.00)),
            'tax_amount'         => (float)($tx['tax_amount'] ?? 0.00),
            'grand_total'        => (float)($tx['total_amount'] ?? ($order['total_amount'] ?? 0.00)),
            'paid_amount'        => (float)($tx['paid_amount'] ?? ($order['total_amount'] ?? 0.00)),
            'change_amount'      => (float)($tx['change_amount'] ?? 0.00),
            'payments'           => $payments,
            'return_policy'      => 'Exchange within 3 days with receipt in original sealed packaging. Perishables cannot be returned.',
            'thank_you_message'  => 'Thank you for shopping at GroCo Superstore!'
        ];
    }
}
