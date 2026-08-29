<?php
/**
 * admin_db_integrity_scan.php — GroCo Administrative Database Referential Integrity Scanner
 */

declare(strict_types=1);

require_once __DIR__ . '/public/dbconnect.php';

header('Content-Type: application/json');

$pdo = db();
$report = [];

try {
    // 1. Orphaned order items (no parent order)
    $report['orphaned_order_items'] = (int) $pdo->query("
        SELECT COUNT(*) FROM order_items oi 
        LEFT JOIN orders o ON o.id = oi.order_id 
        WHERE o.id IS NULL
    ")->fetchColumn();

    // 2. Orphaned reviews (no parent product or user)
    $report['orphaned_reviews_product'] = (int) $pdo->query("
        SELECT COUNT(*) FROM product_reviews r 
        LEFT JOIN products p ON p.id = r.product_id 
        WHERE p.id IS NULL
    ")->fetchColumn();
    $report['orphaned_reviews_user'] = (int) $pdo->query("
        SELECT COUNT(*) FROM product_reviews r 
        LEFT JOIN users u ON u.id = r.user_id 
        WHERE u.id IS NULL
    ")->fetchColumn();

    // 3. Orphaned cart items (no parent cart)
    $report['orphaned_cart_items'] = (int) $pdo->query("
        SELECT COUNT(*) FROM cart_items ci 
        LEFT JOIN carts c ON c.id = ci.cart_id 
        WHERE c.id IS NULL
    ")->fetchColumn();

    // 4. Orphaned addresses (no parent user)
    $report['orphaned_addresses'] = (int) $pdo->query("
        SELECT COUNT(*) FROM addresses a 
        LEFT JOIN users u ON u.id = a.user_id 
        WHERE u.id IS NULL
    ")->fetchColumn();

    // 5. Multiple default addresses per user
    $report['users_with_multiple_default_addresses'] = (int) $pdo->query("
        SELECT COUNT(*) FROM (
            SELECT user_id FROM addresses 
            WHERE is_default = 1 
            GROUP BY user_id 
            HAVING COUNT(*) > 1
        ) tmp
    ")->fetchColumn();

    // 6. Negative stock quantities
    $report['products_with_negative_stock'] = (int) $pdo->query("
        SELECT COUNT(*) FROM products WHERE stock < 0
    ")->fetchColumn();

    // 7. Invalid monetary values (negative prices)
    $report['products_with_negative_price'] = (int) $pdo->query("
        SELECT COUNT(*) FROM products WHERE price < 0 OR discount_price < 0
    ")->fetchColumn();

    // 8. Inconsistent order totals (subtotal - discount + delivery + 5% VAT != total_amount)
    $report['inconsistent_order_totals'] = (int) $pdo->query("
        SELECT COUNT(*) FROM orders 
        WHERE ABS(subtotal - discount_amount + delivery_charge + ROUND((subtotal - discount_amount) * 0.05, 2) - total_amount) > 0.05
          AND order_number NOT LIKE 'POS-%'
    ")->fetchColumn();

    // 9. Inconsistent POS order totals (subtotal - discount + 5% VAT != total_amount)
    $report['inconsistent_pos_totals'] = (int) $pdo->query("
        SELECT COUNT(*) FROM orders 
        WHERE order_number LIKE 'POS-%'
          AND ABS(ROUND((subtotal - discount_amount) * 1.05, 2) - total_amount) > 0.05
    ")->fetchColumn();

    // 10. Duplicate ledger transaction reference lines
    $report['duplicate_ledger_references'] = (int) $pdo->query("
        SELECT COUNT(*) FROM (
            SELECT reference FROM transactions 
            GROUP BY reference 
            HAVING COUNT(*) > 1 AND reference IS NOT NULL AND reference != ''
        ) tmp
    ")->fetchColumn();

    // 11. Broken product relations (no parent category)
    $report['products_with_invalid_category'] = (int) $pdo->query("
        SELECT COUNT(*) FROM products p 
        LEFT JOIN categories c ON c.id = p.category_id 
        WHERE c.id IS NULL AND p.deleted_at IS NULL
    ")->fetchColumn();

    // 12. Invalid status values on orders
    $report['orders_with_invalid_status'] = (int) $pdo->query("
        SELECT COUNT(*) FROM orders 
        WHERE status NOT IN ('pending', 'processing', 'shipped', 'delivered', 'cancelled')
    ")->fetchColumn();

    echo json_encode([
        'success' => true,
        'scan_timestamp' => date('c'),
        'anomalies_found' => array_sum($report),
        'results' => $report
    ], JSON_PRETTY_PRINT) . "\n";

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database scan failed: ' . $e->getMessage()
    ], JSON_PRETTY_PRINT) . "\n";
}
