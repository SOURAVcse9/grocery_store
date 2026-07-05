<?php
/**
 * ==========================================================================
 * admin/api/dashboard_charts.php — Analytics Charts API
 * ==========================================================================
 * Returns statistics charts dataset as JSON for dashboard views.
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/../../public/dbconnect.php';
require_once __DIR__ . '/../middleware/auth_middleware.php';

// Access gate check
require_admin_auth();
require_method('GET');

try {
    $pdo = db();

    // 1. Daily Sales Chart (Last 7 Days)
    $dailyStmt = $pdo->query("
        SELECT DATE_FORMAT(created_at, '%b %d') as date_label, SUM(total_amount) as total_sales 
        FROM orders 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
          AND status NOT IN ('cancelled')
        GROUP BY DATE(created_at)
        ORDER BY DATE(created_at) ASC
    ");
    $dailySales = $dailyStmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Monthly Sales Chart (Current Year)
    $monthlyStmt = $pdo->query("
        SELECT DATE_FORMAT(created_at, '%b') as month_label, SUM(total_amount) as total_sales 
        FROM orders 
        WHERE YEAR(created_at) = YEAR(NOW())
          AND status NOT IN ('cancelled')
        GROUP BY MONTH(created_at)
        ORDER BY MONTH(created_at) ASC
    ");
    $monthlySales = $monthlyStmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Order Status Distribution (Pie Chart)
    $statusStmt = $pdo->query("
        SELECT status, COUNT(*) as count 
        FROM orders 
        GROUP BY status
    ");
    $statusDistribution = $statusStmt->fetchAll(PDO::FETCH_ASSOC);

    // 4. Top Selling Products (Bar Chart)
    $productsStmt = $pdo->query("
        SELECT p.name as product_label, SUM(oi.quantity) as total_qty 
        FROM order_items oi 
        JOIN products p ON p.id = oi.product_id 
        GROUP BY oi.product_id 
        ORDER BY total_qty DESC 
        LIMIT 5
    ");
    $topProducts = $productsStmt->fetchAll(PDO::FETCH_ASSOC);

    // 5. Top Categories (Bar Chart)
    $categoriesStmt = $pdo->query("
        SELECT c.name as category_label, SUM(oi.quantity) as total_qty 
        FROM order_items oi 
        JOIN products p ON p.id = oi.product_id 
        JOIN categories c ON c.id = p.category_id 
        GROUP BY p.category_id 
        ORDER BY total_qty DESC 
        LIMIT 5
    ");
    $topCategories = $categoriesStmt->fetchAll(PDO::FETCH_ASSOC);

    // Output JSON response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'data' => [
            'daily_sales' => $dailySales,
            'monthly_sales' => $monthlySales,
            'status_distribution' => $statusDistribution,
            'top_products' => $topProducts,
            'top_categories' => $topCategories
        ]
    ]);

} catch (PDOException $e) {
    error_log('[admin/api/dashboard_charts] Fail: ' . $e->getMessage());
    header('Content-Type: application/json', true, 500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to retrieve analytics data.'
    ]);
}
