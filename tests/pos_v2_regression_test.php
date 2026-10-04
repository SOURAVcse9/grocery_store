<?php
/**
 * ==========================================================================
 * tests/pos_v2_regression_test.php — Full POS Regression Suite
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/../public/dbconnect.php';
require_once __DIR__ . '/../public/includes/pos_autoload.php';

$pdo = db();

$passed = 0;
$failed = 0;

function assertRegression(string $description, bool $condition) {
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "  [PASS] {$description}\n";
    } else {
        $failed++;
        echo "  [FAIL] {$description}\n";
    }
}

echo "=======================================================\n";
echo " POS V2 FULL REGRESSION SAFETY TEST SUITE\n";
echo "=======================================================\n\n";

// 1. Existing Products Table Structure & Essential Columns Preserved
$prodCols = $pdo->query("DESCRIBE products")->fetchAll(PDO::FETCH_COLUMN);
$essentialProdCols = ['id', 'name', 'price', 'stock', 'sku', 'category_id', 'created_at'];
$allProdColsExist = count(array_intersect($essentialProdCols, $prodCols)) === count($essentialProdCols);
assertRegression("Products table schema integrity preserved", $allProdColsExist);

// 2. Existing Orders Table Schema Preserved
$orderCols = $pdo->query("DESCRIBE orders")->fetchAll(PDO::FETCH_COLUMN);
$essentialOrderCols = ['id', 'user_id', 'total_amount', 'status', 'created_at'];
$allOrderColsExist = count(array_intersect($essentialOrderCols, $orderCols)) === count($essentialOrderCols);
assertRegression("Orders table schema integrity preserved", $allOrderColsExist);

// 3. Existing Categories and Brands Accessible
$catCount = (int)$pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
$brandCount = (int)$pdo->query("SELECT COUNT(*) FROM brands")->fetchColumn();
assertRegression("Existing categories ({$catCount}) and brands ({$brandCount}) remain accessible", $catCount >= 0 && $brandCount >= 0);

// 4. Existing Customers & Admin Accounts Intact
$adminCount = (int)$pdo->query("SELECT COUNT(*) FROM admins")->fetchColumn();
$userCount = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
assertRegression("Existing users ({$userCount}) and admins ({$adminCount}) remain intact", $adminCount > 0 && $userCount > 0);

// 5. Existing POS Store, Register and Terminal Configurations
$storeCount = (int)$pdo->query("SELECT COUNT(*) FROM pos_stores")->fetchColumn();
$regCount = (int)$pdo->query("SELECT COUNT(*) FROM pos_registers")->fetchColumn();
$termCount = (int)$pdo->query("SELECT COUNT(*) FROM pos_terminals")->fetchColumn();
assertRegression("POS multi-store configuration present (Stores: {$storeCount}, Registers: {$regCount}, Terminals: {$termCount})", $storeCount > 0 && $regCount > 0 && $termCount > 0);

// 6. Online Storefront Query Compatibility
$onlineProds = $pdo->query("SELECT id, name, price, stock FROM products WHERE is_active = 1 AND stock > 0 LIMIT 10")->fetchAll();
assertRegression("Online storefront product catalog query operates without errors", count($onlineProds) > 0);

// 7. Finance & Expense Ledgers Unaltered
$expensesExist = $pdo->query("SHOW TABLES LIKE 'expenses'")->rowCount() > 0;
assertRegression("Finance & expense subsystems remain intact", $expensesExist);

echo "\nSummary: {$passed} PASSED, {$failed} FAILED\n";

if ($failed > 0) {
    exit(1);
}
