<?php
/**
 * ==========================================================================
 * public/components/offline-banner.php
 * ==========================================================================
 * Sticky Network Offline Notification Banner component.
 * Rendered globally at the top of the body. Toggled via JS event checks.
 * ==========================================================================
 */

declare(strict_types=1);
?>
<div class="offline-status-banner" id="offlineStatusBanner" role="alert" aria-live="assertive">
    <i class="fas fa-triangle-exclamation"></i>
    <span id="offlineStatusMsg">You are currently offline. Pages are loaded from browser cache.</span>
</div>
