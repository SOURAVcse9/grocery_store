<?php
/**
 * ==========================================================================
 * public/404.php — SEO-Friendly 404 Not Found Error Page
 * ==========================================================================
 * Returns strict HTTP 404 status code, noindex directive, helpful navigation,
 * category links, and store search.
 * ==========================================================================
 */

declare(strict_types=1);

http_response_code(404);

require_once __DIR__ . '/dbconnect.php';

$pageTitle = '404 — Page Not Found | ' . site_name();
$pageDescription = 'The page or product you are looking for cannot be found. Browse our popular grocery categories or return to the homepage.';
$pageRobots = 'noindex, nofollow';
$pageCanonical = build_canonical_url('404.php');

$extraStylesheets = ['css/home.css'];

// Fetch top active categories for quick recovery navigation
try {
    $categories404 = db()->query("
        SELECT id, name, slug 
        FROM categories 
        WHERE is_active = 1 AND parent_id IS NULL 
        ORDER BY name ASC 
        LIMIT 8
    ")->fetchAll();
} catch (PDOException $e) {
    $categories404 = [];
}

require_once __DIR__ . '/header.php';
?>

<div class="container" style="padding: 60px 20px; max-width: 800px; margin: 0 auto; text-align: center;">
    <div style="font-size: 84px; font-weight: 900; color: var(--color-primary, #0b7285); line-height: 1; margin-bottom: 16px;">
        404
    </div>
    <h1 style="font-size: 28px; font-weight: 800; margin-bottom: 16px; color: var(--color-text, #212529);">
        Oops! Page or Product Not Found
    </h1>
    <p style="font-size: 16px; color: var(--color-text-muted, #6c757d); margin-bottom: 32px; line-height: 1.6;">
        The item or page you requested may have been moved, renamed, or is temporarily out of stock. Try searching our store or explore popular categories below.
    </p>

    <!-- Store Search Box -->
    <form action="<?= e(search_url()) ?>" method="GET" style="max-width: 480px; margin: 0 auto 40px; display: flex; gap: 8px;">
        <input type="text" name="q" placeholder="Search fresh groceries, fruits, milk..." style="flex: 1; padding: 12px 18px; border: 2px solid var(--color-border, #dee2e6); border-radius: 50px; font-size: 15px; outline: none;" required>
        <button type="submit" class="btn btn-primary" style="padding: 12px 24px; border-radius: 50px; font-weight: 700;">
            <i class="fas fa-search"></i> Search
        </button>
    </form>

    <!-- Quick Navigation Links -->
    <div style="margin-bottom: 40px; display: flex; justify-content: center; gap: 16px; flex-wrap: wrap;">
        <a href="<?= e(url_for('')) ?>" class="btn btn-primary" style="padding: 12px 24px; border-radius: 50px; font-weight: 700;">
            <i class="fas fa-house"></i> Return to Homepage
        </a>
        <a href="<?= e(url_for('categories.php')) ?>" class="btn btn-outline" style="padding: 12px 24px; border-radius: 50px; font-weight: 700; border: 2px solid var(--color-primary, #0b7285); color: var(--color-primary, #0b7285);">
            <i class="fas fa-th-large"></i> All Categories
        </a>
    </div>

    <!-- Popular Categories Grid -->
    <?php if (!empty($categories404)): ?>
        <div style="border-top: 1px solid var(--color-border, #dee2e6); padding-top: 32px; text-align: left;">
            <h2 style="font-size: 18px; font-weight: 700; margin-bottom: 16px; text-align: center;">Popular Grocery Categories</h2>
            <div style="display: flex; flex-wrap: wrap; gap: 10px; justify-content: center;">
                <?php foreach ($categories404 as $cat): ?>
                    <a href="<?= e(category_url($cat['slug'])) ?>" style="padding: 8px 16px; background: var(--color-bg, #f8f9fa); border: 1px solid var(--color-border, #dee2e6); border-radius: 20px; text-decoration: none; color: var(--color-text, #212529); font-size: 14px; font-weight: 500; transition: all 150ms ease;">
                        <?= e($cat['name']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
