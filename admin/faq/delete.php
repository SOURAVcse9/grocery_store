<?php
/**
 * ==========================================================================
 * admin/faq/delete.php — Delete FAQ Action
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/../../public/dbconnect.php';
require_once __DIR__ . '/../middleware/auth_middleware.php';

require_admin_auth();
require_admin_permission('faq.manage');

$pdo = db();
$faqId = (int) input('id', '0', 'get');

if ($faqId > 0) {
    try {
        $stmt = $pdo->prepare("SELECT question FROM faqs WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $faqId]);
        $question = $stmt->fetchColumn();

        if ($question) {
            $del = $pdo->prepare("DELETE FROM faqs WHERE id = :id");
            $del->execute(['id' => $faqId]);

            log_admin_activity('faq.delete', "Deleted FAQ question: '{$question}'");
            flash('faq_msg', 'FAQ deleted successfully.', 'success');
        }
    } catch (PDOException $e) {
        error_log('[admin/faq/delete] failed: ' . $e->getMessage());
        flash('faq_msg', 'Failed to delete FAQ record.', 'error');
    }
}

header('Location: index.php');
exit;
