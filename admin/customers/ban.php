<?php
/**
 * ==========================================================================
 * admin/customers/ban.php — Ban / Suspend Account Toggle Handler
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/../../public/dbconnect.php';
require_once __DIR__ . '/../middleware/auth_middleware.php';

require_admin_auth();
require_admin_permission('customers.ban');

$pdo = db();
$userId = (int) input('id', '0');
$status = (int) input('status', '0'); // 1 to ban, 0 to unban
$reason = trim(input('reason', 'Administrative decision lockout.'));

if ($userId > 0) {
    try {
        $stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = :id AND role_id != 1 LIMIT 1");
        $stmt->execute(['id' => $userId]);
        $name = $stmt->fetchColumn();

        if ($name) {
            $pdo->prepare("
                UPDATE users 
                SET is_banned = :banned, 
                    ban_reason = :reason, 
                    is_active = :active 
                WHERE id = :id
            ")->execute([
                'banned' => $status,
                'reason' => $status ? $reason : null,
                'active' => $status ? 0 : 1,
                'id'     => $userId
            ]);

            $event = $status ? 'ban' : 'unban';
            $desc = $status ? "Banned: {$reason}" : "Ban lifted.";
            $pdo->prepare("
                INSERT INTO customer_security_logs (user_id, event_type, description, ip_address) 
                VALUES (?, ?, ?, ?)
            ")->execute([$userId, $event, $desc, $_SERVER['REMOTE_ADDR']]);

            log_admin_activity('customers.ban', "Toggled ban status to {$status} for Customer: '{$name}'");
            flash('cust_msg', "Successfully updated ban status for {$name}.", 'success');
        }
    } catch (PDOException $e) {
        error_log('[admin/customers/ban] failed: ' . $e->getMessage());
        flash('cust_msg', 'Failed to update ban status due to server error.', 'error');
    }
}

$redirectUrl = $_SERVER['HTTP_REFERER'] ?? 'index.php';
header("Location: $redirectUrl");
exit;
