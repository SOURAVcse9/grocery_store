<?php
/**
 * ==============================================================================
 * GroCo Enterprise POS — Payment & Multi-Tender Engine
 * ==============================================================================
 * Multi-tender payment allocations, tender validation, wallet credit
 * checks, and transaction reconciliation.
 * ==============================================================================
 */

declare(strict_types=1);

namespace Groco\Pos;

use PDO;
use Exception;
use InvalidArgumentException;

class PosPaymentService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Validate and structure payment tenders against payable grand total.
     *
     * @param float $payableTotal Total amount to be paid
     * @param array $tenders Array of payments:
     *                       [
     *                          ['method' => 'cash', 'amount' => 500.00, 'tendered' => 600.00],
     *                          ['method' => 'bkash', 'amount' => 300.00, 'reference' => 'TRX123', 'provider' => 'bKash'],
     *                          ['method' => 'card', 'amount' => 200.00, 'card_type' => 'Visa', 'last_four' => '4321', 'bank' => 'DBBL']
     *                       ]
     * @param int|null $customerId
     * @return array
     */
    public function validateTenders(float $payableTotal, array $tenders, ?int $customerId = null): array
    {
        if ($payableTotal <= 0.00) {
            // 100% discount or zero total sale
            return [
                'is_valid'      => true,
                'total_paid'    => 0.00,
                'payable_total' => 0.00,
                'change_amount' => 0.00,
                'tenders'       => []
            ];
        }

        if (empty($tenders)) {
            throw new InvalidArgumentException("No payment tenders provided for checkout.");
        }

        $totalPaid = 0.00;
        $totalTendered = 0.00;
        $totalChange = 0.00;
        $validatedTenders = [];

        foreach ($tenders as $tender) {
            $method = strtolower(trim((string)($tender['method'] ?? 'cash')));
            $amount = round((float)($tender['amount'] ?? 0.00), 2);
            $tendered = round((float)($tender['tendered'] ?? $amount), 2);

            if ($amount <= 0.00) {
                continue;
            }

            if ($tendered < $amount) {
                throw new InvalidArgumentException("Tendered amount for {$method} cannot be less than allocated amount.");
            }

            $change = ($method === 'cash') ? round($tendered - $amount, 2) : 0.00;
            
            // If wallet credit used, check customer wallet balance
            if ($method === 'wallet') {
                if (!$customerId || $customerId <= 0) {
                    throw new InvalidArgumentException("Customer wallet payment requires a registered customer account.");
                }
                $stmt = $this->pdo->prepare("SELECT wallet_balance FROM users WHERE id = ? FOR UPDATE");
                $stmt->execute([$customerId]);
                $walletBal = (float)$stmt->fetchColumn();

                if ($walletBal < $amount) {
                    throw new Exception("Insufficient customer wallet balance. Available: ৳" . number_format($walletBal, 2));
                }
            }

            $totalPaid += $amount;
            $totalTendered += $tendered;
            $totalChange += $change;

            $validatedTenders[] = [
                'method'          => $method,
                'amount'          => $amount,
                'tendered_amount' => $tendered,
                'change_amount'   => $change,
                'reference_no'    => $tender['reference'] ?? ($tender['reference_no'] ?? null),
                'card_type'       => $tender['card_type'] ?? null,
                'card_last_four'  => $tender['card_last_four'] ?? ($tender['last_four'] ?? null),
                'mobile_provider' => $tender['mobile_provider'] ?? ($tender['provider'] ?? null),
                'bank_name'       => $tender['bank_name'] ?? ($tender['bank'] ?? null),
                'auth_code'       => $tender['auth_code'] ?? null,
            ];
        }

        if (round($totalPaid, 2) < round($payableTotal, 2)) {
            $due = round($payableTotal - $totalPaid, 2);
            throw new InvalidArgumentException("Payment total (৳{$totalPaid}) is less than payable amount (৳{$payableTotal}). Due remaining: ৳{$due}");
        }

        return [
            'is_valid'      => true,
            'total_paid'    => round($totalPaid, 2),
            'payable_total' => round($payableTotal, 2),
            'change_amount' => round($totalChange, 2),
            'tenders'       => $validatedTenders
        ];
    }

    /**
     * Persist validated tenders into pos_payments relational table.
     */
    public function recordPayments(int $transactionId, array $validatedTenders): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO pos_payments (
                transaction_id, payment_method, amount, tendered_amount, 
                change_amount, reference_no, card_type, card_last_four, 
                mobile_provider, bank_name, auth_code, created_at
            ) VALUES (
                :tx, :method, :amount, :tendered, 
                :change, :ref, :card_type, :card_last, 
                :provider, :bank, :auth, NOW()
            )
        ");

        foreach ($validatedTenders as $t) {
            $stmt->execute([
                'tx'        => $transactionId,
                'method'    => $t['method'],
                'amount'    => $t['amount'],
                'tendered'  => $t['tendered_amount'],
                'change'    => $t['change_amount'],
                'ref'       => $t['reference_no'],
                'card_type' => $t['card_type'],
                'card_last' => $t['card_last_four'],
                'provider'  => $t['mobile_provider'],
                'bank'      => $t['bank_name'],
                'auth'      => $t['auth_code'],
            ]);
        }
    }
}
