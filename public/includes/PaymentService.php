<?php
/**
 * ==============================================================================
 * GroCo Grocery Store — Enterprise Payment Service Layer
 * ==============================================================================
 * Central authority for payment state transitions, idempotency, row-level locking,
 * order inventory finalization, double-deduction guards, refunds, and security audits.
 * ==============================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/SSLCommerzService.php';

class PaymentService
{
    private PDO $pdo;
    private SSLCommerzService $sslcommerz;

    public function __construct(?PDO $pdo = null, ?SSLCommerzService $sslcommerz = null)
    {
        $this->pdo = $pdo ?? Database::getConnection();
        $this->sslcommerz = $sslcommerz ?? new SSLCommerzService();
    }

    public function getGatewayService(): SSLCommerzService
    {
        return $this->sslcommerz;
    }

    /**
     * Generate a unique, server-controlled transaction ID strictly compatible with SSLCOMMERZ (<= 30 chars)
     * Format: GRO-{orderId}-P{attempt}-{8 random hex} (e.g. GRO-10025-P1-9B4C2A1F)
     */
    public function generateTransactionId(int $orderId, int $attempt = 1): string
    {
        $randomHex = strtoupper(bin2hex(random_bytes(4))); // 8 chars
        $tranId = sprintf('GRO-%d-P%d-%s', $orderId, $attempt, $randomHex);

        // Ensure <= 30 chars
        if (strlen($tranId) > 30) {
            $tranId = substr($tranId, 0, 30);
        }

        // Verify uniqueness in database
        $check = $this->pdo->prepare('SELECT COUNT(*) FROM payments WHERE tran_id = ?');
        $check->execute([$tranId]);
        if ((int)$check->fetchColumn() > 0) {
            // Collision fallback (extremely rare)
            return $this->generateTransactionId($orderId, $attempt + 1);
        }

        return $tranId;
    }

    /**
     * Create payment attempt and initiate SSLCOMMERZ session
     *
     * @param int $orderId
     * @param int $userId
     * @param array<string, mixed> $shippingAddress
     * @param string $paymentMethod
     * @return array{success: bool, gateway_url: ?string, tran_id: ?string, error: ?string}
     */
    public function initiateOnlinePayment(int $orderId, int $userId, array $shippingAddress = [], string $paymentMethod = 'sslcommerz'): array
    {
        try {
            $this->pdo->beginTransaction();

            // 1. Lock order row
            $orderStmt = $this->pdo->prepare('SELECT * FROM orders WHERE id = :id FOR UPDATE');
            $orderStmt->execute(['id' => $orderId]);
            $order = $orderStmt->fetch();

            if (!$order) {
                $this->pdo->rollBack();
                return ['success' => false, 'gateway_url' => null, 'tran_id' => null, 'error' => 'Order not found.'];
            }

            if ($order['payment_status'] === 'paid') {
                $this->pdo->rollBack();
                return ['success' => false, 'gateway_url' => null, 'tran_id' => null, 'error' => 'Order is already marked as paid.'];
            }

            // 2. Determine attempt counter
            $attStmt = $this->pdo->prepare('SELECT COUNT(*) FROM payments WHERE order_id = :oid');
            $attStmt->execute(['oid' => $orderId]);
            $attemptCount = (int)$attStmt->fetchColumn() + 1;
            $attemptId = sprintf('PAY-%d-%02d', $orderId, $attemptCount);

            // 3. Generate authoritative transaction ID
            $tranId = $this->generateTransactionId($orderId, $attemptCount);
            $amount = (float)$order['total_amount'];
            $currency = 'BDT';

            // 4. Insert initial payment record in INITIATED status
            $insPayment = $this->pdo->prepare('
                INSERT INTO payments (
                    order_id, user_id, payment_attempt_id, provider, payment_method,
                    tran_id, amount, currency, status, verification_status,
                    initiated_at, created_at, updated_at
                ) VALUES (
                    :oid, :uid, :attempt_id, :provider, :method,
                    :tran_id, :amount, :currency, \'INITIATED\', \'UNVERIFIED\',
                    NOW(), NOW(), NOW()
                )
            ');
            $insPayment->execute([
                'oid'        => $orderId,
                'uid'        => $userId,
                'attempt_id' => $attemptId,
                'provider'   => 'sslcommerz',
                'method'     => $paymentMethod,
                'tran_id'    => $tranId,
                'amount'     => number_format($amount, 2, '.', ''),
                'currency'   => $currency,
            ]);
            $paymentId = (int)$this->pdo->lastInsertId();

            // 5. Audit Log initiation
            $this->logAudit((int)$paymentId, $orderId, $tranId, 'SESSION_INITIATED', null, 'INITIATED', $amount, $currency);

            // 6. Fetch user & address details for gateway payload
            $userStmt = $this->pdo->prepare('SELECT full_name as name, email, phone FROM users WHERE id = ?');
            $userStmt->execute([$userId]);
            $user = $userStmt->fetch() ?: ['name' => 'Customer', 'email' => 'customer@groco.com.bd', 'phone' => '01700000000'];

            $cusName = $shippingAddress['name'] ?? $user['name'] ?? 'Customer';
            $cusEmail = !empty($user['email']) ? $user['email'] : 'customer@groco.com.bd';
            $cusPhone = $shippingAddress['phone'] ?? $user['phone'] ?? '01700000000';
            $cusAddress = $shippingAddress['address1'] ?? 'Dhaka, Bangladesh';
            $cusCity = $shippingAddress['city'] ?? 'Dhaka';
            $cusPostcode = $shippingAddress['postal'] ?? '1000';

            // Fetch order items summary for gateway description
            $itemsStmt = $this->pdo->prepare('SELECT product_name, quantity FROM order_items WHERE order_id = ?');
            $itemsStmt->execute([$orderId]);
            $items = $itemsStmt->fetchAll();
            $itemNames = array_column($items, 'product_name');
            $productSummary = !empty($itemNames) ? implode(', ', array_slice($itemNames, 0, 3)) : 'Groceries';
            $numItems = count($items) > 0 ? count($items) : 1;

            // Configure return URLs
            $baseUrl = defined('BASE_URL') ? BASE_URL : (getenv('APP_URL') ?: 'http://localhost:8080/grocery-store');
            $successUrl = getenv('SSLCOMMERZ_SUCCESS_URL') ?: rtrim($baseUrl, '/') . '/public/payment/sslcommerz/success.php';
            $failUrl    = getenv('SSLCOMMERZ_FAIL_URL') ?: rtrim($baseUrl, '/') . '/public/payment/sslcommerz/fail.php';
            $cancelUrl  = getenv('SSLCOMMERZ_CANCEL_URL') ?: rtrim($baseUrl, '/') . '/public/payment/sslcommerz/cancel.php';
            $ipnUrl     = getenv('SSLCOMMERZ_IPN_URL') ?: rtrim($baseUrl, '/') . '/public/payment/sslcommerz/ipn.php';

            $sessionPayload = [
                'total_amount'     => $amount,
                'tran_id'          => $tranId,
                'success_url'      => $successUrl,
                'fail_url'         => $failUrl,
                'cancel_url'       => $cancelUrl,
                'ipn_url'          => $ipnUrl,
                'cus_name'         => $cusName,
                'cus_email'        => $cusEmail,
                'cus_phone'        => $cusPhone,
                'cus_add1'         => $cusAddress,
                'cus_city'         => $cusCity,
                'cus_postcode'     => $cusPostcode,
                'cus_country'      => 'Bangladesh',
                'num_of_item'      => $numItems,
                'product_name'     => $productSummary,
                'product_category' => 'Grocery',
                'value_a'          => (string)$orderId,
                'value_b'          => (string)$userId,
                'value_c'          => $attemptId,
            ];

            // 7. Request Session from SSLCOMMERZ
            $gwResult = $this->sslcommerz->createSession($sessionPayload);

            if ($gwResult['success'] && !empty($gwResult['gateway_url'])) {
                // Update payment to PENDING with sessionkey
                $upPayment = $this->pdo->prepare('
                    UPDATE payments 
                    SET sessionkey = :skey, status = \'PENDING\', gateway_status = \'PENDING\', 
                        metadata = :meta, updated_at = NOW()
                    WHERE id = :id
                ');
                $upPayment->execute([
                    'skey' => $gwResult['sessionkey'],
                    'meta' => json_encode(SSLCommerzService::redactCredentials($gwResult['raw'])),
                    'id'   => $paymentId
                ]);

                $this->logAudit((int)$paymentId, $orderId, $tranId, 'GATEWAY_SESSION_CREATED', 'INITIATED', 'PENDING', $amount, $currency);
                $this->pdo->commit();

                return [
                    'success'     => true,
                    'gateway_url' => $gwResult['gateway_url'],
                    'tran_id'     => $tranId,
                    'error'       => null
                ];
            }

            // Session creation failed
            $failureReason = $gwResult['error'] ?? 'Gateway session rejected.';
            $upFail = $this->pdo->prepare('
                UPDATE payments 
                SET status = \'FAILED\', failure_reason = :reason, failed_at = NOW(), updated_at = NOW()
                WHERE id = :id
            ');
            $upFail->execute(['reason' => $failureReason, 'id' => $paymentId]);

            $this->logAudit((int)$paymentId, $orderId, $tranId, 'SESSION_CREATION_FAILED', 'INITIATED', 'FAILED', $amount, $currency, null, $failureReason);
            $this->pdo->commit();

            return [
                'success'     => false,
                'gateway_url' => null,
                'tran_id'     => $tranId,
                'error'       => $failureReason
            ];

        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('[PaymentService::initiateOnlinePayment] Exception: ' . $e->getMessage());
            return [
                'success'     => false,
                'gateway_url' => null,
                'tran_id'     => null,
                'error'       => 'Payment initiation error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Authoritative Server Validation & Finalization Pipeline
     * Handles both Return Callback and IPN notifications idempotently.
     *
     * @param string $valId Validation ID from SSLCOMMERZ
     * @param string|null $tranId Merchant Transaction ID
     * @param array<string, mixed> $rawPayload Raw callback/IPN payload
     * @param string $source 'callback' | 'ipn'
     * @return array{success: bool, status: string, message: string, order: ?array, payment: ?array}
     */
    public function validateAndFinalizePayment(string $valId, ?string $tranId = null, array $rawPayload = [], string $source = 'callback'): array
    {
        if (trim($valId) === '') {
            return [
                'success' => false,
                'status'  => 'INVALID_VAL_ID',
                'message' => 'val_id is missing from gateway notification.',
                'order'   => null,
                'payment' => null
            ];
        }

        // Record incoming webhook / callback event for replay protection
        $this->recordWebhookEvent($source, $tranId, $valId, $rawPayload);

        try {
            $this->pdo->beginTransaction();

            // 1. Locate payment record
            $payment = null;
            if ($tranId !== null && trim($tranId) !== '') {
                $stmt = $this->pdo->prepare('SELECT * FROM payments WHERE tran_id = :tid FOR UPDATE');
                $stmt->execute(['tid' => trim($tranId)]);
                $payment = $stmt->fetch();
            }

            // Fallback search by val_id if tran_id not provided
            if (!$payment) {
                $stmt = $this->pdo->prepare('SELECT * FROM payments WHERE val_id = :vid FOR UPDATE');
                $stmt->execute(['vid' => trim($valId)]);
                $payment = $stmt->fetch();
            }

            // If payment still not found, execute validation with gateway first to discover tran_id
            if (!$payment) {
                $gwVal = $this->sslcommerz->validateOrder($valId);
                if ($gwVal['success'] && !empty($gwVal['data']['tran_id'])) {
                    $discoveredTranId = (string)$gwVal['data']['tran_id'];
                    $stmt = $this->pdo->prepare('SELECT * FROM payments WHERE tran_id = :tid FOR UPDATE');
                    $stmt->execute(['tid' => $discoveredTranId]);
                    $payment = $stmt->fetch();
                }
            }

            if (!$payment) {
                $this->pdo->rollBack();
                $this->logAudit(null, null, $tranId, 'UNKNOWN_TRANSACTION_ORPHAN', null, null, null, null, json_encode(SSLCommerzService::redactCredentials($rawPayload)), 'No internal payment matched tran_id or val_id.');
                return [
                    'success' => false,
                    'status'  => 'NOT_FOUND',
                    'message' => 'Transaction not recognized in internal ledger.',
                    'order'   => null,
                    'payment' => null
                ];
            }

            $paymentId = (int)$payment['id'];
            $orderId   = (int)$payment['order_id'];
            $oldStatus = (string)$payment['status'];

            // 2. Fetch and lock order row
            $orderStmt = $this->pdo->prepare('SELECT * FROM orders WHERE id = :id FOR UPDATE');
            $orderStmt->execute(['id' => $orderId]);
            $order = $orderStmt->fetch();

            if (!$order) {
                $this->pdo->rollBack();
                return [
                    'success' => false,
                    'status'  => 'ORDER_NOT_FOUND',
                    'message' => 'Associated order not found in database.',
                    'order'   => null,
                    'payment' => null
                ];
            }

            // 3. IDEMPOTENCY CHECK: If already PAID, do not re-process or double-decrement stock!
            if ($oldStatus === 'PAID') {
                // If this is IPN arriving after browser success, acknowledge IPN flag
                if ($source === 'ipn' && (int)$payment['ipn_received'] === 0) {
                    $this->pdo->prepare('UPDATE payments SET ipn_received = 1, ipn_received_at = NOW() WHERE id = ?')
                        ->execute([$paymentId]);
                }
                $this->pdo->commit();
                $this->logAudit($paymentId, $orderId, (string)$payment['tran_id'], 'IDEMPOTENT_DUPLICATE_ACCEPTED', 'PAID', 'PAID', (float)$payment['amount'], (string)$payment['currency'], json_encode(['source' => $source]));
                return [
                    'success' => true,
                    'status'  => 'ALREADY_PAID',
                    'message' => 'Payment was already processed and verified.',
                    'order'   => $order,
                    'payment' => $payment
                ];
            }

            // 4. Call SSLCOMMERZ Order Validation API
            $gwValidation = $this->sslcommerz->validateOrder($valId);
            $valData = $gwValidation['data'] ?? [];

            if (!$gwValidation['success']) {
                // Gateway validation rejected
                $failReason = $gwValidation['error'] ?? 'Order Validation API rejected the transaction.';
                $this->pdo->prepare('
                    UPDATE payments 
                    SET status = \'FAILED\', verification_status = \'FAILED\', 
                        gateway_status = :gw_status, failure_reason = :reason, failed_at = NOW(), updated_at = NOW()
                    WHERE id = :id
                ')->execute([
                    'gw_status' => $valData['status'] ?? 'INVALID',
                    'reason'    => $failReason,
                    'id'        => $paymentId
                ]);

                $this->logAudit($paymentId, $orderId, (string)$payment['tran_id'], 'VALIDATION_REJECTED', $oldStatus, 'FAILED', (float)$payment['amount'], (string)$payment['currency'], json_encode(SSLCommerzService::redactCredentials($valData)), $failReason);
                $this->pdo->commit();

                return [
                    'success' => false,
                    'status'  => 'VALIDATION_REJECTED',
                    'message' => $failReason,
                    'order'   => $order,
                    'payment' => $payment
                ];
            }

            // 5. CRITICAL SECURITY CHECKS: Transaction Identity, Amount, Currency
            $validatedTranId   = (string)($valData['tran_id'] ?? '');
            $validatedAmount   = (float)($valData['amount'] ?? 0.0);
            $validatedCurrency = strtoupper((string)($valData['currency'] ?? ''));
            $expectedAmount    = (float)$payment['amount'];
            $expectedCurrency  = strtoupper((string)$payment['currency']);

            // 5A. Transaction Identity Check
            if ($validatedTranId !== (string)$payment['tran_id']) {
                $mismatchReason = sprintf('Transaction ID mismatch: gateway=%s, internal=%s', $validatedTranId, $payment['tran_id']);
                $this->pdo->prepare('
                    UPDATE payments 
                    SET status = \'REVIEW_REQUIRED\', verification_status = \'TRANSACTION_MISMATCH\', 
                        failure_reason = :reason, updated_at = NOW()
                    WHERE id = :id
                ')->execute(['reason' => $mismatchReason, 'id' => $paymentId]);

                $this->logAudit($paymentId, $orderId, (string)$payment['tran_id'], 'SECURITY_TRAN_ID_MISMATCH', $oldStatus, 'REVIEW_REQUIRED', $expectedAmount, $expectedCurrency, json_encode(SSLCommerzService::redactCredentials($valData)), $mismatchReason);
                $this->pdo->commit();

                return [
                    'success' => false,
                    'status'  => 'TRAN_MISMATCH',
                    'message' => 'Critical security failure: Transaction ID mismatch.',
                    'order'   => $order,
                    'payment' => $payment
                ];
            }

            // 5B. Currency Matching
            if ($validatedCurrency !== $expectedCurrency) {
                $curMismatchReason = sprintf('Currency mismatch: gateway=%s, expected=%s', $validatedCurrency, $expectedCurrency);
                $this->pdo->prepare('
                    UPDATE payments 
                    SET status = \'REVIEW_REQUIRED\', verification_status = \'CURRENCY_MISMATCH\', 
                        failure_reason = :reason, updated_at = NOW()
                    WHERE id = :id
                ')->execute(['reason' => $curMismatchReason, 'id' => $paymentId]);

                $this->logAudit($paymentId, $orderId, (string)$payment['tran_id'], 'SECURITY_CURRENCY_MISMATCH', $oldStatus, 'REVIEW_REQUIRED', $expectedAmount, $expectedCurrency, json_encode(SSLCommerzService::redactCredentials($valData)), $curMismatchReason);
                $this->pdo->commit();

                return [
                    'success' => false,
                    'status'  => 'CURRENCY_MISMATCH',
                    'message' => 'Critical security failure: Currency mismatch.',
                    'order'   => $order,
                    'payment' => $payment
                ];
            }

            // 5C. Zero-Float Amount Matching (Precision check to 0.01 BDT)
            if (abs($validatedAmount - $expectedAmount) >= 0.01) {
                $amtMismatchReason = sprintf('AMOUNT MISMATCH ALERT: Internal=%.2f, Gateway=%.2f', $expectedAmount, $validatedAmount);
                $this->pdo->prepare('
                    UPDATE payments 
                    SET status = \'REVIEW_REQUIRED\', verification_status = \'AMOUNT_MISMATCH\', 
                        failure_reason = :reason, updated_at = NOW()
                    WHERE id = :id
                ')->execute(['reason' => $amtMismatchReason, 'id' => $paymentId]);

                $this->logAudit($paymentId, $orderId, (string)$payment['tran_id'], 'AMOUNT_MISMATCH_SECURITY_ALERT', $oldStatus, 'REVIEW_REQUIRED', $expectedAmount, $expectedCurrency, json_encode(SSLCommerzService::redactCredentials($valData)), $amtMismatchReason);
                $this->pdo->commit();

                return [
                    'success' => false,
                    'status'  => 'AMOUNT_MISMATCH',
                    'message' => 'Critical security failure: Amount mismatch between gateway and internal order.',
                    'order'   => $order,
                    'payment' => $payment
                ];
            }

            // 6. Extract gateway details
            $bankTranId  = (string)($valData['bank_tran_id'] ?? '');
            $cardType    = (string)($valData['card_type'] ?? '');
            $cardNo      = (string)($valData['card_no'] ?? '');
            $storeAmount = isset($valData['store_amount']) ? (float)$valData['store_amount'] : null;
            $riskLevel   = (string)($valData['risk_level'] ?? '0');
            $riskTitle   = (string)($valData['risk_title'] ?? 'Safe');
            $ipnFlag     = ($source === 'ipn' || (int)$payment['ipn_received'] === 1) ? 1 : 0;

            // 7. Update Payment to PAID
            $ipnReceivedAtSql = $ipnFlag === 1 ? 'NOW()' : 'ipn_received_at';
            $upSuccess = $this->pdo->prepare("
                UPDATE payments 
                SET status = 'PAID', verification_status = 'VALIDATED', gateway_status = :gw_status,
                    bank_tran_id = :bank_tran, card_type = :card_type, card_no = :card_no,
                    val_id = :val_id, store_amount = :store_amt, risk_level = :risk_lvl,
                    risk_title = :risk_title, paid_at = NOW(), ipn_received = :ipn_flag,
                    ipn_received_at = {$ipnReceivedAtSql},
                    metadata = :meta, updated_at = NOW()
                WHERE id = :id
            ");
            $upSuccess->execute([
                'gw_status'  => (string)($valData['status'] ?? 'VALID'),
                'bank_tran'  => $bankTranId,
                'card_type'  => $cardType,
                'card_no'    => $cardNo,
                'val_id'     => $valId,
                'store_amt'  => $storeAmount,
                'risk_lvl'   => $riskLevel,
                'risk_title' => $riskTitle,
                'ipn_flag'   => $ipnFlag,
                'meta'       => json_encode(SSLCommerzService::redactCredentials($valData)),
                'id'         => $paymentId
            ]);

            // 8. Update Order payment_status to 'paid' and status to 'processing'
            $orderUp = $this->pdo->prepare('
                UPDATE orders 
                SET payment_status = \'paid\', status = \'processing\', updated_at = NOW()
                WHERE id = :id
            ');
            $orderUp->execute(['id' => $orderId]);

            // 9. INVENTORY FINALIZATION: Ensure inventory is decremented without double-deduction
            $this->finalizeInventoryForOrder($orderId);

            // 10. Ledger income entry (Accounting & Finance sync)
            $this->recordLedgerTransaction($orderId, (string)$order['order_number'], $expectedAmount, $payment['payment_method'], $payment['tran_id'], $bankTranId);

            // 11. Add Order Status History entry
            $this->pdo->prepare('
                INSERT INTO order_status_history (order_id, status, note, created_at)
                VALUES (:oid, \'processing\', :note, NOW())
            ')->execute([
                'oid'  => $orderId,
                'note' => sprintf('Online payment completed via SSLCOMMERZ (%s). Bank Tran: %s', $cardType, $bankTranId)
            ]);

            // 12. Create Customer In-App Notification
            $this->pdo->prepare('
                INSERT INTO notifications (user_id, title, message, type, is_read, created_at)
                VALUES (:uid, :title, :msg, \'order\', 0, NOW())
            ')->execute([
                'uid'   => (int)$order['user_id'],
                'title' => 'Payment Received!',
                'msg'   => sprintf('Your payment of ৳%.2f for Order #%s was verified and received successfully.', $expectedAmount, $order['order_number'])
            ]);

            // 13. Audit Log
            $this->logAudit($paymentId, $orderId, (string)$payment['tran_id'], 'PAYMENT_VERIFIED_PAID', $oldStatus, 'PAID', $expectedAmount, $expectedCurrency, json_encode([
                'val_id'       => $valId,
                'bank_tran_id' => $bankTranId,
                'card_type'    => $cardType,
                'source'       => $source
            ]));

            $this->pdo->commit();

            // Refresh records
            $orderStmt->execute(['id' => $orderId]);
            $refreshedOrder = $orderStmt->fetch();
            $paymentStmt = $this->pdo->prepare('SELECT * FROM payments WHERE id = ?');
            $paymentStmt->execute([$paymentId]);
            $refreshedPayment = $paymentStmt->fetch();

            return [
                'success' => true,
                'status'  => 'PAID',
                'message' => 'Payment successfully verified and finalized.',
                'order'   => $refreshedOrder,
                'payment' => $refreshedPayment
            ];

        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('[PaymentService::validateAndFinalizePayment] Error: ' . $e->getMessage());
            return [
                'success' => false,
                'status'  => 'INTERNAL_ERROR',
                'message' => 'Internal server error during payment finalization: ' . $e->getMessage(),
                'order'   => null,
                'payment' => null
            ];
        }
    }

    /**
     * Mark payment as failed upon explicit failure notification from gateway
     */
    public function handlePaymentFailure(string $tranId, string $reason = 'Payment failed at gateway', array $rawPayload = []): array
    {
        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare('SELECT * FROM payments WHERE tran_id = :tid FOR UPDATE');
            $stmt->execute(['tid' => trim($tranId)]);
            $payment = $stmt->fetch();

            if (!$payment) {
                $this->pdo->rollBack();
                return ['success' => false, 'error' => 'Payment transaction not found.'];
            }

            // DO NOT overwrite if already PAID!
            if ($payment['status'] === 'PAID') {
                $this->pdo->rollBack();
                return ['success' => true, 'already_paid' => true];
            }

            $paymentId = (int)$payment['id'];
            $orderId   = (int)$payment['order_id'];
            $oldStatus = (string)$payment['status'];

            $up = $this->pdo->prepare('
                UPDATE payments 
                SET status = \'FAILED\', verification_status = \'FAILED\', failure_reason = :reason,
                    failed_at = NOW(), metadata = :meta, updated_at = NOW()
                WHERE id = :id
            ');
            $up->execute([
                'reason' => $reason,
                'meta'   => json_encode(SSLCommerzService::redactCredentials($rawPayload)),
                'id'     => $paymentId
            ]);

            // Keep order payment_status as 'failed' or 'unpaid' so user can retry
            $this->pdo->prepare('UPDATE orders SET payment_status = \'failed\', updated_at = NOW() WHERE id = ?')
                ->execute([$orderId]);

            $this->logAudit($paymentId, $orderId, $tranId, 'PAYMENT_FAILED', $oldStatus, 'FAILED', (float)$payment['amount'], (string)$payment['currency'], json_encode(SSLCommerzService::redactCredentials($rawPayload)), $reason);

            $this->pdo->commit();
            return ['success' => true, 'order_id' => $orderId];

        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('[PaymentService::handlePaymentFailure] ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Mark payment as cancelled upon customer cancellation
     */
    public function handlePaymentCancel(string $tranId, array $rawPayload = []): array
    {
        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare('SELECT * FROM payments WHERE tran_id = :tid FOR UPDATE');
            $stmt->execute(['tid' => trim($tranId)]);
            $payment = $stmt->fetch();

            if (!$payment) {
                $this->pdo->rollBack();
                return ['success' => false, 'error' => 'Payment transaction not found.'];
            }

            // DO NOT overwrite if already PAID
            if ($payment['status'] === 'PAID') {
                $this->pdo->rollBack();
                return ['success' => true, 'already_paid' => true];
            }

            $paymentId = (int)$payment['id'];
            $orderId   = (int)$payment['order_id'];
            $oldStatus = (string)$payment['status'];

            $up = $this->pdo->prepare('
                UPDATE payments 
                SET status = \'CANCELLED\', verification_status = \'CANCELLED\',
                    cancelled_at = NOW(), metadata = :meta, updated_at = NOW()
                WHERE id = :id
            ');
            $up->execute([
                'meta' => json_encode(SSLCommerzService::redactCredentials($rawPayload)),
                'id'   => $paymentId
            ]);

            $this->logAudit($paymentId, $orderId, $tranId, 'PAYMENT_CANCELLED', $oldStatus, 'CANCELLED', (float)$payment['amount'], (string)$payment['currency']);

            $this->pdo->commit();
            return ['success' => true, 'order_id' => $orderId];

        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('[PaymentService::handlePaymentCancel] ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Process Full or Partial Refund for a payment
     *
     * @param int $paymentId
     * @param float $refundAmount
     * @param string $reason
     * @param int|null $adminId
     * @return array{success: bool, status: string, refund_trans_id: ?string, error: ?string}
     */
    public function processRefund(int $paymentId, float $refundAmount, string $reason, ?int $adminId = null): array
    {
        if ($refundAmount <= 0) {
            return ['success' => false, 'status' => 'INVALID_AMOUNT', 'refund_trans_id' => null, 'error' => 'Refund amount must be greater than zero.'];
        }

        try {
            $this->pdo->beginTransaction();

            $pStmt = $this->pdo->prepare('SELECT * FROM payments WHERE id = :id FOR UPDATE');
            $pStmt->execute(['id' => $paymentId]);
            $payment = $pStmt->fetch();

            if (!$payment) {
                $this->pdo->rollBack();
                return ['success' => false, 'status' => 'NOT_FOUND', 'refund_trans_id' => null, 'error' => 'Payment record not found.'];
            }

            if (!in_array($payment['status'], ['PAID', 'PARTIALLY_REFUNDED'], true)) {
                $this->pdo->rollBack();
                return ['success' => false, 'status' => 'NOT_ELIGIBLE', 'refund_trans_id' => null, 'error' => "Only PAID or PARTIALLY_REFUNDED payments can be refunded. Current status: {$payment['status']}"];
            }

            if (empty($payment['bank_tran_id'])) {
                $this->pdo->rollBack();
                return ['success' => false, 'status' => 'NO_BANK_TRAN', 'refund_trans_id' => null, 'error' => 'Missing bank transaction ID required by gateway refund API.'];
            }

            $paidAmount = (float)$payment['amount'];
            $alreadyRefunded = (float)$payment['refunded_amount'];
            $maxRefundable = round($paidAmount - $alreadyRefunded, 2);

            if ($refundAmount > $maxRefundable) {
                $this->pdo->rollBack();
                return [
                    'success'         => false,
                    'status'          => 'OVER_REFUND',
                    'refund_trans_id' => null,
                    'error'           => sprintf('Requested refund (৳%.2f) exceeds maximum refundable balance (৳%.2f).', $refundAmount, $maxRefundable)
                ];
            }

            // Generate unique GroCo refund transaction ID
            $refundTransId = sprintf('REF-%d-%s', $payment['order_id'], strtoupper(bin2hex(random_bytes(4))));

            // Insert pending refund record
            $insRef = $this->pdo->prepare('
                INSERT INTO payment_refunds (
                    payment_id, order_id, refund_trans_id, bank_tran_id,
                    refund_amount, reason, status, requested_by, requested_at, created_at, updated_at
                ) VALUES (
                    :pid, :oid, :ref_trn, :bank_tran,
                    :amt, :reason, \'PROCESSING\', :admin_id, NOW(), NOW(), NOW()
                )
            ');
            $insRef->execute([
                'pid'       => $paymentId,
                'oid'       => $payment['order_id'],
                'ref_trn'   => $refundTransId,
                'bank_tran' => $payment['bank_tran_id'],
                'amt'       => number_format($refundAmount, 2, '.', ''),
                'reason'    => $reason,
                'admin_id'  => $adminId
            ]);
            $refundRecordId = (int)$this->pdo->lastInsertId();

            // Call SSLCOMMERZ Refund API
            $gwRefund = $this->sslcommerz->initiateRefund(
                (string)$payment['bank_tran_id'],
                $refundAmount,
                $reason,
                $refundTransId
            );

            if (!$gwRefund['success']) {
                $failError = $gwRefund['error'] ?? 'Gateway rejected refund.';
                $this->pdo->prepare('
                    UPDATE payment_refunds 
                    SET status = \'FAILED\', error_reason = :err, gateway_response = :resp, updated_at = NOW()
                    WHERE id = :id
                ')->execute([
                    'err'  => $failError,
                    'resp' => json_encode(SSLCommerzService::redactCredentials($gwRefund['data'])),
                    'id'   => $refundRecordId
                ]);

                $this->logAudit($paymentId, (int)$payment['order_id'], (string)$payment['tran_id'], 'REFUND_REJECTED', (string)$payment['status'], (string)$payment['status'], $refundAmount, (string)$payment['currency'], json_encode($gwRefund['data']), $failError);
                $this->pdo->commit();

                return [
                    'success'         => false,
                    'status'          => 'GATEWAY_ERROR',
                    'refund_trans_id' => $refundTransId,
                    'error'           => $failError
                ];
            }

            // Refund accepted by gateway
            $newTotalRefunded = round($alreadyRefunded + $refundAmount, 2);
            $isFullyRefunded = ($newTotalRefunded >= $paidAmount);
            $newPaymentStatus = $isFullyRefunded ? 'REFUNDED' : 'PARTIALLY_REFUNDED';
            $newOrderStatus   = $isFullyRefunded ? 'refunded' : 'partially_refunded';

            // Update refund record
            $this->pdo->prepare('
                UPDATE payment_refunds 
                SET status = \'COMPLETED\', refund_ref_id = :ref_ref, gateway_response = :resp,
                    completed_at = NOW(), updated_at = NOW()
                WHERE id = :id
            ')->execute([
                'ref_ref' => $gwRefund['refund_ref_id'] ?? null,
                'resp'    => json_encode(SSLCommerzService::redactCredentials($gwRefund['data'])),
                'id'      => $refundRecordId
            ]);

            // Update payment record
            $this->pdo->prepare('
                UPDATE payments 
                SET refunded_amount = :ref_amt, status = :pstatus, refund_status = :rstatus, updated_at = NOW()
                WHERE id = :id
            ')->execute([
                'ref_amt' => number_format($newTotalRefunded, 2, '.', ''),
                'pstatus' => $newPaymentStatus,
                'rstatus' => $newPaymentStatus,
                'id'      => $paymentId
            ]);

            // Update order payment status
            $this->pdo->prepare('UPDATE orders SET payment_status = :ostatus, updated_at = NOW() WHERE id = :id')
                ->execute(['ostatus' => $newOrderStatus, 'id' => $payment['order_id']]);

            // Record expense/refund transaction in ledger
            $this->pdo->prepare('
                INSERT INTO transactions (type, category_id, amount, reference, payment_method, reconciled, created_at)
                VALUES (\'expense\', NULL, :amt, :ref, :method, 1, NOW())
            ')->execute([
                'amt'    => number_format($refundAmount, 2, '.', ''),
                'ref'    => sprintf('SSLCOMMERZ Refund for Order #%d (Ref: %s)', $payment['order_id'], $refundTransId),
                'method' => 'sslcommerz'
            ]);

            $this->logAudit($paymentId, (int)$payment['order_id'], (string)$payment['tran_id'], 'REFUND_COMPLETED', (string)$payment['status'], $newPaymentStatus, $refundAmount, (string)$payment['currency'], json_encode([
                'refund_trans_id' => $refundTransId,
                'refund_ref_id'   => $gwRefund['refund_ref_id'] ?? null,
                'refund_amount'   => $refundAmount,
                'new_status'      => $newPaymentStatus
            ]));

            $this->pdo->commit();

            return [
                'success'         => true,
                'status'          => $newPaymentStatus,
                'refund_trans_id' => $refundTransId,
                'error'           => null
            ];

        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('[PaymentService::processRefund] Error: ' . $e->getMessage());
            return [
                'success'         => false,
                'status'          => 'INTERNAL_ERROR',
                'refund_trans_id' => null,
                'error'           => $e->getMessage()
            ];
        }
    }

    /**
     * Query gateway and synchronize transaction status with internal DB
     */
    public function syncTransactionStatus(string $tranId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM payments WHERE tran_id = ?');
        $stmt->execute([trim($tranId)]);
        $payment = $stmt->fetch();

        if (!$payment) {
            return ['success' => false, 'error' => 'Payment not found.'];
        }

        $gwQuery = $this->sslcommerz->queryTransaction($tranId);
        if (!$gwQuery['success']) {
            return ['success' => false, 'error' => $gwQuery['error'] ?? 'Gateway query failed.'];
        }

        $data = $gwQuery['data'];
        $gwStatus = strtoupper((string)($data['status'] ?? ''));

        if ($gwStatus === 'VALID' || $gwStatus === 'VALIDATED') {
            $valId = (string)($data['val_id'] ?? '');
            if ($valId !== '') {
                return $this->validateAndFinalizePayment($valId, $tranId, $data, 'sync_query');
            }
        }

        return [
            'success'        => true,
            'gateway_status' => $gwStatus,
            'data'           => $data
        ];
    }

    /**
     * Inventory Finalization Guard:
     * Ensures stock is decremented exactly once per order (preventing double deduction).
     */
    private function finalizeInventoryForOrder(int $orderId): void
    {
        $checkStmt = $this->pdo->prepare('SELECT inventory_deducted FROM orders WHERE id = ? FOR UPDATE');
        $checkStmt->execute([$orderId]);
        $alreadyDeducted = (int)$checkStmt->fetchColumn();

        if ($alreadyDeducted === 0) {
            // Deduct stock for all items
            $itemsStmt = $this->pdo->prepare('SELECT product_id, quantity FROM order_items WHERE order_id = ?');
            $itemsStmt->execute([$orderId]);
            $items = $itemsStmt->fetchAll();

            $stockUpdate = $this->pdo->prepare('UPDATE products SET stock = GREATEST(0, stock - :qty) WHERE id = :id');
            foreach ($items as $item) {
                $stockUpdate->execute([
                    'qty' => (int)$item['quantity'],
                    'id'  => (int)$item['product_id']
                ]);
            }

            // Mark order inventory as deducted
            $this->pdo->prepare('UPDATE orders SET inventory_deducted = 1 WHERE id = ?')->execute([$orderId]);
        }
    }

    /**
     * Ledger income recorder (Double-entry accounting integration)
     */
    private function recordLedgerTransaction(int $orderId, string $orderNumber, float $amount, string $method, string $tranId, string $bankTran): void
    {
        // Avoid duplicate ledger insertions
        $check = $this->pdo->prepare('SELECT COUNT(*) FROM transactions WHERE reference LIKE :ref');
        $check->execute(['ref' => "%#{$orderNumber}%"]);
        if ((int)$check->fetchColumn() === 0) {
            $this->pdo->prepare('
                INSERT INTO transactions (type, category_id, amount, reference, payment_method, reconciled, created_at)
                VALUES (\'income\', NULL, :amt, :ref, :method, 1, NOW())
            ')->execute([
                'amt'    => number_format($amount, 2, '.', ''),
                'ref'    => sprintf('Online Payment for Order #%s (Txn: %s, Bank: %s)', $orderNumber, $tranId, $bankTran),
                'method' => $method
            ]);
        }
    }

    /**
     * Record raw incoming webhook / callback payload for replay detection
     */
    private function recordWebhookEvent(string $type, ?string $tranId, ?string $valId, array $payload): void
    {
        try {
            $jsonPayload = json_encode(SSLCommerzService::redactCredentials($payload));
            $hash = hash('sha256', $jsonPayload);

            $stmt = $this->pdo->prepare('
                INSERT INTO payment_webhook_events (event_type, tran_id, val_id, payload_hash, payload, ip_address, created_at)
                VALUES (:type, :tid, :vid, :hash, :payload, :ip, NOW())
            ');
            $stmt->execute([
                'type'    => strtoupper($type),
                'tid'     => $tranId,
                'vid'     => $valId,
                'hash'    => $hash,
                'payload' => $jsonPayload,
                'ip'      => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
            ]);
        } catch (Throwable $e) {
            error_log('[PaymentService::recordWebhookEvent] ' . $e->getMessage());
        }
    }

    /**
     * Append to immutable audit trail table
     */
    public function logAudit(
        ?int $paymentId,
        ?int $orderId,
        ?string $tranId,
        string $event,
        ?string $oldStatus,
        ?string $newStatus,
        ?float $amount = null,
        ?string $currency = null,
        ?string $payload = null,
        ?string $errorMessage = null
    ): void {
        try {
            $stmt = $this->pdo->prepare('
                INSERT INTO payment_audit_logs (
                    payment_id, order_id, tran_id, event, provider,
                    old_status, new_status, amount, currency,
                    ip_address, user_agent, payload, error_message, created_at
                ) VALUES (
                    :pid, :oid, :tid, :event, \'sslcommerz\',
                    :old_st, :new_st, :amt, :cur,
                    :ip, :ua, :payload, :err, NOW()
                )
            ');
            $stmt->execute([
                'pid'     => $paymentId,
                'oid'     => $orderId,
                'tid'     => $tranId,
                'event'   => $event,
                'old_st'  => $oldStatus,
                'new_st'  => $newStatus,
                'amt'     => $amount !== null ? number_format($amount, 2, '.', '') : null,
                'cur'     => $currency ?? 'BDT',
                'ip'      => $_SERVER['REMOTE_ADDR'] ?? 'CLI',
                'ua'      => substr($_SERVER['HTTP_USER_AGENT'] ?? 'CLI/System', 0, 255),
                'payload' => $payload,
                'err'     => $errorMessage
            ]);
        } catch (Throwable $e) {
            error_log('[PaymentService::logAudit] ' . $e->getMessage());
        }
    }
}
