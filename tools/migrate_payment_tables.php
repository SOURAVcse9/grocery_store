<?php
/**
 * ==============================================================================
 * tools/migrate_payment_tables.php — Payment Tables Migration Runner
 * ==============================================================================
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "Forbidden: CLI execution only.\n";
    exit(1);
}

define('GROCO_CLI_TEST_MODE', true);
require_once dirname(__DIR__) . '/public/dbconnect.php';

$pdo = db();

echo "=======================================================\n";
echo " Executing SSLCOMMERZ Payment System Database Migration\n";
echo "=======================================================\n\n";

$sqlFile = dirname(__DIR__) . '/database/migrations/sslcommerz_payment_system.sql';
if (!file_exists($sqlFile)) {
    echo "[FAIL] Migration file not found: {$sqlFile}\n";
    exit(1);
}

$sqlContent = file_get_contents($sqlFile);

try {
    $pdo->exec($sqlContent);
    echo "[PASS] SQL Migration executed successfully.\n\n";

    // Verify tables
    $tables = ['payments', 'payment_refunds', 'payment_audit_logs', 'payment_webhook_events'];
    foreach ($tables as $table) {
        $check = $pdo->query("SHOW TABLES LIKE '{$table}'")->fetch();
        if ($check) {
            $count = $pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
            echo "[OK] Table '{$table}' verified (records: {$count}).\n";
        } else {
            echo "[FAIL] Table '{$table}' was NOT created!\n";
            exit(1);
        }
    }

    // Verify orders columns
    $colCheck = $pdo->query("SHOW COLUMNS FROM orders LIKE 'inventory_deducted'")->fetch();
    if ($colCheck) {
        echo "[OK] Column 'inventory_deducted' exists on 'orders'.\n";
    }

    echo "\n[SUCCESS] All payment schema structures are active and verified.\n";
    exit(0);

} catch (PDOException $e) {
    echo "[ERROR] Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
