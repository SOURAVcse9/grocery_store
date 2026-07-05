<?php
/**
 * ==========================================================================
 * public/components/notification-item.php
 * ==========================================================================
 * Reusable Compact Notification Item component (used inside the dropdown list).
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

// Resolve FontAwesome icons & classes based on category
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
<div class="notification-dropdown-item <?= $isRead ? 'read' : 'unread' ?>" data-id="<?= $id ?>">
    <div class="notif-dropdown-icon-wrapper notif-type-<?= $class ?>">
        <i class="fas <?= $icon ?>"></i>
    </div>
    
    <div class="notif-dropdown-details">
        <h5 class="notif-dropdown-title"><?= e($title) ?></h5>
        <p class="notif-dropdown-msg"><?= e(mb_strimwidth($msg, 0, 70, '...')) ?></p>
        <span class="notif-dropdown-time"><?= $time ?></span>
    </div>

    <?php if (!$isRead): ?>
        <span class="notif-dropdown-dot" title="Mark as read"></span>
    <?php endif; ?>
</div>
