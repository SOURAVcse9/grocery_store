<?php
/**
 * ==========================================================================
 * public/components/install-app.php
 * ==========================================================================
 * Custom PWA Application Installation Prompt Drawer.
 * Slid up from bottom-left on desktop/mobile browsers supporting installation.
 * ==========================================================================
 */

declare(strict_types=1);
?>
<div class="pwa-install-drawer" id="pwaInstallDrawer" role="dialog" aria-labelledby="pwaInstallHeading" aria-describedby="pwaInstallDescription">
    <div class="pwa-install-icon">
        <img src="<?= e(asset('images/ui/logo.png')) ?>" alt="Groco Store App Logo" width="48" height="48">
    </div>
    
    <div class="pwa-install-info">
        <h4 class="pwa-install-title" id="pwaInstallHeading">Install Groco App</h4>
        <p class="pwa-install-desc" id="pwaInstallDescription">Add Groco to your screen for fast access and offline orders!</p>
    </div>

    <div class="pwa-install-actions">
        <button type="button" class="btn-pwa-install" id="btnPwaInstallConfirm">Install</button>
        <button type="button" class="btn-pwa-dismiss" id="btnPwaInstallDismiss">Not Now</button>
    </div>
</div>
