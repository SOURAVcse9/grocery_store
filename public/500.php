<?php
/**
 * ==========================================================================
 * public/500.php — 500 Internal Server Error Page
 * ==========================================================================
 */

declare(strict_types=1);

http_response_code(500);

require_once __DIR__ . '/dbconnect.php';

$pageTitle = '500 — Server Error | ' . site_name();
$pageDescription = 'A temporary server error occurred. Please try again shortly.';
$pageRobots = 'noindex, nofollow';

$extraStylesheets = ['css/home.css'];

require_once __DIR__ . '/header.php';
?>

<div class="container" style="padding: 60px 20px; max-width: 600px; margin: 0 auto; text-align: center;">
    <div style="font-size: 72px; font-weight: 900; color: #fa5252; margin-bottom: 16px;">
        <i class="fas fa-triangle-exclamation"></i>
    </div>
    <h1 style="font-size: 26px; font-weight: 800; margin-bottom: 12px;">500 — Internal Server Error</h1>
    <p style="font-size: 15px; color: var(--color-text-muted, #6c757d); line-height: 1.6; margin-bottom: 28px;">
        We encountered an unexpected error processing your request. Our technical team has been notified.
    </p>
    <a href="<?= e(url_for('')) ?>" class="btn btn-primary" style="padding: 12px 28px; border-radius: 50px; font-weight: 700;">
        <i class="fas fa-house"></i> Return to Homepage
    </a>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
