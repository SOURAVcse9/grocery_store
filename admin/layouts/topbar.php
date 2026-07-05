<?php
/**
 * ==========================================================================
 * admin/layouts/topbar.php — Admin Dashboard Top Navigation Bar
 * ==========================================================================
 */

declare(strict_types=1);

$pdo = db();

try {
    $unreadNotifCount = (int) $pdo->query("SELECT COUNT(*) FROM dashboard_notifications WHERE is_read = 0")->fetchColumn();
    $recentNotifs = $pdo->query("SELECT * FROM dashboard_notifications ORDER BY created_at DESC LIMIT 5")->fetchAll();
} catch (PDOException $e) {
    $unreadNotifCount = 0;
    $recentNotifs = [];
}
?>
<header class="admin-topbar">
    <div class="topbar-left">
        <!-- Sidebar Hamburger trigger -->
        <button type="button" class="sidebar-toggle-btn" id="btnToggleSidebar" aria-label="Toggle Sidebar Menu">
            <i class="fas fa-bars"></i>
        </button>

        <!-- Search Bar -->
        <form action="<?= BASE_URL ?>/../admin/products/index.php" method="get" class="topbar-search-form">
            <i class="fas fa-magnifying-glass"></i>
            <input type="text" name="search" placeholder="Search products, orders, users..." aria-label="Global Admin Search">
        </form>
    </div>

    <div class="topbar-right">
        <!-- Fullscreen Button -->
        <button type="button" class="topbar-action-btn" id="btnFullscreen" title="Toggle Fullscreen" onclick="toggleFullscreen();" aria-label="Toggle Fullscreen">
            <i class="fas fa-expand"></i>
        </button>

        <!-- Notification Bell Dropdown -->
        <div style="position:relative; display:inline-block;" class="notification-dropdown-wrapper">
            <button type="button" class="topbar-action-btn" id="btnNotifDropdown" aria-label="View Notifications">
                <i class="far fa-bell"></i>
                <?php if ($unreadNotifCount > 0): ?>
                    <span class="badge-dot"></span>
                <?php endif; ?>
            </button>
            
            <!-- Dropdown Pane -->
            <div id="notifDropdownPane" style="display:none; position:absolute; right:0; top:45px; width:300px; background:#fff; border:1px solid var(--admin-color-border); border-radius:var(--radius-md); box-shadow:var(--shadow-md); z-index:1010; padding: var(--space-3) 0;">
                <div style="padding:4px 16px 8px 16px; border-bottom:1px solid var(--admin-color-border); display:flex; justify-content:space-between; align-items:center;">
                    <strong style="font-size:12px; color:var(--admin-color-text);">Notifications</strong>
                    <?php if ($unreadNotifCount > 0): ?>
                        <span style="font-size:10px; background:var(--admin-color-primary-light); color:var(--admin-color-primary-dark); font-weight:700; padding:2px 6px; border-radius:10px;"><?= $unreadNotifCount ?> new</span>
                    <?php endif; ?>
                </div>

                <div style="max-height:260px; overflow-y:auto; display:flex; flex-direction:column;">
                    <?php if (!empty($recentNotifs)): ?>
                        <?php foreach ($recentNotifs as $n): 
                            $icon = 'fa-bell';
                            $color = 'var(--admin-color-primary)';
                            if ($n['type'] === 'low_stock') { $icon = 'fa-triangle-exclamation'; $color = '#f03e3e'; }
                            elseif ($n['type'] === 'order') { $icon = 'fa-receipt'; $color = '#4263eb'; }
                            elseif ($n['type'] === 'user_registered') { $icon = 'fa-user-plus'; $color = '#7048e8'; }
                            elseif ($n['type'] === 'review') { $icon = 'fa-comment-dots'; $color = '#f59f00'; }
                        ?>
                            <a href="<?= !empty($n['link']) ? BASE_URL . '/../admin/' . $n['link'] : '#' ?>" style="padding:10px 16px; border-bottom:1px solid var(--admin-color-border); display:flex; gap:10px; align-items:flex-start; text-decoration:none; transition:background 0.2s;" class="notif-item">
                                <span style="color:<?= $color ?>; font-size:14px; margin-top:2px; width:20px; text-align:center;"><i class="fas <?= $icon ?>"></i></span>
                                <div style="display:flex; flex-direction:column; gap:2px; flex:1;">
                                    <strong style="font-size:11px; color:var(--admin-color-text); font-weight:700;"><?= e($n['title']) ?></strong>
                                    <span style="font-size:10px; color:var(--admin-color-text-muted); line-height:1.4;"><?= e($n['message']) ?></span>
                                    <span style="font-size:9px; color:var(--admin-color-text-faint); margin-top:2px;"><?= time_ago($n['created_at']) ?></span>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="padding:20px; text-align:center; font-size:12px; color:var(--admin-color-text-faint); margin:0;">Zero system alerts.</p>
                    <?php endif; ?>
                </div>

                <div style="padding:8px 16px 0 16px; border-top:1px solid var(--admin-color-border); text-align:center;">
                    <a href="<?= BASE_URL ?>/../admin/notifications/index.php" style="font-size:11px; color:var(--admin-color-primary); font-weight:700; text-decoration:none;">View All Alerts</a>
                </div>
            </div>
        </div>

        <!-- Profile Dropdown Menu -->
        <div style="position:relative; display:inline-block;" class="profile-dropdown-wrapper">
            <div class="topbar-user-profile" id="btnProfileDropdown">
                <div class="topbar-avatar">
                    <img src="<?= e($__admin_avatar) ?>" alt="<?= e($__admin['full_name']) ?>">
                </div>
                <div class="topbar-user-info hide-mobile">
                    <span class="topbar-username"><?= e($__admin['full_name']) ?></span>
                    <span class="topbar-role-badge"><?= e($__admin['role_name']) ?></span>
                </div>
                <i class="fas fa-chevron-down" style="font-size:10px; color:var(--admin-color-text-faint);"></i>
            </div>
            
            <!-- Profile Dropdown Pane -->
            <div id="profileDropdownPane" style="display:none; position:absolute; right:0; top:52px; width:200px; background:#fff; border:1px solid var(--admin-color-border); border-radius:var(--radius-md); box-shadow:var(--shadow-md); z-index:1010; padding: var(--space-2) 0;">
                <a href="<?= BASE_URL ?>/../admin/profile.php" style="padding:10px 16px; display:flex; align-items:center; gap:10px; font-size:12px; color:var(--admin-color-text); text-decoration:none;" class="dropdown-item">
                    <i class="far fa-user-circle" style="font-size:14px; width:16px;"></i> My Profile
                </a>
                <a href="<?= BASE_URL ?>/../admin/settings/index.php" style="padding:10px 16px; display:flex; align-items:center; gap:10px; font-size:12px; color:var(--admin-color-text); text-decoration:none;" class="dropdown-item">
                    <i class="fas fa-sliders" style="font-size:14px; width:16px;"></i> System Settings
                </a>
                <div style="border-top:1px solid var(--admin-color-border); margin-block:6px;"></div>
                <a href="<?= BASE_URL ?>/../admin/logout.php" style="padding:10px 16px; display:flex; align-items:center; gap:10px; font-size:12px; color:#f03e3e; text-decoration:none;" class="dropdown-item">
                    <i class="fas fa-arrow-right-from-bracket" style="font-size:14px; width:16px;"></i> Sign Out
                </a>
            </div>
        </div>

    </div>
</header>

<script>
// Toggle Fullscreen mode
function toggleFullscreen() {
    if (!document.fullscreenElement) {
        document.documentElement.requestFullscreen().catch(err => {
            console.error(`Error enabling fullscreen: ${err.message}`);
        });
    } else {
        document.exitFullscreen();
    }
}

// Toggle Dropdown panels and click-away closers
document.addEventListener('DOMContentLoaded', () => {
    const btnNotif = document.getElementById('btnNotifDropdown');
    const paneNotif = document.getElementById('notifDropdownPane');
    const btnProfile = document.getElementById('btnProfileDropdown');
    const paneProfile = document.getElementById('profileDropdownPane');

    if (btnNotif && paneNotif) {
        btnNotif.addEventListener('click', (e) => {
            e.stopPropagation();
            paneNotif.style.display = paneNotif.style.display === 'none' ? 'block' : 'none';
            if (paneProfile) paneProfile.style.display = 'none';
        });
    }

    if (btnProfile && paneProfile) {
        btnProfile.addEventListener('click', (e) => {
            e.stopPropagation();
            paneProfile.style.display = paneProfile.style.display === 'none' ? 'block' : 'none';
            if (paneNotif) paneNotif.style.display = 'none';
        });
    }

    document.addEventListener('click', () => {
        if (paneNotif) paneNotif.style.display = 'none';
        if (paneProfile) paneProfile.style.display = 'none';
    });
});
</script>
