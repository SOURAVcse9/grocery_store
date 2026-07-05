<?php
/**
 * ==========================================================================
 * components/toast.php
 * ==========================================================================
 * Included once from footer.php. Emits an empty container that toast.js
 * populates client-side (for AJAX-triggered toasts), plus a JSON payload
 * of any server-side flash messages queued by flash() in functions.php
 * (e.g. after a redirect from process_login.php).
 *
 * Requires: functions.php (get_all_flashes) already loaded via dbconnect.php.
 * ==========================================================================
 */

declare(strict_types=1);

$flashes = get_all_flashes();
?>
<div
    id="toastContainer"
    class="toast-container"
    data-flashes='<?= htmlspecialchars(json_encode($flashes, JSON_UNESCAPED_UNICODE) ?: '{}', ENT_QUOTES, 'UTF-8') ?>'
></div>
