<?php
/**
 * ==========================================================================
 * public/components/notification-card.php
 * ==========================================================================
 * Full-width Rich Notification Card component.
 * Expects:
 *   - $notification (array)
 * ==========================================================================
 */

declare(strict_types=1);

if (!isset($notification) || !is_array($notification)) {
    return;
}

$id = (int) $notification['id'];
$title = $notification['title'];
$msg = $notification['message'];
$type = strtolower($notification['type'] ?? 'general');
$isRead = (bool) ($notification['is_read'] ?? false);
$time = time_ago($notification['created_at']);

// Resolve FontAwesome icons
$icon = 'fa-bell';
$class = 'general';

if ($type === 'order') {
    $icon = 'fa-box-open';
    $class = 'order';
} elseif ($type === 'promo' || $type === 'flash_sale') {
    $icon = 'fa-tags';
    $class = 'promo';
} elseif ($type === 'coupon') {
    $icon = 'fa-ticket';
    $class = 'coupon';
} elseif ($type === 'account') {
    $icon = 'fa-user-gear';
    $class = 'account';
} elseif ($type === 'security') {
    $icon = 'fa-shield-halved';
    $class = 'security';
}
?>
<div class="notification-card <?= $isRead ? 'read' : 'unread' ?>" data-id="<?= $id ?>" id="notification-card-<?= $id ?>">
    <!-- Icon column -->
    <div class="notification-card-icon-box notif-type-<?= $class ?>">
        <i class="fas <?= $icon ?>"></i>
    </div>

    <!-- Details column -->
    <div class="notification-card-details">
        <h4 class="notification-card-title"><?= e($title) ?></h4>
        <p class="notification-card-message"><?= e($msg) ?></p>
        <span class="notification-card-time"><?= $time ?></span>
    </div>

    <!-- Actions column -->
    <div class="notification-card-actions">
        <?php if (!$isRead): ?>
            <button type="button" class="btn-notif-card-action btn-mark-read" data-id="<?= $id ?>" title="Mark as read" aria-label="Mark item as read">
                <i class="fas fa-check"></i>
            </button>
        <?php endif; ?>
        
        <button type="button" class="btn-notif-card-action btn-delete-notif" data-id="<?= $id ?>" title="Delete notification" aria-label="Delete item">
            <i class="far fa-trash-can"></i>
        </button>
    </div>
</div>
