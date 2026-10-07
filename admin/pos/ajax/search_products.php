<?php
/**
 * ==========================================================================
 * admin/pos/ajax/search_products.php — Unified POS product search / barcode lookup
 * ==========================================================================
 * Ranking: exact barcode/SKU  >  exact id  >  barcode/SKU prefix  >  name prefix  >  name/brand/category contains.
 * Exact matches always sort first so a scan can never be pushed out by the LIMIT.
 * Only light columns are returned (no descriptions, no gallery).
 */

declare(strict_types=1);

header('Content-Type: application/json');
header('Cache-Control: no-store');

require_once __DIR__ . '/../../../public/dbconnect.php';
require_once __DIR__ . '/../../includes/auth_helpers.php';
require_once __DIR__ . '/../../includes/pos_lib.php';

if (!is_admin_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}
if (!has_admin_permission('pos.sale') && !has_admin_permission('pos.access') && !has_admin_permission('pos.manage')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden']);
    exit;
}

$query = trim(input('barcode', '', 'get'));
if ($query === '') {
    $query = trim(input('q', '', 'get'));
}
$query = function_exists('mb_substr') ? mb_substr($query, 0, 100, 'UTF-8') : substr($query, 0, 100);
if ($query === '') {
    echo json_encode(['success' => true, 'products' => []]);
    exit;
}

try {
    $pdo = db();
    $like = addcslashes($query, '\\%_');
    $numericId = ctype_digit($query) ? (int) $query : 0;
    $hasUnit = pos_column_exists($pdo, 'products', 'unit');
    $unitSel = $hasUnit ? 'p.unit AS unit,' : "NULL AS unit,";

    $stmt = $pdo->prepare("
        SELECT p.id, p.name, p.price, p.discount_price, p.stock, p.min_stock, p.sku, p.barcode, p.thumbnail, p.is_active,
               $unitSel c.name AS category_name, b.name AS brand_name
        FROM products p
        LEFT JOIN categories c ON c.id = p.category_id
        LEFT JOIN brands b ON b.id = p.brand_id
        WHERE p.deleted_at IS NULL
          AND (p.barcode = :exact OR p.sku = :exact2 OR p.id = :nid
               OR p.barcode LIKE :pre OR p.sku LIKE :pre2 OR p.name LIKE :contains
               OR b.name LIKE :contains2 OR c.name LIKE :contains3)
        ORDER BY (p.barcode = :e3 OR p.sku = :e4) DESC,
                 (p.id = :nid2) DESC,
                 (p.barcode LIKE :pre3 OR p.sku LIKE :pre4) DESC,
                 (p.name LIKE :namepre) DESC,
                 (p.stock > 0) DESC,
                 p.name ASC
        LIMIT 25
    ");
    $stmt->execute([
        ':exact' => $query, ':exact2' => $query, ':nid' => $numericId, ':nid2' => $numericId,
        ':pre' => $like . '%', ':pre2' => $like . '%', ':pre3' => $like . '%', ':pre4' => $like . '%',
        ':contains' => '%' . $like . '%', ':contains2' => '%' . $like . '%', ':contains3' => '%' . $like . '%',
        ':e3' => $query, ':e4' => $query, ':namepre' => $like . '%',
    ]);

    $out = [];
    foreach ($stmt->fetchAll() as $p) {
        $stock = (float) $p['stock'];
        $out[] = [
            'id'             => (int) $p['id'],
            'name'           => $p['name'],
            'price'          => pos_effective_price($p),
            'regular_price'  => (float) $p['price'],
            'discount_price' => $p['discount_price'] !== null ? (float) $p['discount_price'] : null,
            'stock'          => $stock,
            'low_stock'      => $stock > 0 && $stock <= (float) ($p['min_stock'] ?? 0),
            'unit'           => $p['unit'],
            'category'       => $p['category_name'],
            'brand'          => $p['brand_name'],
            'image'          => image_url($p['thumbnail'], 'products'),
            'barcode'        => $p['barcode'],
            'sku'            => $p['sku'],
            'is_active'      => (int) ($p['is_active'] ?? 1),
        ];
    }
    echo json_encode(['success' => true, 'products' => $out]);
} catch (Throwable $e) {
    error_log('[admin/pos/ajax/search_products] query error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database search error.']);
}
