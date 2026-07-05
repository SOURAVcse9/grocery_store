<?php
/**
 * ==========================================================================
 * header.php
 * ==========================================================================
 * Included at the very top of every storefront page, right after
 * dbconnect.php. Emits <!DOCTYPE html> through the opening of <main>;
 * the including page prints its own content, then requires footer.php.
 *
 * Requires: dbconnect.php (db, session, auth, helpers, functions, csrf)
 *           to already be loaded by the calling page.
 * ==========================================================================
 */

declare(strict_types=1);

// --------------------------------------------------------------------------
// Language switch (?lang=en|bn) — must run before any HTML is emitted.
// --------------------------------------------------------------------------
if (isset($_GET['lang']) && in_array($_GET['lang'], ['en', 'bn'], true)) {
    $_SESSION['lang'] = $_GET['lang'];
}
$lang = current_lang();

// --------------------------------------------------------------------------
// Data needed by the header markup below
// --------------------------------------------------------------------------
$__nav_categories = db()
    ->query('SELECT id, name, slug FROM categories WHERE parent_id IS NULL AND is_active = 1 ORDER BY name ASC LIMIT 10')
    ->fetchAll();

$__cart_count         = cart_item_count();
$__wishlist_count     = wishlist_item_count();
$__notification_count = unread_notification_count();
$__user               = current_user();

// Page-level overrides: any page may set these BEFORE requiring header.php.
$pageTitle       = $pageTitle ?? site_name() . ' — Fresh Groceries Delivered';
$pageDescription = $pageDescription ?? 'Order fresh groceries, vegetables, dairy, and daily essentials online with fast delivery.';
$pageCanonical   = $pageCanonical ?? current_url();
?>
<!DOCTYPE html>
<html lang="<?= e($lang) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <?php include PUBLIC_PATH . '/components/meta-tags.php'; ?>

    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <link rel="stylesheet" href="<?= asset('css/style.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/header.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/footer.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/components.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/notifications.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/performance.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/pwa.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/accessibility.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/newsletter.css') ?>">
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#0b7285">
    <?php if (!empty($extraStylesheets) && is_array($extraStylesheets)): ?>
        <?php foreach ($extraStylesheets as $__css): ?>
            <link rel="stylesheet" href="<?= asset($__css) ?>">
        <?php endforeach; ?>
    <?php endif; ?>
    <link rel="stylesheet" href="<?= asset('css/responsive.css') ?>">
</head>
<body>
<?php
// Fetch announcement bar details
$announcementActive = false;
$announcementText = '';
$popupActive = false;
$popupTitle = '';
$popupMessage = '';
try {
    $stmtSettings = db()->query("SELECT key_name, value FROM settings WHERE key_name IN ('announcement_bar_active', 'announcement_bar_text', 'popup_notice_active', 'popup_notice_title', 'popup_notice_message')");
    $settingsData = [];
    while ($row = $stmtSettings->fetch()) {
        $settingsData[$row['key_name']] = $row['value'];
    }
    
    $announcementActive = (($settingsData['announcement_bar_active'] ?? '0') === '1');
    $announcementText = $settingsData['announcement_bar_text'] ?? '';
    
    $popupActive = (($settingsData['popup_notice_active'] ?? '0') === '1');
    $popupTitle = $settingsData['popup_notice_title'] ?? 'Notice';
    $popupMessage = $settingsData['popup_notice_message'] ?? '';
} catch (PDOException $e) {
    error_log('[storefront/header] settings fetch fail: ' . $e->getMessage());
}
?>

<?php if ($announcementActive && !empty($announcementText)): ?>
    <div style="background-color: #0ca678; color: #ffffff; text-align: center; padding: 8px 12px; font-size: 13px; font-weight: 600; letter-spacing: 0.5px; z-index: 9999; position: relative;">
        <?= e($announcementText) ?>
    </div>
<?php endif; ?>

<?php if ($popupActive && !empty($popupMessage)): ?>
    <div id="settingsPopupModal" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 100000; display: flex; align-items: center; justify-content: center;">
        <div style="background: #fff; padding: 24px; border-radius: 8px; max-width: 450px; width: 90%; box-shadow: 0 4px 20px rgba(0,0,0,0.15); text-align: center; position: relative;">
            <h3 style="margin-top: 0; color: #0ca678; font-size: 18px; font-weight: 700;"><?= e($popupTitle) ?></h3>
            <p style="color: #555; font-size: 14px; line-height: 1.6; margin: 16px 0;"><?= e($popupMessage) ?></p>
            <button onclick="document.getElementById('settingsPopupModal').style.display='none';" style="background: #0ca678; color: #fff; border: none; padding: 8px 24px; border-radius: 20px; font-weight: 700; cursor: pointer; outline: none;">Close</button>
        </div>
    </div>
<?php endif; ?>

<?php include PUBLIC_PATH . '/components/offline-banner.php'; ?>

<!-- Mobile overlay + off-canvas menu -->
<div id="mobileOverlay" class="mobile-overlay"></div>
<div id="mobileMenu" class="mobile-menu">
    <div class="mobile-menu-close" id="closeMenuBtn"><i class="fas fa-times"></i></div>
    <ul>
        <li><a href="<?= url_for('index.php') ?>"><i class="fas fa-house"></i> <?= e(t('home')) ?></a></li>
        <li><a href="<?= url_for('products.php') ?>"><i class="fas fa-bag-shopping"></i> <?= e(t('shop')) ?></a></li>
        <li><a href="<?= url_for('categories.php') ?>"><i class="fas fa-border-all"></i> <?= e(t('categories')) ?></a></li>
        <li><a href="<?= url_for('wishlist.php') ?>"><i class="fas fa-heart"></i> <?= e(t('wishlist')) ?></a></li>
        <li><a href="<?= url_for('compare.php') ?>"><i class="fas fa-code-compare"></i> <?= e(t('compare')) ?></a></li>
        <li><a href="<?= url_for('about.php') ?>"><i class="fas fa-circle-info"></i> <?= e(t('about')) ?></a></li>
        <li><a href="<?= url_for('contact.php') ?>"><i class="fas fa-envelope"></i> <?= e(t('contact')) ?></a></li>
    </ul>
    <div class="lang-switch" style="margin-top:24px;">
        <i class="fas fa-globe"></i>
        <a href="?lang=en" class="<?= $lang === 'en' ? 'active' : '' ?>">English</a> |
        <a href="?lang=bn" class="<?= $lang === 'bn' ? 'active' : '' ?>">বাংলা</a>
    </div>
</div>

<header class="site-header" id="siteHeader">
    <div class="container header-top">
        <button class="mobile-menu-toggle" id="openMenuBtn" aria-label="Open menu">
            <i class="fas fa-bars"></i>
        </button>

        <a href="<?= url_for('index.php') ?>" class="header-brand">
            <i class="fas fa-leaf"></i> <span><?= e(site_name()) ?></span>
        </a>

        <div class="header-search">
            <form action="<?= url_for('search.php') ?>" method="get" role="search">
                <input
                    type="text"
                    name="q"
                    id="smartSearch"
                    autocomplete="off"
                    placeholder="<?= e(t('search_placeholder')) ?>"
                    value="<?= e($_GET['q'] ?? '') ?>"
                >
                <button type="submit" aria-label="Search"><i class="fas fa-search"></i></button>
            </form>
            <div id="searchSuggestions" class="search-suggestions"></div>
        </div>

        <div class="header-actions">
            <div class="lang-switch hide-mobile">
                <a href="?lang=en" class="<?= $lang === 'en' ? 'active' : '' ?>">EN</a> /
                <a href="?lang=bn" class="<?= $lang === 'bn' ? 'active' : '' ?>">বাং</a>
            </div>

            <a href="<?= url_for('wishlist.php') ?>" class="icon-link hide-mobile" title="<?= e(t('wishlist')) ?>">
                <i class="far fa-heart"></i>
                <?php if ($__wishlist_count > 0): ?>
                    <span class="icon-badge" id="wishlistCount"><?= (int) $__wishlist_count ?></span>
                <?php endif; ?>
            </a>

            <?php if ($__user): ?>
                <div class="header-notif-dropdown-wrapper">
                    <button type="button" class="icon-link hide-mobile" id="btnNotificationBell" title="<?= e(t('notifications')) ?>" aria-label="Notifications Dropdown" style="background:none; border:none; cursor:pointer;">
                        <i class="far fa-bell"></i>
                        <span class="icon-badge" id="headerNotifBadge" style="display: <?= $__notification_count > 0 ? 'flex' : 'none' ?>;"><?= (int) $__notification_count ?></span>
                    </button>
                    <!-- Floating Panel Drawer -->
                    <div class="notification-dropdown-panel" id="notificationsDropdownPanel">
                        <?php include PUBLIC_PATH . '/components/notification-dropdown.php'; ?>
                    </div>
                </div>
            <?php endif; ?>

            <a href="<?= url_for('cart.php') ?>" class="icon-link" title="<?= e(t('cart')) ?>">
                <i class="fas fa-cart-shopping"></i>
                <span class="icon-badge" id="cartCount"><?= (int) $__cart_count ?></span>
            </a>

            <?php if ($__user): ?>
                <div class="user-menu">
                    <div class="user-menu-trigger">
                        <img
                            class="user-avatar"
                            src="<?= e(image_url($__user['avatar'], 'users')) ?>"
                            alt="<?= e($__user['full_name']) ?>"
                        >
                    </div>
                    <div class="user-dropdown">
                        <div class="user-dropdown-head">
                            <strong><?= e($__user['full_name']) ?></strong>
                            <span><?= $__user['role_name'] === 'admin' ? 'Administrator' : 'Customer' ?></span>
                        </div>
                        <a href="<?= url_for('account.php') ?>"><i class="fas fa-user-circle"></i> <?= e(t('account')) ?></a>
                        <a href="<?= url_for('orders.php') ?>"><i class="fas fa-box"></i> <?= e(t('orders')) ?></a>
                        <a href="<?= url_for('profile.php') ?>"><i class="fas fa-gear"></i> <?= e(t('profile')) ?></a>
                        <?php if ($__user['role_name'] === 'admin' || isset($_SESSION['admin_id'])): ?>
                            <a href="<?= BASE_URL ?>/../admin/index.php"><i class="fas fa-shield-halved"></i> Admin Panel</a>
                        <?php endif; ?>
                        <a href="<?= url_for('logout.php') ?>" class="danger"><i class="fas fa-arrow-right-from-bracket"></i> <?= e(t('logout')) ?></a>
                    </div>
                </div>
            <?php else: ?>
                <a href="<?= url_for('login.php') ?>" class="btn-login">
                    <i class="far fa-user"></i> <span class="hide-mobile"><?= e(t('login')) ?></span>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <nav class="header-nav">
        <div class="container">
            <ul>
                <li><a href="<?= url_for('index.php') ?>"><?= e(t('home')) ?></a></li>
                <li class="has-dropdown">
                    <a href="<?= url_for('categories.php') ?>">
                        <i class="fas fa-border-all"></i> <?= e(t('categories')) ?>
                        <i class="fas fa-chevron-down dropdown-caret"></i>
                    </a>
                    <?php if ($__nav_categories): ?>
                        <div class="mega-dropdown">
                            <?php foreach ($__nav_categories as $__cat): ?>
                                <a href="<?= url_for('products.php') ?>?category=<?= e($__cat['slug']) ?>">
                                    <?= e($__cat['name']) ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </li>
                <li><a href="<?= url_for('products.php') ?>"><?= e(t('shop')) ?></a></li>
                <li><a href="<?= url_for('about.php') ?>"><?= e(t('about')) ?></a></li>
                <li><a href="<?= url_for('contact.php') ?>"><?= e(t('contact')) ?></a></li>
            </ul>
        </div>
    </nav>
</header>

<main class="site-main">
