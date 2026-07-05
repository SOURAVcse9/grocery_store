<?php
/**
 * ==========================================================================
 * public/ajax/notification.php
 * ==========================================================================
 * AJAX mutations controller for customer notifications (Mark Read, Mark All, Delete).
 * Responds with JSON.
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/../dbconnect.php';

// Only POST allowed
require_method('POST');

// Verify CSRF
verify_csrf_or_fail(true);

if (!is_logged_in()) {
    json_response(false, 'You must be logged in to manage notifications.', [], 401);
}

$userId = current_user_id();
$action = input('action', '');
$notificationId = (int) input('notification_id', '0');

try {
    $pdo = db();

    // A. Mark single notification as read
    if ($action === 'mark_read') {
        if ($notificationId <= 0) {
            json_response(false, 'Invalid notification selection.', [], 400);
        }

        $stmt = $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE id = :id AND user_id = :uid');
        $stmt->execute(['id' => $notificationId, 'uid' => $userId]);

        $unread = unread_notification_count();
        json_response(true, 'Notification marked as read.', ['unread_count' => $unread]);
    }

    // B. Mark all notifications as read
    elseif ($action === 'mark_all_read') {
        $stmt = $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = :uid AND is_read = 0');
        $stmt->execute(['uid' => $userId]);

        json_response(true, 'All notifications marked as read.', ['unread_count' => 0]);
    }

    // C. Delete single notification
    elseif ($action === 'delete') {
        if ($notificationId <= 0) {
            json_response(false, 'Invalid notification selection.', [], 400);
        }

        $stmt = $pdo->prepare('DELETE FROM notifications WHERE id = :id AND user_id = :uid');
        $stmt->execute(['id' => $notificationId, 'uid' => $userId]);

        $unread = unread_notification_count();
        json_response(true, 'Notification deleted successfully.', ['unread_count' => $unread]);
    }

    json_response(false, 'Invalid notification action.', [], 400);

} catch (PDOException $e) {
    error_log('[ajax/notification.php] Error: ' . $e->getMessage());
    json_response(false, 'Failed to update notification due to database error.', [], 500);
}
