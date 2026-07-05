<?php
/**
 * ==========================================================================
 * admin/newsletter/export.php — Export Subscribers List to CSV File
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/../../public/dbconnect.php';
require_once __DIR__ . '/../middleware/auth_middleware.php';

require_admin_auth();
require_admin_permission('newsletter.manage');

$pdo = db();

try {
    $stmt = $pdo->prepare("SELECT email, created_at FROM contact_messages WHERE subject = 'Newsletter Opt-in' ORDER BY created_at DESC");
    $stmt->execute();
    $subscribers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Set headers for download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="newsletter_subscribers_' . date('Ymd_His') . '.csv"');
    
    $output = fopen('php://output', 'w');
    if ($output) {
        // Output CSV header row
        fputcsv($output, ['Subscriber Email', 'Opt-in Timestamp']);
        
        foreach ($subscribers as $sub) {
            fputcsv($output, [
                $sub['email'],
                $sub['created_at']
            ]);
        }
        fclose($output);
    }
    
    log_admin_activity('newsletter.export', 'Exported newsletter subscribers list to CSV.');
    exit;

} catch (PDOException $e) {
    error_log('[admin/newsletter/export] CSV Export failed: ' . $e->getMessage());
    echo "An error occurred while generating the CSV file.";
    exit;
}
