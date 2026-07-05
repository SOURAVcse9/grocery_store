<?php
/**
 * ==========================================================================
 * admin/contact/delete.php — Delete Contact Message Action
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/../../public/dbconnect.php';
require_once __DIR__ . '/../middleware/auth_middleware.php';

require_admin_auth();
require_admin_permission('contacts.manage');

$pdo = db();
$msgId = (int) input('id', '0', 'get');

if ($msgId > 0) {
    try {
        $stmt = $pdo->prepare("SELECT subject FROM contact_messages WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $msgId]);
        $subject = $stmt->fetchColumn();

        if ($subject) {
            $del = $pdo->prepare("DELETE FROM contact_messages WHERE id = :id");
            $del->execute(['id' => $msgId]);

            log_admin_activity('contacts.delete', "Deleted contact message: '{$subject}'");
            flash('contact_msg', 'Message deleted successfully.', 'success');
        }
    } catch (PDOException $e) {
        error_log('[admin/contact/delete] failed: ' . $e->getMessage());
        flash('contact_msg', 'Failed to delete contact message.', 'error');
    }
}

header('Location: index.php');
exit;
