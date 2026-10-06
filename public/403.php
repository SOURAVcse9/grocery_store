<?php
/**
 * ==========================================================================
 * public/403.php — SEO-Safe 403 Forbidden Error Page
 * ==========================================================================
 * Returns strict HTTP 403 status code, noindex directive, clean styling,
 * and friendly recovery navigation.
 * ==========================================================================
 */

declare(strict_types=1);

http_response_code(403);

require_once __DIR__ . '/dbconnect.php';

$pageTitle = '403 — Access Forbidden | ' . site_name();
$pageDescription = 'Access to the requested resource is forbidden or requires appropriate permissions.';
$pageRobots = 'noindex, nofollow';
$pageCanonical = build_canonical_url('403.php');

$extraStylesheets = ['css/home.css'];

// Fetch top active categories for navigation recovery
try {
    $categories403 = db()->query("
        SELECT id, name, slug 
        FROM categories 
        WHERE is_active = 1 AND parent_id IS NULL 
        ORDER BY name ASC 
        LIMIT 8
    ")->fetchAll();
} catch (PDOException $e) {
    $categories403 = [];
}

require_once __DIR__ . '/header.php';
?>

<div class="container" style="padding: 60px 20px; max-width: 800px; margin: 0 auto; text-align: center;">
    <div style="font-size: 84px; font-weight: 900; color: #e03131; line-height: 1; margin-bottom: 16px;">
        403
    </div>
    <h1 style="font-size: 28px; font-weight: 800; margin-bottom: 16px; color: var(--color-text, #212529);">
        Access Forbidden
    </h1>
    <p style="font-size: 16px; color: var(--color-text-muted, #6c757d); margin-bottom: 32px; line-height: 1.6;">
        You do not have permission to view this resource. If you believe this is an error or need access, please sign in with an authorized account or contact support.
    </p>

    <!-- Quick Navigation Links -->
    <div style="margin-bottom: 40px; display: flex; justify-content: center; gap: 16px; flex-wrap: wrap;">
        <a href="<?= e(url_for('')) ?>" class="btn btn-primary" style="padding: 12px 24px; border-radius: 50px; font-weight: 700;">
            <i class="fas fa-house"></i> Return to Homepage
        </a>
        <a href="<?= e(url_for('login.php')) ?>" class="btn btn-outline" style="padding: 12px 24px; border-radius: 50px; font-weight: 700; border: 2px solid var(--color-primary, #0b7285); color: var(--color-primary, #0b7285);">
            <i class="fas fa-sign-in-alt"></i> Sign In
        </a>
        <a href="<?= e(url_for('categories.php')) ?>" class="btn btn-outline" style="padding: 12px 24px; border-radius: 50px; font-weight: 700; border: 2px solid var(--color-border, #dee2e6); color: var(--color-text, #212529);">
            <i class="fas fa-th-large"></i> Browse Categories
        </a>
    </div>

    <!-- Popular Categories Grid -->
    <?php if (!empty($categories403)): ?>
        <div style="border-top: 1px solid var(--color-border, #dee2e6); padding-top: 32px; text-align: left;">
            <h2 style="font-size: 18px; font-weight: 700; margin-bottom: 16px; text-align: center;">Explore Grocery Categories</h2>
            <div style="display: flex; flex-wrap: wrap; gap: 10px; justify-content: center;">
                <?php foreach ($categories403 as $cat): ?>
                    <a href="<?= e(category_url($cat['slug'])) ?>" style="padding: 8px 16px; background: var(--color-bg, #f8f9fa); border: 1px solid var(--color-border, #dee2e6); border-radius: 20px; text-decoration: none; color: var(--color-text, #212529); font-size: 14px; font-weight: 500; transition: all 150ms ease;">
                        <?= e($cat['name']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
