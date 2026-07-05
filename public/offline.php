<?php
/**
 * ==========================================================================
 * public/offline.php — PWA Offline Fallback Page
 * ==========================================================================
 * Renders when internet connection is lost and pages are not in browser cache.
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/dbconnect.php';

$pageTitle = 'You are Offline — ' . site_name();
$pageDescription = 'Please check your internet connection to continue ordering fresh groceries.';

// Header will still load from cache first inside service worker
require_once __DIR__ . '/header.php';
?>

<div class="container" style="margin-top: var(--space-6); margin-bottom: var(--space-6);">
    <div class="cart-empty-page" style="padding: var(--space-6) var(--space-5);">
        
        <!-- Offline illustration icon -->
        <div class="cart-empty-icon" style="color: var(--color-primary); font-size: 72px; margin-bottom: var(--space-4); animation: offlinePulse 2s infinite ease-in-out;">
            <i class="fas fa-wifi-slash"></i>
        </div>

        <h1 style="font-size: var(--fs-md); font-weight: 800; color: var(--color-text); margin: 0 0 var(--space-2) 0;">No Internet Connection</h1>
        <p style="font-size: var(--fs-xs); color: var(--color-text-muted); max-width: 480px; margin: 0 auto var(--space-5) auto; line-height: 1.6;">
            It looks like you are currently offline. Please check your network connection, Wi-Fi or mobile data settings, and try again.
        </p>

        <!-- Offline Action buttons -->
        <div style="display:flex; justify-content:center; gap:var(--space-3); flex-wrap:wrap; margin-bottom: var(--space-5);">
            <button type="button" class="btn btn-primary" onclick="window.location.reload();" style="font-size: var(--fs-xs); font-weight: 700; border-radius: var(--radius-pill); border: none; padding: 12px 28px;">
                <i class="fas fa-rotate-right"></i> Try Reconnect
            </button>
            <a href="<?= url_for('index.php') ?>" class="btn btn-secondary" style="font-size: var(--fs-xs); font-weight: 700; border-radius: var(--radius-pill); border: none; padding: 12px 28px; text-decoration:none; display:inline-flex; align-items:center; justify-content:center;">
                <i class="fas fa-house" style="margin-right:6px;"></i> Store Front
            </a>
        </div>

        <!-- Offline helpful directory list -->
        <div class="offline-cached-list-box" style="background: var(--color-bg); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: var(--space-4); max-width: 440px; margin: 0 auto; text-align: left;">
            <h4 style="font-size: 11px; font-weight: 800; text-transform: uppercase; color: var(--color-text-faint); margin: 0 0 var(--space-3) 0; letter-spacing: 0.5px; text-align: center;">Available Offline</h4>
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:var(--space-2); font-size:var(--fs-xs); font-weight:700;">
                <a href="<?= url_for('index.php') ?>" style="color:var(--color-text); text-decoration:none; display:flex; align-items:center; gap:6px; padding:6px; background:var(--color-surface); border:1px solid var(--color-border); border-radius:var(--radius-sm);"><i class="fas fa-house" style="color:var(--color-primary);"></i> Home</a>
                <a href="<?= url_for('cart.php') ?>" style="color:var(--color-text); text-decoration:none; display:flex; align-items:center; gap:6px; padding:6px; background:var(--color-surface); border:1px solid var(--color-border); border-radius:var(--radius-sm);"><i class="fas fa-cart-shopping" style="color:var(--color-primary);"></i> Cart</a>
                <a href="<?= url_for('wishlist.php') ?>" style="color:var(--color-text); text-decoration:none; display:flex; align-items:center; gap:6px; padding:6px; background:var(--color-surface); border:1px solid var(--color-border); border-radius:var(--radius-sm);"><i class="fas fa-heart" style="color:var(--color-primary);"></i> Wishlist</a>
                <a href="<?= url_for('compare.php') ?>" style="color:var(--color-text); text-decoration:none; display:flex; align-items:center; gap:6px; padding:6px; background:var(--color-surface); border:1px solid var(--color-border); border-radius:var(--radius-sm);"><i class="fas fa-code-compare" style="color:var(--color-primary);"></i> Compare</a>
            </div>
        </div>

    </div>
</div>

<style>
@keyframes offlinePulse {
    0% { transform: scale(1); opacity: 0.8; }
    50% { transform: scale(1.05); opacity: 1; }
    100% { transform: scale(1); opacity: 0.8; }
}
</style>

<?php
require_once __DIR__ . '/footer.php';
?>
