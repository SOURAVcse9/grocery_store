<?php
/**
 * ==========================================================================
 * public/api/analytics.php
 * ==========================================================================
 * AJAX API endpoint to fetch analytics chart configurations and export
 * order summaries in CSV format.
 * Responds with JSON or downloads files.
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/../dbconnect.php';

// Check auth
if (!is_logged_in()) {
    if (input('action', '', 'get') === 'download_csv') {
        redirect('login.php');
    }
    json_response(false, 'Unauthorized access.', [], 401);
}

$userId = current_user_id();
$action = input('action', '', 'get');

try {
    $pdo = db();

    // ---------------------------------------------------------------------
    // A. Export Order Summary (CSV File Download)
    // ---------------------------------------------------------------------
    if ($action === 'download_csv') {
        // Fetch all user orders
        $stmt = $pdo->prepare('
            SELECT order_number, created_at, payment_method, payment_status, status, 
                   subtotal, discount_amount, delivery_charge, total_amount 
            FROM orders 
            WHERE user_id = :uid 
            ORDER BY id DESC
        ');
        $stmt->execute(['uid' => $userId]);
        $orders = $stmt->fetchAll();

        // Send CSV headers
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=groco_orders_summary_' . date('Ymd') . '.csv');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');

        // CSV Header columns
        fputcsv($output, [
            'Order Number', 'Date', 'Payment Method', 'Payment Status', 'Fulfillment Status',
            'Subtotal (BDT)', 'Discount (BDT)', 'Delivery Charge (BDT)', 'Total Amount (BDT)'
        ]);

        foreach ($orders as $o) {
            fputcsv($output, [
                $o['order_number'],
                date('Y-m-d H:i', strtotime($o['created_at'])),
                strtoupper($o['payment_method']),
                strtoupper($o['payment_status']),
                strtoupper($o['status']),
                $o['subtotal'],
                $o['discount_amount'],
                $o['delivery_charge'],
                $o['total_amount']
            ]);
        }

        fclose($output);
        exit;
    }

    // ---------------------------------------------------------------------
    // B. Fetch Charts Config Data
    // ---------------------------------------------------------------------
    
    // 1. Monthly Spending & Orders Timeline (last 6 months)
    $spendingStmt = $pdo->prepare("
        SELECT DATE_FORMAT(created_at, '%b %Y') AS month_label, 
               SUM(total_amount) AS total_spent, 
               COUNT(id) AS total_orders
        FROM orders 
        WHERE user_id = :uid AND status != 'cancelled'
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY created_at ASC
        LIMIT 6
    ");
    $spendingStmt->execute(['uid' => $userId]);
    $timeline = $spendingStmt->fetchAll();

    // 2. Favorite Categories distribution
    $catStmt = $pdo->prepare('
        SELECT c.name AS label, COUNT(*) AS count
        FROM order_items oi
        JOIN products p ON p.id = oi.product_id
        JOIN categories c ON c.id = p.category_id
        JOIN orders o ON o.id = oi.order_id
        WHERE o.user_id = :uid
        GROUP BY c.id
        ORDER BY count DESC
        LIMIT 5
    ');
    $catStmt->execute(['uid' => $userId]);
    $categories = $catStmt->fetchAll();

    // 3. Product Purchases quantities
    $prodStmt = $pdo->prepare('
        SELECT oi.product_name AS label, SUM(oi.quantity) AS count
        FROM order_items oi
        JOIN orders o ON o.id = oi.order_id
        WHERE o.user_id = :uid
        GROUP BY oi.product_id
        ORDER BY count DESC
        LIMIT 5
    ');
    $prodStmt->execute(['uid' => $userId]);
    $products = $prodStmt->fetchAll();

    // 4. Order Status distribution
    $statusStmt = $pdo->prepare('
        SELECT status AS label, COUNT(*) AS count
        FROM orders
        WHERE user_id = :uid
        GROUP BY status
    ');
    $statusStmt->execute(['uid' => $userId]);
    $statuses = $statusStmt->fetchAll();

    json_response(true, 'Analytics data compiled.', [
        'timeline'   => $timeline,
        'categories' => $categories,
        'products'   => $products,
        'statuses'   => $statuses
    ]);

} catch (PDOException $e) {
    error_log('[api/analytics.php] Error: ' . $e->getMessage());
    json_response(false, 'Database load error.', [], 500);
}
