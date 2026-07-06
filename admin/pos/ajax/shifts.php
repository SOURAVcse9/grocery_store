<?php
/**
 * ==========================================================================
 * admin/pos/ajax/shifts.php — POS Cashier Shift AJAX Handler
 * ==========================================================================
 */

declare(strict_types=1);

// Set JSON content-type header at the very top
header('Content-Type: application/json');

require_once __DIR__ . '/../../../public/dbconnect.php';
require_once __DIR__ . '/../../includes/auth_helpers.php';

// Safe JSON auth validation checks
if (!is_admin_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if (!has_admin_permission('pos.manage')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden']);
    exit;
}

$pdo = db();
$adminId = current_admin_id();
$action = input('action', '');

try {
    if (method_is('get')) {
        // Fetch active shift details
        $stmtActive = $pdo->prepare("SELECT * FROM pos_shifts WHERE admin_id = ? AND status = 'open' LIMIT 1");
        $stmtActive->execute([$adminId]);
        $activeShift = $stmtActive->fetch();

        if ($activeShift) {
            echo json_encode(['success' => true, 'has_active_shift' => true, 'shift' => $activeShift]);
        } else {
            echo json_encode(['success' => true, 'has_active_shift' => false]);
        }
        exit;
    }

    if (method_is('post')) {
        if (!verify_csrf()) {
            echo json_encode(['success' => false, 'error' => 'Invalid security request (CSRF check failed).']);
            exit;
        }

        if ($action === 'open_shift') {
            $openingCash = (float) input('opening_cash', '0.00');

            // Verify no open shift exists
            $stmtCheck = $pdo->prepare("SELECT id FROM pos_shifts WHERE admin_id = ? AND status = 'open' LIMIT 1");
            $stmtCheck->execute([$adminId]);
            if ($stmtCheck->fetch()) {
                echo json_encode(['success' => false, 'error' => 'A register shift is already active.']);
                exit;
            }

            $ins = $pdo->prepare("
                INSERT INTO pos_shifts (admin_id, opening_cash, status, start_time, created_at)
                VALUES (?, ?, 'open', NOW(), NOW())
            ");
            $ins->execute([$adminId, $openingCash]);

            log_admin_activity('pos.open_shift', "Opened new POS cashier register shift with starting cash ৳{$openingCash}");
            echo json_encode(['success' => true, 'message' => 'Shift opened successfully.']);
            exit;
        }

        if ($action === 'close_shift') {
            $actualCash = (float) input('actual_cash', '0.00');

            $stmtActive = $pdo->prepare("SELECT * FROM pos_shifts WHERE admin_id = ? AND status = 'open' LIMIT 1");
            $stmtActive->execute([$adminId]);
            $activeShift = $stmtActive->fetch();

            if (!$activeShift) {
                echo json_encode(['success' => false, 'error' => 'No active shift found to close.']);
                exit;
            }

            // Calculate shift sales
            $stmtSales = $pdo->prepare("
                SELECT SUM(total_amount) 
                FROM orders 
                WHERE status = 'delivered' 
                  AND payment_method = 'pos_split'
                  AND created_at >= ?
            ");
            $stmtSales->execute([$activeShift['start_time']]);
            $shiftSales = (float) $stmtSales->fetchColumn();

            $expectedCash = (float)$activeShift['opening_cash'] + $shiftSales;

            $up = $pdo->prepare("
                UPDATE pos_shifts SET 
                    end_time = NOW(),
                    closing_cash = ?,
                    actual_cash = ?,
                    status = 'closed'
                WHERE id = ?
            ");
            $up->execute([$expectedCash, $actualCash, $activeShift['id']]);

            log_admin_activity('pos.close_shift', "Closed register shift ID: {$activeShift['id']}. Cash Count: ৳{$actualCash}");
            echo json_encode(['success' => true, 'message' => 'Shift closed successfully.']);
            exit;
        }
    }

    echo json_encode(['success' => false, 'error' => 'Invalid request action.']);

} catch (Exception $e) {
    error_log('[admin/pos/ajax/shifts] Shift operation failed: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
