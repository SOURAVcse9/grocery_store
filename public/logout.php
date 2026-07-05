<?php
/**
 * ==========================================================================
 * public/logout.php — Secure Logout Page
 * ==========================================================================
 * Destroys user session tracking, clears remember me cookie registers,
 * and redirects back to the storefront login panel.
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/dbconnect.php';

logout_user();

// Flash success message
flash('auth', 'You have been successfully logged out.', 'success');

redirect(url_for('login.php'));
