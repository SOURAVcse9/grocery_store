<?php
/**
 * ==========================================================================
 * admin/shifts/index.php
 * ==========================================================================
 * Cashier drawer shifts logs entrypoint.
 * Redirects or forwards queries directly to the POS shift control dashboard.
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/../../public/dbconnect.php';
require_once __DIR__ . '/../middleware/auth_middleware.php';

require_admin_auth();
header('Location: ' . BASE_URL . '/../admin/pos/shift.php');
exit;
