<?php
/**
 * ==============================================================================
 * GroCo Enterprise POS — Shift & Cash Register Drawer Service
 * ==============================================================================
 * End-of-shift cash drawer balancing, discrepancy detection, X/Z reading
 * reports, and petty cash movements.
 * ==============================================================================
 */

declare(strict_types=1);

namespace Groco\Pos;

use PDO;
use Exception;
use InvalidArgumentException;

class PosShiftService
{
    private PDO $pdo;
    private PosAuditService $auditService;

    public function __construct(PDO $pdo, ?PosAuditService $auditService = null)
    {
        $this->pdo = $pdo;
        $this->auditService = $auditService ?? new PosAuditService($pdo);
    }

    /**
     * Open a new register shift drawer.
     */
    public function openShift(
        int $adminId,
        float $openingCash,
        int $storeId = 1,
        ?int $registerId = 1,
        ?int $terminalId = 1,
        string $openingNote = ''
    ): array {
        if ($openingCash < 0.00) {
            throw new InvalidArgumentException("Opening cash amount cannot be negative.");
        }

        // Check if cashier already has an open shift
        $stmtCheck = $this->pdo->prepare("SELECT id FROM pos_shifts WHERE admin_id = ? AND status = 'open' LIMIT 1");
        $stmtCheck->execute([$adminId]);
        if ($stmtCheck->fetch()) {
            throw new Exception("A cash register shift is already active for this cashier.");
        }

        $stmtIns = $this->pdo->prepare("
            INSERT INTO pos_shifts (
                admin_id, store_id, register_id, terminal_id, 
                opening_cash, status, start_time, created_at
            ) VALUES (
                :admin, :store, :reg, :term, 
                :cash, 'open', NOW(), NOW()
            )
        ");

        $stmtIns->execute([
            'admin' => $adminId,
            'store' => $storeId,
            'reg'   => $registerId,
            'term'  => $terminalId,
            'cash'  => $openingCash
        ]);

        $shiftId = (int)$this->pdo->lastInsertId();

        $this->auditService->log(
            'SHIFT_OPENED',
            $adminId,
            $storeId,
            $registerId,
            $terminalId,
            null,
            null,
            ['opening_cash' => $openingCash, 'note' => $openingNote],
            ['shift_id' => $shiftId]
        );

        return [
            'id'           => $shiftId,
            'shift_id'     => $shiftId,
            'admin_id'     => $adminId,
            'store_id'     => $storeId,
            'register_id'  => $registerId,
            'terminal_id'  => $terminalId,
            'opening_cash' => $openingCash,
            'status'       => 'open',
            'start_time'   => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Get active open shift for cashier.
     */
    public function getActiveShift(int $adminId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM pos_shifts WHERE admin_id = ? AND status = 'open' LIMIT 1");
        $stmt->execute([$adminId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Get current active shift summary for a cashier.
     */
    public function getCurrentShiftSummary(int $adminId): array
    {
        $activeShift = $this->getActiveShift($adminId);
        if (!$activeShift) {
            return [
                'has_active_shift' => false,
                'shift'            => null
            ];
        }

        $summary = $this->getShiftSummary((int)$activeShift['id']);
        $summary['has_active_shift'] = true;
        return $summary;
    }

    /**
     * Compile shift performance and financial reconciliation breakdown.
     */
    public function getShiftSummary(int $shiftId): array
    {
        $stmtShift = $this->pdo->prepare("SELECT * FROM pos_shifts WHERE id = ? LIMIT 1");
        $stmtShift->execute([$shiftId]);
        $shift = $stmtShift->fetch(PDO::FETCH_ASSOC);

        if (!$shift) {
            throw new Exception("Shift ID #{$shiftId} not found.");
        }

        $startTime = $shift['start_time'];
        $endTime = $shift['end_time'] ?? date('Y-m-d H:i:s');
        $openingCash = (float)$shift['opening_cash'];

        // 1. Calculate sales by payment method from pos_payments or orders
        $stmtTenders = $this->pdo->prepare("
            SELECT p.payment_method, SUM(p.amount) AS total_amount, COUNT(DISTINCT p.transaction_id) AS tx_count
            FROM pos_payments p
            JOIN pos_transactions t ON t.id = p.transaction_id
            WHERE t.shift_id = ? AND t.status = 'completed'
            GROUP BY p.payment_method
        ");
        $stmtTenders->execute([$shiftId]);
        $tenders = $stmtTenders->fetchAll(PDO::FETCH_ASSOC);

        $cashSales = 0.00;
        $cardSales = 0.00;
        $digitalSales = 0.00;
        $otherSales = 0.00;
        $totalSales = 0.00;

        foreach ($tenders as $t) {
            $amt = (float)$t['total_amount'];
            $totalSales += $amt;
            if ($t['payment_method'] === 'cash') {
                $cashSales += $amt;
            } elseif ($t['payment_method'] === 'card') {
                $cardSales += $amt;
            } elseif (in_array($t['payment_method'], ['bkash', 'nagad', 'rocket', 'mobile_banking'], true)) {
                $digitalSales += $amt;
            } else {
                $otherSales += $amt;
            }
        }

        // 2. Calculate Cash-Ins and Cash-Outs
        $stmtCashIn = $this->pdo->prepare("
            SELECT COALESCE(SUM(amount), 0.00) 
            FROM pos_cash_movements 
            WHERE shift_id = ? AND type IN ('cash_in', 'safe_drop')
        ");
        $stmtCashIn->execute([$shiftId]);
        $cashIn = (float)$stmtCashIn->fetchColumn();

        if ($cashIn === 0.00) {
            $stmtLegacyIn = $this->pdo->prepare("SELECT COALESCE(SUM(amount), 0.00) FROM pos_drawer_transactions WHERE shift_id = ? AND type = 'cash_in'");
            $stmtLegacyIn->execute([$shiftId]);
            $cashIn = (float)$stmtLegacyIn->fetchColumn();
        }

        $stmtCashOut = $this->pdo->prepare("
            SELECT COALESCE(SUM(amount), 0.00) 
            FROM pos_cash_movements 
            WHERE shift_id = ? AND type IN ('cash_out', 'petty_cash')
        ");
        $stmtCashOut->execute([$shiftId]);
        $cashOut = (float)$stmtCashOut->fetchColumn();

        if ($cashOut === 0.00) {
            $stmtLegacyOut = $this->pdo->prepare("SELECT COALESCE(SUM(amount), 0.00) FROM pos_drawer_transactions WHERE shift_id = ? AND type = 'cash_out'");
            $stmtLegacyOut->execute([$shiftId]);
            $cashOut = (float)$stmtLegacyOut->fetchColumn();
        }

        // 3. Calculate Cash Refunds
        $stmtRefunds = $this->pdo->prepare("
            SELECT COALESCE(SUM(refund_amount), 0.00) 
            FROM pos_returns 
            WHERE admin_id = ? AND created_at >= ? AND refund_method = 'cash'
        ");
        $stmtRefunds->execute([$shift['admin_id'], $startTime]);
        $cashRefunds = (float)$stmtRefunds->fetchColumn();

        // Expected drawer cash formula:
        // Expected = Opening Cash + Cash Sales + Cash In - Cash Out - Cash Refunds
        $expectedCash = round($openingCash + $cashSales + $cashIn - $cashOut - $cashRefunds, 2);

        $actualCash = ($shift['status'] === 'closed') ? (float)$shift['actual_cash'] : $expectedCash;
        $difference = round($actualCash - $expectedCash, 2);

        return [
            'shift'         => $shift,
            'opening_cash'  => $openingCash,
            'cash_sales'    => round($cashSales, 2),
            'card_sales'    => round($cardSales, 2),
            'digital_sales' => round($digitalSales, 2),
            'other_sales'   => round($otherSales, 2),
            'total_sales'   => round($totalSales, 2),
            'cash_in'       => round($cashIn, 2),
            'cash_out'      => round($cashOut, 2),
            'cash_refunds'  => round($cashRefunds, 2),
            'expected_cash' => $expectedCash,
            'actual_cash'   => $actualCash,
            'difference'    => $difference,
            'is_overage'    => $difference > 0,
            'is_shortage'   => $difference < 0,
            'is_balanced'   => abs($difference) < 0.01,
            'tenders'       => $tenders
        ];
    }

    /**
     * Close register shift, balance drawer, and log discrepancy audit.
     */
    public function closeShift(int $shiftId, float $actualCash, string $closingNote = ''): array
    {
        $summary = $this->getShiftSummary($shiftId);
        $expectedCash = $summary['expected_cash'];
        $difference = round($actualCash - $expectedCash, 2);

        $stmtUp = $this->pdo->prepare("
            UPDATE pos_shifts SET 
                end_time = NOW(),
                closing_cash = :expected,
                actual_cash = :actual,
                status = 'closed'
            WHERE id = :id
        ");

        $stmtUp->execute([
            'expected' => $expectedCash,
            'actual'   => $actualCash,
            'id'       => $shiftId
        ]);

        $this->auditService->log(
            'SHIFT_CLOSED',
            (int)$summary['shift']['admin_id'],
            (int)($summary['shift']['store_id'] ?? 1),
            (int)($summary['shift']['register_id'] ?? 1),
            (int)($summary['shift']['terminal_id'] ?? 1),
            null,
            ['status' => 'open'],
            [
                'status'        => 'closed',
                'expected_cash' => $expectedCash,
                'actual_cash'   => $actualCash,
                'difference'    => $difference,
                'note'          => $closingNote
            ],
            ['shift_id' => $shiftId]
        );

        return array_merge($summary, [
            'actual_cash' => $actualCash,
            'difference'  => $difference,
            'closed_at'   => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Record petty cash in / out movement.
     */
    public function recordCashMovement(
        int $shiftId,
        int $adminId,
        string $type,
        float $amount,
        string $reason,
        ?int $authorizedBy = null
    ): int {
        if ($amount <= 0.00) {
            throw new InvalidArgumentException("Cash movement amount must be greater than zero.");
        }
        if (trim($reason) === '') {
            throw new InvalidArgumentException("A reason must be provided for cash movements.");
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO pos_cash_movements (
                shift_id, admin_id, type, amount, reason, authorized_by, created_at
            ) VALUES (
                :shift, :admin, :type, :amount, :reason, :auth, NOW()
            )
        ");

        $stmt->execute([
            'shift'  => $shiftId,
            'admin'  => $adminId,
            'type'   => $type,
            'amount' => $amount,
            'reason' => $reason,
            'auth'   => $authorizedBy
        ]);

        $movementId = (int)$this->pdo->lastInsertId();

        // Also record to pos_drawer_transactions for legacy table compatibility
        try {
            $legacyType = ($type === 'cash_in' || $type === 'safe_drop') ? 'cash_in' : 'cash_out';
            $stmtLeg = $this->pdo->prepare("
                INSERT INTO pos_drawer_transactions (shift_id, type, amount, notes, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmtLeg->execute([$shiftId, $legacyType, $amount, $reason]);
        } catch (Exception $e) {
            // Non-critical legacy sync
        }

        $this->auditService->log(
            strtoupper($type),
            $adminId,
            1,
            null,
            null,
            null,
            null,
            ['amount' => $amount, 'reason' => $reason, 'type' => $type],
            ['shift_id' => $shiftId, 'movement_id' => $movementId]
        );

        return $movementId;
    }
}
