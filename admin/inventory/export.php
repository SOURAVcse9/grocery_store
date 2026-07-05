<?php
/**
 * ==========================================================================
 * admin/inventory/export.php — Export Inventory Valuation to CSV
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/../../public/dbconnect.php';
require_once __DIR__ . '/../middleware/auth_middleware.php';

require_admin_auth();
require_admin_permission('inventory.manage');

$pdo = db();

try {
    $stmt = $pdo->query("
        SELECT id, name, price, stock, (price * stock) AS valuation
        FROM products
        WHERE deleted_at IS NULL AND is_active = 1
        ORDER BY name ASC
    ");
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="inventory_valuation_' . date('Ymd_His') . '.csv"');

    $output = fopen('php://output', 'w');
    if ($output) {
        fputcsv($output, ['Product ID', 'Product Name', 'Unit Retail Price (৳)', 'In Stock Quantity', 'Stock Valuation (৳)']);
        foreach ($items as $row) {
            fputcsv($output, [
                $row['id'],
                $row['name'],
                number_format((float)$row['price'], 2, '.', ''),
                $row['stock'],
                number_format((float)$row['valuation'], 2, '.', '')
            ]);
        }
        fclose($output);
    }
    log_admin_activity('inventory.export', 'Exported inventory stock valuation list to CSV.');
    exit;

} catch (PDOException $e) {
    error_log('[admin/inventory/export] CSV export failed: ' . $e->getMessage());
    echo "An error occurred while generating the CSV file.";
    exit;
}
