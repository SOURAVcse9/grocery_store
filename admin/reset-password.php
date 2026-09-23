<?php
/**
 * ==========================================================================
 * admin/reset-password.php — Disabled (Admin Password Reset Policy)
 * ==========================================================================
 * Self-service administrative password reset is strictly disabled by policy.
 * Staff password recovery is managed exclusively via the Super Administrator.
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/../public/dbconnect.php';

http_response_code(403);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>403 Forbidden — Administrative Recovery Disabled</title>
    <link rel="stylesheet" href="<?= asset('css/admin.css') ?>">
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #0f172a; color: #f8fafc; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; padding: 20px;">
    <div style="background: #1e293b; border: 1px solid #334155; border-radius: 12px; padding: 36px; max-width: 480px; text-align: center; box-shadow: 0 20px 40px rgba(0,0,0,0.4);">
        <div style="font-size: 48px; color: #ef4444; margin-bottom: 16px;">🛡️</div>
        <h2 style="margin: 0 0 10px 0; font-size: 20px; font-weight: 800;">Self-Service Password Reset Disabled</h2>
        <p style="color: #94a3b8; font-size: 13px; line-height: 1.6; margin: 0 0 24px 0;">
            For security reasons, self-service email password recovery is not available for administrative accounts. If you have forgotten your password, please contact the Super Administrator.
        </p>
        <a href="login.php" style="display: inline-block; background: #0b7285; color: #ffffff; text-decoration: none; padding: 10px 24px; border-radius: 50px; font-weight: 700; font-size: 13px;">Return to Admin Login</a>
    </div>
</body>
</html>
