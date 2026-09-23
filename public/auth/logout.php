<?php
/**
 * ==========================================================================
 * public/auth/logout.php — Customer Logout Handler
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/../dbconnect.php';

logout_user();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

flash('auth', 'You have been safely signed out.', 'success');
redirect(url_for('login.php'));
