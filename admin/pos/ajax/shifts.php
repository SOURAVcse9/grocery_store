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
require_once __DIR__ . '/../../includes/pos_lib.php';

// Safe JSON auth validation checks
if (!is_admin_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if (!has_admin_permission('pos.manage') && !has_admin_permission('pos.access') && !has_admin_permission('pos.cash') && !has_admin_permission('pos.sale')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden: POS terminal access required.']);
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
            $shiftId = pos_open_shift($pdo, (int) $adminId, $openingCash);
            log_admin_activity('pos.open_shift', "Opened POS shift #{$shiftId} with starting cash ৳{$openingCash}");
            echo json_encode(['success' => true, 'message' => 'Shift opened successfully.', 'shift_id' => $shiftId]);
            exit;
        }

        if ($action === 'close_shift') {
            $closed = pos_close_shift($pdo, (int) $adminId, (float) input('actual_cash', '0.00'));
            log_admin_activity('pos.close_shift', "Closed shift #{$closed['shift_id']}: expected ৳{$closed['expected']}, counted ৳{$closed['actual']}, difference ৳{$closed['difference']}");
            echo json_encode(['success' => true, 'message' => 'Shift closed successfully.', 'expected' => $closed['expected'], 'actual' => $closed['actual'], 'difference' => $closed['difference']]);
            exit;
        }

        if ($action === 'summary') {
            $sh = pos_active_shift($pdo, (int) $adminId);
            echo json_encode($sh ? ['success' => true, 'summary' => pos_shift_summary($pdo, $sh)] : ['success' => false, 'error' => 'No active shift.']);
            exit;
        }

        if ($action === 'cash_in' || $action === 'cash_out') {
            if (!has_admin_permission('pos.cash') && !has_admin_permission('pos.manage')) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Forbidden: cash drawer permission required.']);
                exit;
            }
            pos_drawer_tx($pdo, (int) $adminId, $action, (float) input('amount', '0'), (string) input('notes', ''));
            log_admin_activity('pos.drawer_tx', "Drawer {$action} ৳" . (float) input('amount', '0'));
            echo json_encode(['success' => true, 'message' => 'Drawer transaction recorded.']);
            exit;
        }
    }

    echo json_encode(['success' => false, 'error' => 'Invalid request action.']);

} catch (PosException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('[admin/pos/ajax/shifts] Shift operation failed: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Shift operation failed due to a server error.']);
}
