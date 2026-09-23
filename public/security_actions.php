<?php
/**
 * ==========================================================================
 * public/security_actions.php — Customer Security Operations
 * ==========================================================================
 * Handles account-level security actions such as multi-device session revocation.
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/dbconnect.php';

require_login();
require_method('POST');
verify_csrf_or_fail();

$user = current_user();
$userId = (int) $user['id'];
$action = input('action', '');

if ($action === 'sign_out_all_devices') {
    sign_out_all_devices($userId, true);
    flash('security', 'You have been safely signed out of all other devices and sessions.', 'success');
    redirect(url_for('profile.php'));
}

redirect(url_for('profile.php'));
