<?php
/**
 * ==========================================================================
 * public/api/notifications.php
 * ==========================================================================
 * AJAX API endpoint to fetch notifications, count unreads, and render
 * dropdown panels.
 * Responds with JSON.
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/../dbconnect.php';

// Only GET allowed
require_method('GET');

if (!is_logged_in()) {
    json_response(false, 'Unauthorized.', ['unread_count' => 0], 401);
}

$userId = current_user_id();
$action = input('action', '', 'get');

try {
    $pdo = db();

    // A. Count unread notifications (Polling check)
    if ($action === 'count_latest') {
        $unread = unread_notification_count();
        json_response(true, 'Count retrieved.', [
            'unread_count' => $unread
        ]);
    }

    // B. Pre-render Header dropdown HTML
    if ($action === 'dropdown') {
        $unread = unread_notification_count();
        
        ob_start();
        include PUBLIC_PATH . '/components/notification-dropdown.php';
        $dropdownHtml = ob_get_clean();

        json_response(true, 'Dropdown rendered.', [
            'unread_count' => $unread,
            'html'          => $dropdownHtml
        ]);
    }

    // C. Paginated Notification Center list
    $page = (int) input('page', '1', 'get');
    if ($page < 1) {
        $page = 1;
    }
    
    $limit = 8;
    $offset = ($page - 1) * $limit;

    // Count total rows
    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = :uid');
    $countStmt->execute(['uid' => $userId]);
    $totalNotifications = (int) $countStmt->fetchColumn();

    // Query rows
    $stmt = $pdo->prepare('
        SELECT * FROM notifications 
        WHERE user_id = :uid 
        ORDER BY created_at DESC 
        LIMIT :limit OFFSET :offset
    ');
    $stmt->bindValue('uid', $userId, PDO::PARAM_INT);
    $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $notifications = $stmt->fetchAll();

    $unread = unread_notification_count();

    // Pre-render Cards HTML
    ob_start();
    if (!empty($notifications)) {
        foreach ($notifications as $notification) {
            include PUBLIC_PATH . '/components/notification-card.php';
        }
    } else {
        ?>
        <div class="cart-empty-page" style="box-shadow:none; border:none; background:transparent;">
            <div class="cart-empty-icon" style="color:var(--color-text-faint);"><i class="far fa-bell-slash"></i></div>
            <h2>No Notifications Yet</h2>
            <p>We will alert you here on order status changes, new coupon offers, and security warnings!</p>
        </div>
        <?php
    }
    $html = ob_get_clean();

    // Pre-render Pagination HTML
    $totalPages = (int) ceil($totalNotifications / $limit);
    $baseUrl = 'notifications.php';
    $queryParams = $_GET;
    unset($queryParams['page']);

    ob_start();
    if ($totalPages > 1) {
        include PUBLIC_PATH . '/components/pagination.php';
    }
    $paginationHtml = ob_get_clean();

    json_response(true, 'Notifications loaded.', [
        'unread_count'      => $unread,
        'total'             => $totalNotifications,
        'html'              => $html,
        'pagination_html'   => $paginationHtml
    ]);

} catch (PDOException $e) {
    error_log('[api/notifications.php] Error: ' . $e->getMessage());
    json_response(false, 'Database error.', [], 500);
}
