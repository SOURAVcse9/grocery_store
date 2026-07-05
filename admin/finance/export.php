<?php
/**
 * ==========================================================================
 * admin/finance/export.php — Export General Ledger to CSV
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/../../public/dbconnect.php';
require_once __DIR__ . '/../middleware/auth_middleware.php';

require_admin_auth();
require_admin_permission('finance.manage');

$pdo = db();

try {
    $stmt = $pdo->query("
        SELECT t.id, t.type, t.amount, t.reference, t.payment_method, t.reconciled, t.created_at, ec.name AS category_name
        FROM transactions t
        LEFT JOIN expense_categories ec ON ec.id = t.category_id
        ORDER BY t.created_at DESC
    ");
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="general_ledger_' . date('Ymd_His') . '.csv');

    $output = fopen('php://output', 'w');
    if ($output) {
        fputcsv($output, ['TX ID', 'Type', 'Category', 'Amount (৳)', 'Reference Memo', 'Payment Method', 'Reconciled', 'Posted Date']);
        foreach ($items as $row) {
            fputcsv($output, [
                $row['id'],
                strtoupper($row['type']),
                $row['category_name'] ?: 'N/A',
                number_format((float)$row['amount'], 2, '.', ''),
                $row['reference'],
                strtoupper($row['payment_method']),
                $row['reconciled'] ? 'YES' : 'NO',
                $row['created_at']
            ]);
        }
        fclose($output);
    }
    log_admin_activity('finance.export', 'Exported general ledger transactions to CSV.');
    exit;

} catch (PDOException $e) {
    error_log('[admin/finance/export] CSV export failed: ' . $e->getMessage());
    echo "An error occurred while generating the CSV file.";
    exit;
}
