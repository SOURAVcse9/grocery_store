<?php
/**
 * ==========================================================================
 * admin/pos/ajax/barcode.php — AJAX Barcode & SKU Product Lookup Endpoint
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

if (!has_admin_permission('pos.sale')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden']);
    exit;
}

$code = trim(input('code', '', 'get'));

if ($code === '') {
    echo json_encode(['success' => false, 'error' => 'No lookup code provided.']);
    exit;
}

try {
    $pdo = db();
    $stmt = $pdo->prepare("
        SELECT id, name, price, stock, sku, barcode 
        FROM products 
        WHERE (barcode = ? OR sku = ? OR id = ?) 
          AND deleted_at IS NULL 
          AND is_active = 1 
        LIMIT 1
    ");
    $stmt->execute([$code, $code, $code]);
    $product = $stmt->fetch();

    if ($product) {
        echo json_encode([
            'success' => true,
            'product' => [
                'id' => (int) $product['id'],
                'name' => $product['name'],
                'price' => (float) $product['price'],
                'stock' => (int) $product['stock'],
                'sku' => $product['sku'],
                'barcode' => $product['barcode']
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Product not found']);
    }
} catch (PDOException $e) {
    error_log('[admin/pos/ajax/barcode] query error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database query error occurred.']);
}
