<?php
/**
 * ==============================================================================
 * tests/pos/pos_master_test_runner.php — Master POS Test Suite Aggregator
 * ==============================================================================
 */

declare(strict_types=1);

$testFiles = [
    'pos_cart_and_pricing_test.php' => 'Cart, Weighted Produce & Pricing Engine',
    'pos_transaction_and_inventory_concurrency_test.php' => 'Atomic Sales, Concurrency & Split Tenders',
    'pos_offline_sync_and_idempotency_test.php' => 'Offline Batch Sync & Idempotency',
    'pos_returns_and_refunds_test.php' => 'Itemized Returns & Restocking',
    'pos_shifts_and_reconciliation_test.php' => 'Shift Drawer, Cash Movements & Reconciliation',
    'pos_api_endpoints_test.php' => 'POS REST API v1 Data Endpoints'
];

echo "======================================================================\n";
echo " GROCO ENTERPRISE POS — MASTER AUTOMATED TEST RUNNER (2026)\n";
echo "======================================================================\n\n";

$phpPath = PHP_BINARY;
if (strpos($phpPath, 'php') === false) {
    $phpPath = 'C:\\xampp\\php\\php.exe';
}

$allPassed = true;
$results = [];

foreach ($testFiles as $file => $label) {
    $fullPath = __DIR__ . DIRECTORY_SEPARATOR . $file;
    if (!file_exists($fullPath)) {
        echo "[ERROR] Missing test suite file: {$file}\n";
        $allPassed = false;
        continue;
    }

    echo ">>> Running: {$label} ({$file})\n";
    $cmd = escapeshellarg($phpPath) . ' ' . escapeshellarg($fullPath);
    exec($cmd, $output, $returnCode);

    echo implode("\n", $output) . "\n\n";

    if ($returnCode === 0) {
        $results[$file] = 'PASS';
    } else {
        $results[$file] = 'FAIL';
        $allPassed = false;
    }
    $output = [];
}

echo "======================================================================\n";
echo " FINAL POS TEST EXECUTION SUMMARY\n";
echo "======================================================================\n";

foreach ($results as $f => $st) {
    $badge = $st === 'PASS' ? '[PASS]' : '[FAIL]';
    echo "  {$badge} {$f}\n";
}

echo "======================================================================\n";
if ($allPassed) {
    echo " ALL POS PRODUCTION TESTS PASSED SUCCESSFULLY! (100% SUCCESS)\n";
} else {
    echo " SOME POS TESTS FAILED! PLEASE INSPECT LOGS ABOVE.\n";
}
echo "======================================================================\n";

exit($allPassed ? 0 : 1);
