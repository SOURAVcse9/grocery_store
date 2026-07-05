<?php
/**
 * ==========================================================================
 * admin/reports/exports.php — CSV Reports Exporter
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/../../public/dbconnect.php';
require_once __DIR__ . '/../middleware/auth_middleware.php';

require_admin_auth();
require_admin_permission('reports.view');

$pdo = db();
$type = input('type', 'sales');

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="report_' . $type . '_' . date('Ymd_His') . '.csv"');

$output = fopen('php://output', 'w');
if (!$output) {
    exit;
}

try {
    if ($type === 'sales') {
        $startDate = input('start_date', date('Y-m-d', strtotime('-30 days')));
        $endDate = input('end_date', date('Y-m-d'));
        
        fputcsv($output, ['Sales Date', 'Orders Count', 'Daily Revenue (৳)']);
        
        $stmt = $pdo->prepare("
            SELECT DATE(created_at) AS date, COUNT(*) AS orders_count, SUM(total_amount) AS daily_revenue
            FROM orders
            WHERE status = 'delivered' AND DATE(created_at) BETWEEN :start AND :end
            GROUP BY DATE(created_at)
            ORDER BY DATE(created_at) DESC
        ");
        $stmt->execute(['start' => $startDate, 'end' => $endDate]);
        
        while ($row = $stmt->fetch()) {
            fputcsv($output, [
                $row['date'],
                $row['orders_count'],
                number_format((float)$row['daily_revenue'], 2, '.', '')
            ]);
        }
    }
} catch (PDOException $e) {
    error_log('[admin/reports/exports] failed: ' . $e->getMessage());
}

fclose($output);
exit;
