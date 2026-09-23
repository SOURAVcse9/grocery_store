<?php
/**
 * ==========================================================================
 * public/auth/google-login.php — Google OAuth Redirect Initiator
 * ==========================================================================
 * Initiates the Google OAuth 2.0 / OpenID Connect authorization code flow.
 * Generates a cryptographically secure state token, stores it in session,
 * and redirects the customer to Google's authentication server.
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/../dbconnect.php';
require_once __DIR__ . '/../includes/google_auth.php';

// If customer is already authenticated, redirect to their account dashboard
if (is_logged_in()) {
    redirect(url_for('account.php'));
}

$config = get_google_oauth_config();
if (empty($config['client_id']) || str_starts_with($config['client_id'], 'YOUR_GOOGLE_CLIENT_ID')) {
    flash('auth', 'Google Sign-In is not configured yet. Please set your GOOGLE_CLIENT_ID in the .env file.', 'warning');
    redirect(url_for('login.php'));
}

// Preserve return URL if specified
$returnUrl = input('return_url', '', 'get');
$authUrl = generate_google_auth_url(!empty($returnUrl) ? $returnUrl : null);

// Redirect to Google's authorization endpoint
header('Location: ' . $authUrl);
exit;
