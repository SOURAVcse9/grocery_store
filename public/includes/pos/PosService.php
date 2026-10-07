<?php
/**
 * ==============================================================================
 * GroCo Enterprise POS — Master Domain Subsystem Facade
 * ==============================================================================
 * Unified facade coordinating Cart, Inventory, Payment, Transaction, Customer,
 * Shift, Return, Sync, Receipt, and Audit services.
 * ==============================================================================
 */

declare(strict_types=1);

namespace Groco\Pos;

use PDO;

class PosService
{
    private PDO $pdo;
    private PosCartService $cartService;
    private PosInventoryService $inventoryService;
    private PosPaymentService $paymentService;
    private PosCustomerService $customerService;
    private PosDiscountService $discountService;
    private PosShiftService $shiftService;
    private PosTransactionService $transactionService;
    private PosReturnService $returnService;
    private PosSyncService $syncService;
    private PosReceiptService $receiptService;
    private PosAuditService $auditService;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->auditService = new PosAuditService($pdo);
        $this->cartService = new PosCartService();
        $this->inventoryService = new PosInventoryService($pdo);
        $this->paymentService = new PosPaymentService($pdo);
        $this->customerService = new PosCustomerService($pdo);
        $this->discountService = new PosDiscountService($pdo);
        $this->shiftService = new PosShiftService($pdo, $this->auditService);
        $this->transactionService = new PosTransactionService(
            $pdo,
            $this->cartService,
            $this->inventoryService,
            $this->paymentService,
            $this->customerService,
            $this->discountService,
            $this->auditService
        );
        $this->returnService = new PosReturnService($pdo, $this->inventoryService, $this->auditService);
        $this->syncService = new PosSyncService($pdo, $this->transactionService, $this->auditService);
        $this->receiptService = new PosReceiptService($pdo, $this->auditService);
    }

    public function cart(): PosCartService { return $this->cartService; }
    public function inventory(): PosInventoryService { return $this->inventoryService; }
    public function payment(): PosPaymentService { return $this->paymentService; }
    public function payments(): PosPaymentService { return $this->paymentService; }
    public function customer(): PosCustomerService { return $this->customerService; }
    public function customers(): PosCustomerService { return $this->customerService; }
    public function discount(): PosDiscountService { return $this->discountService; }
    public function discounts(): PosDiscountService { return $this->discountService; }
    public function shift(): PosShiftService { return $this->shiftService; }
    public function shifts(): PosShiftService { return $this->shiftService; }
    public function transaction(): PosTransactionService { return $this->transactionService; }
    public function transactions(): PosTransactionService { return $this->transactionService; }
    public function returns(): PosReturnService { return $this->returnService; }
    public function sync(): PosSyncService { return $this->syncService; }
    public function receipt(): PosReceiptService { return $this->receiptService; }
    public function receipts(): PosReceiptService { return $this->receiptService; }
    public function audit(): PosAuditService { return $this->auditService; }
}
