<?php
/**
 * ==========================================================================
 * admin/pos/ajax/search_products.php — Unified POS Product Search Endpoint
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

// Support both 'barcode' and 'q' parameters
$query = trim(input('barcode', '', 'get'));
if ($query === '') {
    $query = trim(input('q', '', 'get'));
}

if ($query === '') {
    echo json_encode(['success' => true, 'products' => []]);
    exit;
}

try {
    $pdo = db();
    
    // Select matching active/inactive and in/out of stock products (exclude deleted)
    $stmt = $pdo->prepare("
        SELECT id, name, price, stock, sku, barcode, thumbnail, is_active, status 
        FROM products 
        WHERE (barcode = ? OR sku = ? OR id = ? OR name LIKE ? OR sku LIKE ? OR barcode LIKE ?)
          AND deleted_at IS NULL 
        LIMIT 25
    ");
    $stmt->execute([
        $query,
        $query,
        $query,
        '%' . $query . '%',
        '%' . $query . '%',
        '%' . $query . '%'
    ]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $formattedProducts = [];
    foreach ($products as $p) {
        $formattedProducts[] = [
            'id' => (int) $p['id'],
            'name' => $p['name'],
            'price' => (float) $p['price'],
            'stock' => (int) $p['stock'],
            'image' => image_url($p['thumbnail'], 'products'),
            'barcode' => $p['barcode'],
            'sku' => $p['sku'],
            'is_active' => (int) ($p['is_active'] ?? 1)
        ];
    }

    echo json_encode([
        'success' => true,
        'products' => $formattedProducts
    ]);

} catch (PDOException $e) {
    error_log('[admin/pos/ajax/search_products] query error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database search error.']);
}
