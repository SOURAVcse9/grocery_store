<?php
/**
 * ==========================================================================
 * admin/layouts/dashboard_layout.php — Main Dashboard Layout Wrapper
 * ==========================================================================
 * Standard layout wrap utility. Include this at the top of admin modules.
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/header.php';
require_once __DIR__ . '/sidebar.php';
?>
<div class="admin-main-panel">
    <?php require_once __DIR__ . '/topbar.php'; ?>
    <main class="admin-content-area" id="adminMainContent">
