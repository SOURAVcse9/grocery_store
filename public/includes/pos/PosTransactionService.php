<?php
/**
 * ==============================================================================
 * GroCo Enterprise POS — Transaction Engine Service
 * ==============================================================================
 * Core retail transaction engine. Executes atomic multi-tender checkouts,
 * concurrency-safe inventory deductions, unique sequence numbering,
 * idempotency checks, and immutable financial ledger posting.
 * ==============================================================================
 */

declare(strict_types=1);

namespace Groco\Pos;

use PDO;
use Exception;
use InvalidArgumentException;

class PosTransactionService
{
    private PDO $pdo;
    private PosCartService $cartService;
    private PosInventoryService $inventoryService;
    private PosPaymentService $paymentService;
    private PosCustomerService $customerService;
    private PosDiscountService $discountService;
    private PosAuditService $auditService;

    public function __construct(
        PDO $pdo,
        ?PosCartService $cartService = null,
        ?PosInventoryService $inventoryService = null,
        ?PosPaymentService $paymentService = null,
        ?PosCustomerService $customerService = null,
        ?PosDiscountService $discountService = null,
        ?PosAuditService $auditService = null
    ) {
        $this->pdo = $pdo;
        $this->cartService = $cartService ?? new PosCartService();
        $this->inventoryService = $inventoryService ?? new PosInventoryService($pdo);
        $this->paymentService = $paymentService ?? new PosPaymentService($pdo);
        $this->customerService = $customerService ?? new PosCustomerService($pdo);
        $this->discountService = $discountService ?? new PosDiscountService($pdo);
        $this->auditService = $auditService ?? new PosAuditService($pdo);
    }

    /**
     * Generate a unique human-friendly transaction number (e.g. GR-20261004-000001).
     */
    public function generateTransactionNumber(): string
    {
        $datePrefix = date('Ymd');
        $randomSuffix = strtoupper(bin2hex(random_bytes(3))); // 6 hex characters
        return "GR-{$datePrefix}-{$randomSuffix}";
    }

    /**
     * Process and commit a POS retail transaction atomically.
     *
     * @param array $payload Detailed checkout payload:
     *                       - store_id (int)
     *                       - register_id (int)
     *                       - terminal_id (int)
     *                       - shift_id (int)
     *                       - cashier_id (int)
     *                       - customer_id (int|null)
     *                       - items (array)
     *                       - cart_discount (float)
     *                       - coupon_code (string|null)
     *                       - payments (array)
     *                       - client_uuid (string|null)
     *                       - notes (string)
     *                       - is_offline (bool)
     * @return array
     */
    public function processSale(array $payload): array
    {
        $cashierId = (int)($payload['cashier_id'] ?? 0);
        $storeId = (int)($payload['store_id'] ?? 1);
        $registerId = (int)($payload['register_id'] ?? 1);
        $terminalId = (int)($payload['terminal_id'] ?? 1);
        $shiftId = !empty($payload['shift_id']) ? (int)$payload['shift_id'] : null;
        $customerId = !empty($payload['customer_id']) ? (int)$payload['customer_id'] : null;
        $items = $payload['items'] ?? [];
        $cartDiscount = (float)($payload['cart_discount'] ?? 0.00);
        $couponCode = trim((string)($payload['coupon_code'] ?? ''));
        $rawPayments = $payload['payments'] ?? [];
        $clientUuid = !empty($payload['client_uuid']) ? trim((string)$payload['client_uuid']) : null;
        $notes = trim((string)($payload['notes'] ?? 'POS Retail Checkout'));
        $isOffline = !empty($payload['is_offline']);

        if ($cashierId <= 0) {
            throw new InvalidArgumentException("Valid cashier operator ID is required.");
        }
        if (empty($items)) {
            throw new InvalidArgumentException("Cannot process transaction: checkout cart is empty.");
        }

        // 1. Idempotency check: if client_uuid already exists, return existing transaction
        if ($clientUuid !== null) {
            $stmtCheckUuid = $this->pdo->prepare("SELECT id, transaction_number, order_id, total_amount FROM pos_transactions WHERE client_uuid = ? LIMIT 1");
            $stmtCheckUuid->execute([$clientUuid]);
            $existingTx = $stmtCheckUuid->fetch(PDO::FETCH_ASSOC);
            if ($existingTx) {
                return [
                    'success'            => true,
                    'is_idempotent_hit'  => true,
                    'transaction_id'     => (int)$existingTx['id'],
                    'transaction_number' => $existingTx['transaction_number'],
                    'order_id'           => (int)$existingTx['order_id'],
                    'total_amount'       => (float)$existingTx['total_amount']
                ];
            }
        }

        // 2. Resolve Customer ID
        $resolvedCustomerId = $this->customerService->resolveCustomerId($customerId);

        try {
            $this->pdo->beginTransaction();

            // 3. Concurrency-safe lock and validate catalog products & stocks
            $lockedProducts = $this->inventoryService->lockAndValidateStock($items);

            // 4. Validate and apply coupon if provided
            $couponDiscount = 0.00;
            $couponId = null;
            if ($couponCode !== '') {
                $rawSubtotal = 0.00;
                foreach ($items as $it) {
                    $rawSubtotal += ((float)$it['price'] * (float)$it['quantity']);
                }
                $couponRes = $this->discountService->applyCoupon($couponCode, $rawSubtotal);
                if ($couponRes['is_valid']) {
                    $couponDiscount = $couponRes['discount'];
                    $couponId = $couponRes['coupon_id'];
                }
            }

            // 5. Calculate Cart Breakdown
            $cartCalc = $this->cartService->calculate($items, $cartDiscount, $couponDiscount);
            $grandTotal = $cartCalc['grand_total'];
            $subtotal = $cartCalc['subtotal'];
            $totalDiscount = $cartCalc['total_discount'];
            $taxAmount = $cartCalc['tax_amount'];
            $rounding = $cartCalc['rounding'];

            // 6. Validate Payment Tenders against grand total
            $paymentValidation = $this->paymentService->validateTenders($grandTotal, $rawPayments, $resolvedCustomerId);
            $totalPaid = $paymentValidation['total_paid'];
            $totalChange = $paymentValidation['change_amount'];
            $tenders = $paymentValidation['tenders'];

            // Calculate primary payment method enum for legacy orders table
            $primaryMethod = 'cod';
            $maxTenderAmt = 0.00;
            $walletAmountPaid = 0.00;
            foreach ($tenders as $t) {
                if ($t['method'] === 'wallet') {
                    $walletAmountPaid += $t['amount'];
                }
                if ($t['amount'] > $maxTenderAmt) {
                    $maxTenderAmt = $t['amount'];
                    if ($t['method'] === 'card') {
                        $primaryMethod = 'card';
                    } elseif (in_array($t['method'], ['bkash', 'nagad', 'rocket', 'mobile_banking'], true)) {
                        $primaryMethod = 'mobile_banking';
                    } else {
                        $primaryMethod = 'cod';
                    }
                }
            }

            // 7. Generate Sequence Number & Create master order in orders table
            $txNumber = $this->generateTransactionNumber();
            $orderNumber = 'POS-' . date('Ymd') . '-' . rand(1000, 9999);

            $stmtOrder = $this->pdo->prepare("
                INSERT INTO orders (
                    order_number, user_id, address_id, coupon_id, 
                    subtotal, discount_amount, total_amount, payment_method, 
                    payment_status, status, note, created_at
                ) VALUES (
                    :ord_num, :uid, NULL, :coupon_id, 
                    :subtotal, :discount, :total, :method, 
                    'paid', 'delivered', :note, NOW()
                )
            ");

            $stmtOrder->execute([
                'ord_num'   => $orderNumber,
                'uid'       => $resolvedCustomerId,
                'coupon_id' => $couponId,
                'subtotal'  => $subtotal,
                'discount'  => $totalDiscount,
                'total'     => $grandTotal,
                'method'    => $primaryMethod,
                'note'      => "{$notes} [Tx: {$txNumber}]"
            ]);
            $orderId = (int)$this->pdo->lastInsertId();

            // 8. Create POS Transaction Record
            $stmtTx = $this->pdo->prepare("
                INSERT INTO pos_transactions (
                    transaction_number, order_id, client_uuid, store_id, register_id, 
                    terminal_id, shift_id, cashier_id, customer_id, status, 
                    subtotal, discount_amount, tax_amount, total_amount, paid_amount, 
                    change_amount, rounding_amount, notes, is_offline, synced_at, created_at
                ) VALUES (
                    :tx_num, :ord_id, :uuid, :store, :reg, 
                    :term, :shift, :cashier, :cust, 'completed', 
                    :subtotal, :discount, :tax, :total, :paid, 
                    :change, :round, :notes, :offline, :synced, NOW()
                )
            ");

            $stmtTx->execute([
                'tx_num'   => $txNumber,
                'ord_id'   => $orderId,
                'uuid'     => $clientUuid,
                'store'    => $storeId,
                'reg'      => $registerId,
                'term'     => $terminalId,
                'shift'    => $shiftId,
                'cashier'  => $cashierId,
                'cust'     => $resolvedCustomerId,
                'subtotal' => $subtotal,
                'discount' => $totalDiscount,
                'tax'      => $taxAmount,
                'total'    => $grandTotal,
                'paid'     => $totalPaid,
                'change'   => $totalChange,
                'round'    => $rounding,
                'notes'    => $notes,
                'offline'  => $isOffline ? 1 : 0,
                'synced'   => $isOffline ? date('Y-m-d H:i:s') : null
            ]);
            $transactionId = (int)$this->pdo->lastInsertId();

            // 9. Save Line Items & Deduct Inventory Stock
            $stmtTxItem = $this->pdo->prepare("
                INSERT INTO pos_transaction_items (
                    transaction_id, product_id, product_name, product_sku, product_barcode, 
                    unit, is_weighted, regular_price, unit_price, quantity, 
                    discount_amount, tax_amount, line_total, price_overridden, 
                    override_reason, created_at
                ) VALUES (
                    :tx_id, :pid, :name, :sku, :barcode, 
                    :unit, :weighted, :reg_price, :unit_price, :qty, 
                    :disc, :tax, :line_tot, :overridden, 
                    :reason, NOW()
                )
            ");

            $stmtLegacyItem = $this->pdo->prepare("
                INSERT INTO order_items (
                    order_id, product_id, product_name, product_sku, 
                    price, quantity, line_total
                ) VALUES (
                    :ord_id, :pid, :name, :sku, 
                    :price, :qty, :tot
                )
            ");

            foreach ($cartCalc['items'] as $item) {
                $pid = $item['id'];
                $prodInfo = $lockedProducts[$pid];
                $qty = (float)$item['quantity'];
                $unitPrice = (float)$item['unit_price'];
                $regPrice = (float)($prodInfo['price'] ?? $unitPrice);
                $lineTotal = (float)$item['line_total'];

                // Insert into pos_transaction_items
                $stmtTxItem->execute([
                    'tx_id'      => $transactionId,
                    'pid'        => $pid,
                    'name'       => $prodInfo['name'],
                    'sku'        => $prodInfo['sku'] ?? 'N/A',
                    'barcode'    => $prodInfo['barcode'] ?? '',
                    'unit'       => $item['unit'],
                    'weighted'   => $item['is_weighted'],
                    'reg_price'  => $regPrice,
                    'unit_price' => $unitPrice,
                    'qty'        => $qty,
                    'disc'       => $item['discount'],
                    'tax'        => 0.00,
                    'line_tot'   => $lineTotal,
                    'overridden' => $item['price_override'],
                    'reason'     => $item['override_reason']
                ]);

                // Insert into legacy order_items
                $stmtLegacyItem->execute([
                    'ord_id' => $orderId,
                    'pid'    => $pid,
                    'name'   => $prodInfo['name'],
                    'sku'    => $prodInfo['sku'] ?? 'N/A',
                    'price'  => $unitPrice,
                    'qty'    => (int)ceil($qty),
                    'tot'    => $lineTotal
                ]);

                // Atomically deduct inventory with audit movement log
                $this->inventoryService->deductStock(
                    $pid,
                    $qty,
                    $cashierId,
                    "POS Counter Sale Tx #{$txNumber} [Order #{$orderNumber}]",
                    $storeId
                );
            }

            // 10. Persist Multi-Tender Payments
            $this->paymentService->recordPayments($transactionId, $tenders);

            // 11. Process Customer Wallet Deduction & Loyalty Points
            $this->customerService->processPostSaleLoyalty($resolvedCustomerId, $walletAmountPaid, $grandTotal);

            // 12. Post Financial Ledger Transaction
            $stmtLedger = $this->pdo->prepare("
                INSERT INTO transactions (
                    type, category_id, amount, reference, payment_method, reconciled, created_at
                ) VALUES (
                    'income', NULL, :amount, :ref, 'pos_multi', 1, NOW()
                )
            ");
            $stmtLedger->execute([
                'amount' => $grandTotal,
                'ref'    => "POS Sale: {$txNumber} (Order #{$orderNumber})"
            ]);

            // 13. Write Structured Audit Log
            $this->auditService->log(
                'SALE_CREATED',
                $cashierId,
                $storeId,
                $registerId,
                $terminalId,
                $transactionId,
                null,
                [
                    'grand_total'   => $grandTotal,
                    'paid_amount'   => $totalPaid,
                    'change_amount' => $totalChange,
                    'item_count'    => $cartCalc['item_count']
                ],
                ['order_number' => $orderNumber, 'tx_number' => $txNumber]
            );

            $this->pdo->commit();

            return [
                'success'            => true,
                'transaction_id'     => $transactionId,
                'transaction_number' => $txNumber,
                'order_id'           => $orderId,
                'order_number'       => $orderNumber,
                'total_amount'       => $grandTotal,
                'paid_amount'        => $totalPaid,
                'change_amount'      => $totalChange,
                'item_count'         => $cartCalc['item_count']
            ];

        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('[PosTransactionService] Sale failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Retrieve complete transaction record with items and payments.
     */
    public function getTransaction(int $transactionId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT t.*, s.name AS store_name, s.address AS store_address, s.phone AS store_phone, 
                   s.vat_reg_no AS store_vat, a.full_name AS cashier_name, a.username AS cashier_username,
                   u.full_name AS customer_name, u.phone AS customer_phone
            FROM pos_transactions t
            LEFT JOIN pos_stores s ON s.id = t.store_id
            LEFT JOIN admins a ON a.id = t.cashier_id
            LEFT JOIN users u ON u.id = t.customer_id
            WHERE t.id = ? LIMIT 1
        ");
        $stmt->execute([$transactionId]);
        $tx = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$tx) {
            return null;
        }

        // Fetch items
        $stmtItems = $this->pdo->prepare("SELECT * FROM pos_transaction_items WHERE transaction_id = ?");
        $stmtItems->execute([$transactionId]);
        $tx['items'] = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

        // Fetch payments
        $stmtPay = $this->pdo->prepare("SELECT * FROM pos_payments WHERE transaction_id = ?");
        $stmtPay->execute([$transactionId]);
        $tx['payments'] = $stmtPay->fetchAll(PDO::FETCH_ASSOC);

        return $tx;
    }

    /**
     * Void a completed transaction and restore inventory stock.
     */
    public function voidTransaction(int $transactionId, int $adminId, string $reason = ''): void
    {
        $tx = $this->getTransaction($transactionId);
        if (!$tx) {
            throw new Exception("Transaction ID #{$transactionId} not found.");
        }
        if ($tx['status'] === 'voided') {
            throw new Exception("Transaction is already voided.");
        }

        try {
            $this->pdo->beginTransaction();

            // 1. Mark transaction as voided
            $stmtUp = $this->pdo->prepare("UPDATE pos_transactions SET status = 'voided', notes = CONCAT(COALESCE(notes, ''), ' [VOIDED: ', ?, ']') WHERE id = ?");
            $stmtUp->execute([$reason ?: 'Administrative Void', $transactionId]);

            // 2. Mark linked order as cancelled & refunded
            if (!empty($tx['order_id'])) {
                $stmtOrd = $this->pdo->prepare("UPDATE orders SET status = 'cancelled', payment_status = 'refunded' WHERE id = ?");
                $stmtOrd->execute([$tx['order_id']]);
            }

            // 3. Restock inventory for all line items
            foreach ($tx['items'] as $item) {
                $this->inventoryService->restock(
                    (int)$item['product_id'],
                    (float)$item['quantity'],
                    $adminId,
                    "Void POS Transaction #{$tx['transaction_number']} ({$reason})",
                    (int)$tx['store_id']
                );
            }

            // 4. Financial ledger reversal expense
            $stmtLedger = $this->pdo->prepare("
                INSERT INTO transactions (type, category_id, amount, reference, payment_method, reconciled, created_at)
                VALUES ('expense', NULL, ?, ?, 'cash', 1, NOW())
            ");
            $stmtLedger->execute([
                $tx['total_amount'],
                "Void POS Invoice Reversal: {$tx['transaction_number']}"
            ]);

            // 5. Audit log
            $this->auditService->log(
                'SALE_VOIDED',
                $adminId,
                (int)$tx['store_id'],
                (int)$tx['register_id'],
                (int)$tx['terminal_id'],
                $transactionId,
                ['status' => 'completed'],
                ['status' => 'voided', 'reason' => $reason]
            );

            $this->pdo->commit();
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('[PosTransactionService] Void failed: ' . $e->getMessage());
            throw $e;
        }
    }
}
