<?php
/**
 * ==========================================================================
 * admin/testimonials/delete.php — Delete Testimonial Action
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/../../public/dbconnect.php';
require_once __DIR__ . '/../middleware/auth_middleware.php';

require_admin_auth();
require_admin_permission('testimonials.manage');

$pdo = db();
$testiId = (int) input('id', '0', 'get');

if ($testiId > 0) {
    try {
        $stmt = $pdo->prepare("SELECT name FROM testimonials WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $testiId]);
        $name = $stmt->fetchColumn();

        if ($name) {
            $del = $pdo->prepare("DELETE FROM testimonials WHERE id = :id");
            $del->execute(['id' => $testiId]);

            log_admin_activity('testimonials.delete', "Deleted testimonial feedback for: '{$name}'");
            flash('testi_msg', 'Testimonial deleted successfully.', 'success');
        }
    } catch (PDOException $e) {
        error_log('[admin/testimonials/delete] failed: ' . $e->getMessage());
        flash('testi_msg', 'Failed to delete testimonial record.', 'error');
    }
}

header('Location: index.php');
exit;
