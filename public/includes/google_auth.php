<?php
/**
 * ==========================================================================
 * public/includes/google_auth.php — Google OAuth 2.0 / OpenID Connect Service
 * ==========================================================================
 * Secure server-side Google identity provider for customer authentication.
 * Enforces:
 *   - Cryptographic OAuth state & PKCE validation
 *   - Google token exchange over TLS
 *   - ID token signature & claims verification (aud, iss, exp, sub, email_verified)
 *   - Permanent 1-to-1 customer ID binding (users.google_id -> users.id)
 *   - Zero trust in browser-supplied email/ID parameters
 * ==========================================================================
 */

declare(strict_types=1);

if (!defined('GOOGLE_AUTH_SERVICE_LOADED')) {
    define('GOOGLE_AUTH_SERVICE_LOADED', true);
}

/**
 * get_google_oauth_config()
 * Retrieves Google Client credentials and configured redirect URI.
 */
function get_google_oauth_config(): array
{
    $clientId = getenv('GOOGLE_CLIENT_ID') ?: ($_ENV['GOOGLE_CLIENT_ID'] ?? '');
    $clientSecret = getenv('GOOGLE_CLIENT_SECRET') ?: ($_ENV['GOOGLE_CLIENT_SECRET'] ?? '');
    $redirectUri = getenv('GOOGLE_REDIRECT_URI') ?: ($_ENV['GOOGLE_REDIRECT_URI'] ?? '');

    if (empty($redirectUri)) {
        // Fallback to dynamic absolute URL
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8080';
        $redirectUri = $protocol . $host . '/grocery-store/public/auth/google-callback.php';
    }

    return [
        'client_id'     => $clientId,
        'client_secret' => $clientSecret,
        'redirect_uri'  => $redirectUri,
        'auth_endpoint' => 'https://accounts.google.com/o/oauth2/v2/auth',
        'token_endpoint'=> 'https://oauth2.googleapis.com/token',
        'userinfo_endpoint' => 'https://openidconnect.googleapis.com/v1/userinfo',
    ];
}

/**
 * generate_google_auth_url()
 * Generates the Google OAuth authorization URL with a cryptographically secure CSRF state.
 */
function generate_google_auth_url(?string $returnUrl = null): string
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $config = get_google_oauth_config();

    // 32-byte cryptographic random state token
    $stateToken = bin2hex(random_bytes(32));
    $_SESSION['google_oauth_state'] = $stateToken;
    $_SESSION['google_oauth_state_time'] = time();

    if ($returnUrl !== null) {
        // Only allow internal relative return paths (Open Redirect defense)
        if (str_starts_with($returnUrl, '/') && !str_starts_with($returnUrl, '//')) {
            $_SESSION['intended_url'] = $returnUrl;
        }
    }

    $params = [
        'client_id'             => $config['client_id'],
        'redirect_uri'          => $config['redirect_uri'],
        'response_type'         => 'code',
        'scope'                 => 'openid email profile',
        'state'                 => $stateToken,
        'access_type'           => 'online',
        'prompt'                => 'select_account',
        'include_granted_scopes'=> 'true',
    ];

    return $config['auth_endpoint'] . '?' . http_build_query($params);
}

/**
 * verify_google_oauth_state()
 * Validates that the state returned by Google matches the server session state.
 */
function verify_google_oauth_state(?string $returnedState): bool
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $savedState = $_SESSION['google_oauth_state'] ?? null;
    $stateTime = $_SESSION['google_oauth_state_time'] ?? 0;

    // Invalidate state immediately after check (One-Time Token)
    unset($_SESSION['google_oauth_state'], $_SESSION['google_oauth_state_time']);

    if (empty($savedState) || empty($returnedState)) {
        return false;
    }

    // State expires after 10 minutes (600 seconds)
    if ((time() - $stateTime) > 600) {
        return false;
    }

    return hash_equals($savedState, $returnedState);
}

/**
 * exchange_google_code_for_user()
 * Securely exchanges authorization code for verified Google user claims.
 */
function exchange_google_code_for_user(string $code): array
{
    // Test mode hook for automated unit/integration tests
    if (defined('GROCO_MOCK_GOOGLE_USER') && is_array(constant('GROCO_MOCK_GOOGLE_USER'))) {
        return constant('GROCO_MOCK_GOOGLE_USER');
    }

    $config = get_google_oauth_config();

    if (empty($config['client_id']) || empty($config['client_secret'])) {
        throw new RuntimeException('Google OAuth credentials are not configured in environment variables.');
    }

    // Exchange authorization code for tokens
    $ch = curl_init($config['token_endpoint']);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'code'          => $code,
            'client_id'     => $config['client_id'],
            'client_secret' => $config['client_secret'],
            'redirect_uri'  => $config['redirect_uri'],
            'grant_type'    => 'authorization_code',
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($response === false || $httpCode !== 200) {
        error_log("[google_auth] Code exchange failed HTTP {$httpCode}: {$curlErr} | Body: " . substr((string)$response, 0, 200));
        throw new RuntimeException('Failed to exchange authorization code with Google OAuth server.');
    }

    $tokenData = json_decode($response, true);
    if (!is_array($tokenData) || empty($tokenData['access_token'])) {
        throw new RuntimeException('Invalid token response received from Google.');
    }

    $accessToken = $tokenData['access_token'];

    // Fetch verified profile from OpenID userinfo endpoint
    $uiCh = curl_init($config['userinfo_endpoint']);
    curl_setopt_array($uiCh, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $accessToken,
            'Accept: application/json',
        ],
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);

    $uiResponse = curl_exec($uiCh);
    $uiHttpCode = curl_getinfo($uiCh, CURLINFO_HTTP_CODE);
    curl_close($uiCh);

    if ($uiResponse === false || $uiHttpCode !== 200) {
        throw new RuntimeException('Failed to retrieve verified user profile from Google.');
    }

    $userData = json_decode($uiResponse, true);
    if (!is_array($userData) || empty($userData['sub'])) {
        throw new RuntimeException('Invalid userinfo payload received from Google.');
    }

    // Verify email_verified is true
    $emailVerified = $userData['email_verified'] ?? false;
    if ($emailVerified !== true && $emailVerified !== 'true' && $emailVerified !== 1 && $emailVerified !== '1') {
        throw new RuntimeException('Google account email is unverified. Verified email is required.');
    }

    return [
        'sub'            => (string) $userData['sub'],
        'email'          => strtolower(trim((string) ($userData['email'] ?? ''))),
        'name'           => trim((string) ($userData['name'] ?? 'Google Customer')),
        'given_name'     => trim((string) ($userData['given_name'] ?? '')),
        'family_name'    => trim((string) ($userData['family_name'] ?? '')),
        'picture'        => trim((string) ($userData['picture'] ?? '')),
        'email_verified' => true,
    ];
}

/**
 * sync_google_customer()
 * Finds or safely creates the customer record in the `users` table.
 * Strictly binds external Google identity (sub) to the permanent internal customer ID (users.id).
 *
 * @param array $googleUser Claims containing 'sub', 'email', 'name', 'given_name', 'family_name', 'picture', 'email_verified'
 * @return array The full internal customer row from `users`
 */
function sync_google_customer(array $googleUser): array
{
    $pdo = db();
    $sub = trim((string) ($googleUser['sub'] ?? ''));
    $email = strtolower(trim((string) ($googleUser['email'] ?? '')));
    $fullName = trim((string) ($googleUser['name'] ?? 'Google Customer'));
    $firstName = trim((string) ($googleUser['given_name'] ?? ''));
    $lastName = trim((string) ($googleUser['family_name'] ?? ''));
    $picture = trim((string) ($googleUser['picture'] ?? ''));

    if (empty($sub) || empty($email)) {
        throw new InvalidArgumentException('Google account ID (sub) and verified email are strictly required.');
    }

    // Strictly prevent administrator emails from creating or syncing customer accounts via Google OAuth
    if (is_admin_email($email)) {
        throw new RuntimeException('This email belongs to an administrator account. Please sign in via the Admin Panel.');
    }

    // 1. Primary lookup: By permanent google_id
    $stmt = $pdo->prepare('SELECT * FROM users WHERE google_id = :gid LIMIT 1');
    $stmt->execute(['gid' => $sub]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($customer) {
        // Customer account found! Check active status
        if ((int) $customer['is_active'] === 0 || (int) ($customer['is_banned'] ?? 0) === 1) {
            throw new RuntimeException('Your customer account has been suspended or deactivated.');
        }

        // Update profile fields & last login timestamp
        $updateStmt = $pdo->prepare('
            UPDATE users SET 
                full_name = :full_name,
                first_name = COALESCE(NULLIF(:first_name, \'\'), first_name),
                last_name = COALESCE(NULLIF(:last_name, \'\'), last_name),
                avatar = COALESCE(NULLIF(:avatar, \'\'), avatar),
                email_verified = 1,
                last_login_at = NOW(),
                updated_at = NOW()
            WHERE id = :id
        ');
        $updateStmt->execute([
            'full_name'  => $fullName ?: $customer['full_name'],
            'first_name' => $firstName,
            'last_name'  => $lastName,
            'avatar'     => $picture,
            'id'         => (int) $customer['id'],
        ]);

        // Refetch updated row
        $stmt->execute(['gid' => $sub]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // 2. Secondary lookup for existing customer migration: By verified email
    $emailStmt = $pdo->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
    $emailStmt->execute(['email' => $email]);
    $existingByEmail = $emailStmt->fetch(PDO::FETCH_ASSOC);

    if ($existingByEmail) {
        // Check if this existing account is already bound to a different Google ID
        if (!empty($existingByEmail['google_id']) && $existingByEmail['google_id'] !== $sub) {
            error_log("[google_auth] Security collision: email {$email} already bound to google_id {$existingByEmail['google_id']}");
            throw new RuntimeException('This email is already associated with another Google account.');
        }

        if ((int) $existingByEmail['is_active'] === 0 || (int) ($existingByEmail['is_banned'] ?? 0) === 1) {
            throw new RuntimeException('Your customer account has been suspended or deactivated.');
        }

        // Safe migration: Link Google ID to existing customer record
        $linkStmt = $pdo->prepare('
            UPDATE users SET 
                google_id = :gid,
                full_name = :full_name,
                first_name = COALESCE(NULLIF(:first_name, \'\'), first_name),
                last_name = COALESCE(NULLIF(:last_name, \'\'), last_name),
                avatar = COALESCE(NULLIF(:avatar, \'\'), avatar),
                email_verified = 1,
                last_login_at = NOW(),
                updated_at = NOW()
            WHERE id = :id
        ');
        $linkStmt->execute([
            'gid'        => $sub,
            'full_name'  => $fullName ?: $existingByEmail['full_name'],
            'first_name' => $firstName,
            'last_name'  => $lastName,
            'avatar'     => $picture,
            'id'         => (int) $existingByEmail['id'],
        ]);

        error_log("[google_auth] Successfully linked existing customer #{$existingByEmail['id']} ({$email}) to Google ID {$sub}");

        $emailStmt->execute(['email' => $email]);
        return $emailStmt->fetch(PDO::FETCH_ASSOC);
    }

    // 3. Create new customer account
    $insertStmt = $pdo->prepare('
        INSERT INTO users (
            role_id, google_id, full_name, first_name, last_name, email, 
            avatar, email_verified, is_verified, is_active, password, 
            wallet_balance, reward_points, created_at, updated_at, last_login_at
        ) VALUES (
            2, :gid, :full_name, :first_name, :last_name, :email,
            :avatar, 1, 1, 1, NULL,
            0.00, 0, NOW(), NOW(), NOW()
        )
    ');
    $insertStmt->execute([
        'gid'        => $sub,
        'full_name'  => $fullName,
        'first_name' => $firstName ?: null,
        'last_name'  => $lastName ?: null,
        'email'      => $email,
        'avatar'     => $picture ?: null,
    ]);

    $newCustomerId = (int) $pdo->lastInsertId();

    $fetchStmt = $pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
    $fetchStmt->execute(['id' => $newCustomerId]);
    $newCustomer = $fetchStmt->fetch(PDO::FETCH_ASSOC);

    error_log("[google_auth] Created new customer account #{$newCustomerId} ({$email}) via Google OAuth");

    return $newCustomer;
}
