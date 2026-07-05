<?php
/**
 * ==========================================================================
 * admin/logout.php — Admin Portal Logout Processor
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/../public/dbconnect.php';
require_once __DIR__ . '/includes/auth_helpers.php';
require_once __DIR__ . '/middleware/auth_middleware.php';

// Log out active admin session
admin_logout();

// Redirect to login form
redirect_to_admin_login();
