<?php
/**
 * ==============================================================================
 * tests/pos/pos_shifts_and_reconciliation_test.php
 * ==============================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/../../public/dbconnect.php';

$pdo = db();
$posService = pos_service();

$passed = 0;
$failed = 0;

function assertTest(string $desc, bool $condition, &$passed, &$failed): void {
    if ($condition) {
        $passed++;
        echo "  [PASS] {$desc}\n";
    } else {
        $failed++;
        echo "  [FAIL] {$desc}\n";
    }
}

echo "=======================================================\n";
echo " POS SHIFTS & RECONCILIATION TEST SUITE\n";
echo "=======================================================\n\n";

try {
    // Use or create a test cashier admin
    $testAdminId = 999;
    $stmtAdmin = $pdo->prepare("SELECT id FROM admins WHERE id = ?");
    $stmtAdmin->execute([$testAdminId]);
    if (!$stmtAdmin->fetch()) {
        $pdo->prepare("
            INSERT INTO admins (id, username, email, password, full_name, role_id, created_at)
            VALUES (999, 'test_cashier_999', 'test999@grocery.store', 'hash', 'Test Cashier 999', 1, NOW())
        ")->execute();
    }

    // Clean any existing open shift for test cashier
    $pdo->prepare("DELETE FROM pos_drawer_transactions WHERE shift_id IN (SELECT id FROM pos_shifts WHERE admin_id = ?)")->execute([$testAdminId]);
    $pdo->prepare("DELETE FROM pos_cash_movements WHERE admin_id = ?")->execute([$testAdminId]);
    $pdo->prepare("DELETE FROM pos_shifts WHERE admin_id = ?")->execute([$testAdminId]);

    // 1. Open Shift Drawer
    $openingCash = 1000.00;
    $openRes = $posService->shifts()->openShift($testAdminId, $openingCash, 1, 1, 1, 'Shift Open Test');
    assertTest("Open shift executes and returns shift ID (> 0)", $openRes['shift_id'] > 0, $passed, $failed);
    $shiftId = (int)$openRes['shift_id'];

    // 2. Record Petty Cash In
    $inId = $posService->shifts()->recordCashMovement($shiftId, $testAdminId, 'cash_in', 200.00, 'Adding change float');
    assertTest("Cash-in movement recorded successfully (> 0)", $inId > 0, $passed, $failed);

    // 3. Record Petty Cash Out
    $outId = $posService->shifts()->recordCashMovement($shiftId, $testAdminId, 'cash_out', 50.00, 'Cleaning supplies');
    assertTest("Cash-out movement recorded successfully (> 0)", $outId > 0, $passed, $failed);

    // 4. Mid-Shift X-Reading Report
    $xSummary = $posService->shifts()->getCurrentShiftSummary($testAdminId);
    assertTest("X-Reading reports active shift", $xSummary['has_active_shift'] === true, $passed, $failed);
    assertTest("X-Reading calculates cash-in total (200.00)", abs((float)$xSummary['cash_in'] - 200.00) < 0.001, $passed, $failed);
    assertTest("X-Reading calculates cash-out total (50.00)", abs((float)$xSummary['cash_out'] - 50.00) < 0.001, $passed, $failed);

    // Expected drawer cash: 1000 + 200 - 50 = 1150.00
    $expectedDrawer = 1000.00 + 200.00 - 50.00;
    assertTest("X-Reading calculates expected drawer cash exactly (৳1150.00)", abs((float)$xSummary['expected_cash'] - $expectedDrawer) < 0.001, $passed, $failed);

    // 5. Close Shift with Z-Reading Reconciliation (Counting ৳1140 -> ৳10 short)
    $countedCash = 1140.00;
    $closeRes = $posService->shifts()->closeShift($shiftId, $countedCash, 'End of Shift Z-Reading');
    assertTest("Close shift completed successfully", $closeRes['actual_cash'] === $countedCash, $passed, $failed);
    assertTest("Z-Reading detects cash shortage of ৳10.00", abs((float)$closeRes['difference'] - (-10.00)) < 0.001, $passed, $failed);

    // Cleanup test records cleanly
    $pdo->prepare("DELETE FROM pos_audit_logs WHERE admin_id = ?")->execute([$testAdminId]);
    $pdo->prepare("DELETE FROM pos_drawer_transactions WHERE shift_id = ?")->execute([$shiftId]);
    $pdo->prepare("DELETE FROM pos_cash_movements WHERE admin_id = ?")->execute([$testAdminId]);
    $pdo->prepare("DELETE FROM pos_shifts WHERE admin_id = ?")->execute([$testAdminId]);
    $pdo->prepare("DELETE FROM admins WHERE id = 999")->execute();

} catch (Throwable $e) {
    echo "  [ERROR] " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine() . "\n";
    $failed++;
}

echo "\nSummary: {$passed} PASSED, {$failed} FAILED\n";
exit($failed > 0 ? 1 : 0);
