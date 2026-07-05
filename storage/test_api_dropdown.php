<?php
/**
 * ==========================================================================
 * test_api_dropdown.php
 * ==========================================================================
 */
declare(strict_types=1);
$_SERVER['HTTP_USER_AGENT'] = 'unknown';
$_SERVER['SCRIPT_NAME'] = '/grocery-store/public/api/notifications.php';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
require __DIR__ . '/../public/dbconnect.php';
$_SESSION['user_id'] = 1;
$_SESSION['user'] = [
    'id'        => 1,
    'role_name' => 'customer',
    'full_name' => 'QA Tester',
    'avatar'    => ''
];
$_GET['action'] = 'dropdown';
require __DIR__ . '/../public/api/notifications.php';
