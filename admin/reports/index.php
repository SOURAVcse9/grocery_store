<?php
/**
 * ==========================================================================
 * admin/reports/index.php — Reports Entry Route Redirector
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/../../public/dbconnect.php';

header('Location: dashboard.php');
exit;
