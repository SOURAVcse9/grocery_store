<?php
/**
 * ==========================================================================
 * public/components/install-app.php
 * ==========================================================================
 * Modern PWA Application Installation Prompt Card.
 * ==========================================================================
 */

declare(strict_types=1);
?>
<div class="pwa-install-drawer" id="pwaInstallDrawer" role="dialog" aria-labelledby="pwaInstallHeading" aria-describedby="pwaInstallDescription">
    <button type="button" class="pwa-close-btn" id="btnPwaCloseX" aria-label="Close installation prompt">
        <i class="fas fa-times"></i>
    </button>
    <div class="pwa-drawer-header">
        <div class="pwa-install-icon" aria-hidden="true">
            <div class="pwa-app-badge">
                <i class="fas fa-basket-shopping"></i>
            </div>
        </div>
        <div class="pwa-install-info">
            <div class="pwa-badge-pill">
                <i class="fas fa-bolt"></i> FAST APP
            </div>
            <h4 class="pwa-install-title" id="pwaInstallHeading">Install GroCo App</h4>
            <p class="pwa-install-desc" id="pwaInstallDescription">Add to home screen for 1-tap checkout &amp; live order tracking.</p>
        </div>
    </div>

    <div class="pwa-install-actions">
        <button type="button" class="btn-pwa-install" id="btnPwaInstallConfirm">
            <i class="fas fa-download" style="margin-right: 4px;"></i> Install Now
        </button>
        <button type="button" class="btn-pwa-dismiss" id="btnPwaInstallDismiss">
            Not Now
        </button>
    </div>
</div>
