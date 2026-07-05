<?php
/**
 * ==========================================================================
 * admin/customers/delete.php — Soft Delete / Restore Customer Account
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/../../public/dbconnect.php';
require_once __DIR__ . '/../middleware/auth_middleware.php';

require_admin_auth();

$pdo = db();
$userId = (int) input('id', '0', 'get');
$action = input('action', 'delete', 'get'); // 'delete' or 'restore'

if ($userId > 0) {
    if ($action === 'delete') {
        require_admin_permission('customers.delete');
        try {
            $stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = :id AND role_id != 1 LIMIT 1");
            $stmt->execute(['id' => $userId]);
            $name = $stmt->fetchColumn();

            if ($name) {
                // Soft delete
                $pdo->prepare("UPDATE users SET deleted_at = NOW() WHERE id = ?")->execute([$userId]);
                $pdo->prepare("INSERT INTO customer_deleted_records (user_id, deleted_by, reason) VALUES (?, ?, 'Soft deleted from admin panel')")->execute([$userId, current_admin_id()]);

                log_admin_activity('customers.delete', "Soft deleted Customer ID: {$userId} ('{$name}')");
                flash('cust_msg', "Customer '{$name}' moved to trash successfully.", 'success');
            }
        } catch (PDOException $e) {
            error_log('[admin/customers/delete] failed: ' . $e->getMessage());
            flash('cust_msg', 'Failed to delete customer record.', 'error');
        }
    } elseif ($action === 'restore') {
        require_admin_permission('customers.restore');
        try {
            $stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = :id AND role_id != 1 LIMIT 1");
            $stmt->execute(['id' => $userId]);
            $name = $stmt->fetchColumn();

            if ($name) {
                // Restore
                $pdo->prepare("UPDATE users SET deleted_at = NULL WHERE id = ?")->execute([$userId]);
                $pdo->prepare("DELETE FROM customer_deleted_records WHERE user_id = ?")->execute([$userId]);

                log_admin_activity('customers.restore', "Restored Customer ID: {$userId} ('{$name}')");
                flash('cust_msg', "Customer '{$name}' restored successfully.", 'success');
            }
        } catch (PDOException $e) {
            error_log('[admin/customers/restore] failed: ' . $e->getMessage());
            flash('cust_msg', 'Failed to restore customer record.', 'error');
        }
    }
}

$redirectUrl = $_SERVER['HTTP_REFERER'] ?? 'index.php';
header("Location: $redirectUrl");
exit;
