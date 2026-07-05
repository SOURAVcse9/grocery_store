<?php
/**
 * ==========================================================================
 * admin/middleware/auth_middleware.php — Admin Auth Middlewares
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_helpers.php';

/**
 * require_admin_auth()
 * Protects admin route from guest users. Redirects to admin/login.php if unauthenticated.
 */
function require_admin_auth(): void
{
    if (defined('BYPASS_AUTH') && BYPASS_AUTH === true) {
        return;
    }
    if (!is_admin_logged_in()) {
        redirect_to_admin_login();
    }
}

/**
 * require_admin_guest()
 * Redirects authenticated administrators away from guest pages like login.php to the dashboard.
 */
function require_admin_guest(): void
{
    if (is_admin_logged_in()) {
        header('Location: ' . BASE_URL . '/../admin/index.php');
        exit;
    }
}

/**
 * require_admin_permission()
 * Enforces RBAC permissions check. Aborts with HTTP 403 on failure.
 */
function require_admin_permission(string $permissionKey): void
{
    // Must be logged in first
    require_admin_auth();

    if (!has_admin_permission($permissionKey)) {
        log_admin_activity('permission_denied', "Attempted to access restricted resource requiring permission: '$permissionKey'");
        
        // Output clean 403 page
        http_response_code(403);
        $pageTitle = '403 Access Denied — ' . site_name();
        
        require_once __DIR__ . '/../../public/header.php';
        ?>
        <div class="container" style="margin-top: 80px; margin-bottom: 80px; text-align: center;">
            <div class="dashboard-card" style="max-width: 500px; margin: 0 auto; padding: var(--space-6);">
                <div style="font-size: 50px; color: var(--color-danger); margin-bottom: var(--space-3);"><i class="fas fa-lock"></i></div>
                <h1 style="font-size: 20px; font-weight: 800; color: var(--color-text); margin-bottom: var(--space-2);">403 — Unauthorized Access</h1>
                <p style="font-size: var(--fs-sm); color: var(--color-text-muted); line-height: 1.5; margin-bottom: var(--space-5);">
                    You do not have the required permission (<strong><?= htmlspecialchars($permissionKey) ?></strong>) to view this resource. If you believe this is an error, please contact your administrator.
                </p>
                <a href="<?= BASE_URL ?>/../admin/index.php" class="btn btn-primary" style="padding: 10px 24px; border-radius: var(--radius-pill); border:none; text-decoration:none; display:inline-block; font-weight:700;">Back to Dashboard</a>
            </div>
        </div>
        <?php
        require_once __DIR__ . '/../../public/footer.php';
        exit;
    }
}

/**
 * require_super_admin()
 * Special role gate check for Super Admin only features.
 */
function require_super_admin(): void
{
    require_admin_auth();
    
    $admin = current_admin();
    if (!$admin || $admin['role_name'] !== 'Super Admin') {
        log_admin_activity('permission_denied', "Attempted to access restricted resource requiring Super Admin role");
        
        http_response_code(403);
        $pageTitle = '403 Access Denied';
        require_once __DIR__ . '/../../public/header.php';
        ?>
        <div class="container" style="margin-top: 80px; margin-bottom: 80px; text-align: center;">
            <div class="dashboard-card" style="max-width: 500px; margin: 0 auto; padding: var(--space-6);">
                <div style="font-size: 50px; color: var(--color-danger); margin-bottom: var(--space-3);"><i class="fas fa-shield-halved"></i></div>
                <h1 style="font-size: 20px; font-weight: 800; color: var(--color-text); margin-bottom: var(--space-2);">Super Admin Access Required</h1>
                <p style="font-size: var(--fs-sm); color: var(--color-text-muted); line-height: 1.5; margin-bottom: var(--space-5);">
                    This screen is restricted to Super Administrators only.
                </p>
                <a href="<?= BASE_URL ?>/../admin/index.php" class="btn btn-primary" style="padding: 10px 24px; border-radius: var(--radius-pill); border:none; text-decoration:none; display:inline-block; font-weight:700;">Back to Dashboard</a>
            </div>
        </div>
        <?php
        require_once __DIR__ . '/../../public/footer.php';
        exit;
    }
}

/**
 * redirect_to_admin_login()
 * Centralized redirect routing helper.
 */
function redirect_to_admin_login(): void
{
    header('Location: ' . BASE_URL . '/../admin/login.php');
    exit;
}
