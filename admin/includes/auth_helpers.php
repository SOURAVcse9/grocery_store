<?php
/**
 * ==========================================================================
 * admin/includes/auth_helpers.php — Administrative Auth Helpers
 * ==========================================================================
 */

declare(strict_types=1);

// Helper check (ensure session is started)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * is_admin_logged_in()
 * Returns true if an administrator session is active and valid.
 */
function is_admin_logged_in(): bool
{
    // Check main session key
    if (!isset($_SESSION['admin_id'])) {
        // Try auto-login via Remember Me cookie
        return attempt_admin_cookie_login();
    }

    // Verify session fingerprint (User-Agent mismatch check)
    if (isset($_SESSION['admin_fingerprint']) && $_SESSION['admin_fingerprint'] !== md5($_SERVER['HTTP_USER_AGENT'] ?? '')) {
        admin_logout();
        return false;
    }

    // Verify session timeout (30 minutes of inactivity)
    $now = time();
    if (isset($_SESSION['admin_last_activity']) && ($now - $_SESSION['admin_last_activity']) > 1800) {
        admin_logout();
        return false;
    }
    
    // Renew activity timestamp
    $_SESSION['admin_last_activity'] = $now;

    return true;
}

/**
 * current_admin_id()
 * Returns the logged-in administrator ID or null.
 */
function current_admin_id(): ?int
{
    return isset($_SESSION['admin_id']) ? (int) $_SESSION['admin_id'] : null;
}

/**
 * current_admin()
 * Fetches the active admin details along with role details from the database.
 */
function current_admin(): ?array
{
    $id = current_admin_id();
    if ($id === null) {
        return null;
    }

    static $cache = null;
    if ($cache !== null && $cache['id'] === $id) {
        return $cache;
    }

    try {
        $pdo = db();
        $stmt = $pdo->prepare("
            SELECT a.*, r.name AS role_name 
            FROM admins a 
            JOIN admin_roles r ON r.id = a.role_id 
            WHERE a.id = :id AND a.is_active = 1 
            LIMIT 1
        ");
        $stmt->execute(['id' => $id]);
        $admin = $stmt->fetch();
        if ($admin) {
            $cache = $admin;
            return $admin;
        }
    } catch (PDOException $e) {
        error_log('[auth_helpers] Fetch admin failed: ' . $e->getMessage());
    }

    return null;
}

/**
 * has_admin_permission()
 * Checks if the current admin possesses a specific RBAC permission.
 */
function has_admin_permission(string $permissionKey): bool
{
    $admin = current_admin();
    if (!$admin) {
        return false;
    }

    // Super Admin inherits all permissions automatically
    if ($admin['role_name'] === 'Super Admin') {
        return true;
    }

    try {
        $pdo = db();
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM admin_role_permissions rp
            JOIN admin_permissions p ON p.id = rp.permission_id
            WHERE rp.role_id = :role_id AND p.permission_key = :key
        ");
        $stmt->execute([
            'role_id' => $admin['role_id'],
            'key'     => $permissionKey
        ]);
        return ((int) $stmt->fetchColumn() > 0);
    } catch (PDOException $e) {
        error_log('[auth_helpers] Permission check failed: ' . $e->getMessage());
    }

    return false;
}

/**
 * log_admin_activity()
 * Logs an administrative action in the admin_activity_logs table.
 */
function log_admin_activity(string $type, string $description): void
{
    $adminId = current_admin_id();
    if ($adminId === null) {
        return;
    }

    try {
        $pdo = db();
        $stmt = $pdo->prepare("
            INSERT INTO admin_activity_logs (admin_id, activity_type, description, ip_address, user_agent)
            VALUES (:uid, :type, :desc, :ip, :ua)
        ");
        $stmt->execute([
            'uid'   => $adminId,
            'type'  => $type,
            'desc'  => $description,
            'ip'    => $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN',
            'ua'    => substr($_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN', 0, 255)
        ]);
    } catch (PDOException $e) {
        error_log('[auth_helpers] Activity log write fail: ' . $e->getMessage());
    }
}

/**
 * log_admin_login()
 * Logs a login attempt (success or failure) in the admin_login_logs table.
 */
function log_admin_login(?int $adminId, string $identity, bool $success, ?string $reason = null): void
{
    try {
        $pdo = db();
        
        // Simple User Agent Parsing
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $os = 'Unknown OS';
        $browser = 'Unknown Browser';
        
        if (preg_match('/win/i', $ua)) $os = 'Windows';
        elseif (preg_match('/mac/i', $ua)) $os = 'macOS';
        elseif (preg_match('/linux/i', $ua)) $os = 'Linux';
        elseif (preg_match('/android/i', $ua)) $os = 'Android';
        elseif (preg_match('/iphone/i', $ua)) $os = 'iOS';

        if (preg_match('/chrome/i', $ua)) $browser = 'Chrome';
        elseif (preg_match('/firefox/i', $ua)) $browser = 'Firefox';
        elseif (preg_match('/safari/i', $ua)) $browser = 'Safari';
        elseif (preg_match('/edge/i', $ua)) $browser = 'Edge';

        $stmt = $pdo->prepare("
            INSERT INTO admin_login_logs (admin_id, login_identity, ip_address, user_agent, browser, os, device, success, failure_reason)
            VALUES (:aid, :identity, :ip, :ua, :browser, :os, 'Desktop/Mobile', :success, :reason)
        ");
        $stmt->execute([
            'aid'      => $adminId,
            'identity' => $identity,
            'ip'       => $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN',
            'ua'       => substr($ua, 0, 255),
            'browser'  => $browser,
            'os'       => $os,
            'success'  => $success ? 1 : 0,
            'reason'   => $reason
        ]);
    } catch (PDOException $e) {
        error_log('[auth_helpers] Login log write fail: ' . $e->getMessage());
    }
}

/**
 * admin_logout()
 * Terminate the administrative session and clear cookies.
 */
function admin_logout(): void
{
    $adminId = current_admin_id();
    if ($adminId !== null) {
        log_admin_activity('logout', 'Administrator logged out successfully');
        
        // Log logout time in login log
        try {
            $pdo = db();
            $up = $pdo->prepare("
                UPDATE admin_login_logs 
                SET logout_time = NOW() 
                WHERE admin_id = :aid AND success = 1 AND logout_time IS NULL 
                ORDER BY login_time DESC LIMIT 1
            ");
            $up->execute(['aid' => $adminId]);
        } catch (PDOException $e) {
            error_log('[auth_helpers] Logout timestamp failed: ' . $e->getMessage());
        }
    }

    // Clear remember me token in DB
    if ($adminId !== null) {
        try {
            $pdo = db();
            $stmt = $pdo->prepare("UPDATE admins SET remember_token = NULL WHERE id = :id");
            $stmt->execute(['id' => $adminId]);
        } catch (PDOException $e) {
            error_log('[auth_helpers] remember_token clear failed: ' . $e->getMessage());
        }
    }

    // Unset session keys
    unset(
        $_SESSION['admin_id'],
        $_SESSION['admin_last_activity'],
        $_SESSION['admin_fingerprint']
    );

    // Delete remember cookie
    if (isset($_COOKIE['admin_remember'])) {
        setcookie('admin_remember', '', [
            'expires' => time() - 3600,
            'path' => '/admin',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    }
}

/**
 * attempt_admin_cookie_login()
 * Verifies remember me cookie and logins the administrator automatically.
 */
function attempt_admin_cookie_login(): bool
{
    if (empty($_COOKIE['admin_remember'])) {
        return false;
    }

    $cookieToken = $_COOKIE['admin_remember'];

    try {
        $pdo = db();
        $stmt = $pdo->prepare("SELECT id, remember_token FROM admins WHERE is_active = 1 AND remember_token IS NOT NULL");
        $stmt->execute();
        $adminsList = $stmt->fetchAll();

        foreach ($adminsList as $admin) {
            // Verify hash match
            if (hash_equals($admin['remember_token'], hash('sha256', $cookieToken))) {
                // Successful auto-login!
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_last_activity'] = time();
                $_SESSION['admin_fingerprint'] = md5($_SERVER['HTTP_USER_AGENT'] ?? '');

                // Rotate remember me token (rotation protection)
                $newToken = bin2hex(random_bytes(32));
                $newHash = hash('sha256', $newToken);
                
                $up = $pdo->prepare("UPDATE admins SET remember_token = :token WHERE id = :id");
                $up->execute(['token' => $newHash, 'id' => $admin['id']]);

                // Write new cookie (valid for 30 days)
                setcookie('admin_remember', $newToken, [
                    'expires' => time() + (30 * 86400),
                    'path' => '/admin',
                    'secure' => true,
                    'httponly' => true,
                    'samesite' => 'Lax'
                ]);

                log_admin_login((int)$admin['id'], 'Remember Cookie', true);
                log_admin_activity('auto_login', 'Logged in automatically via remember cookie');
                
                return true;
            }
        }
    } catch (Exception $e) {
        error_log('[auth_helpers] cookie login failed: ' . $e->getMessage());
    }

    return false;
}
