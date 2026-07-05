<?php
/**
 * ==========================================================================
 * public/sitemap.php — Dynamic XML Sitemap Generator
 * ==========================================================================
 * Queries active catalog products and categories, compiling them into a
 * valid XML sitemap for SEO crawlers.
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/dbconnect.php';

// Set correct XML header
header('Content-Type: application/xml; charset=utf-8');

$pdo = db();
$pages = [];

// 1. Core Static pages
$staticPages = [
    '', // Home
    'about.php',
    'contact.php',
    'offers.php',
    'wishlist.php',
    'compare.php'
];

foreach ($staticPages as $sp) {
    $pages[] = [
        'loc'        => site_url($sp),
        'lastmod'    => date('Y-m-d'),
        'changefreq' => 'daily',
        'priority'   => empty($sp) ? '1.0' : '0.6'
    ];
}

try {
    // 2. Fetch all active products
    $prodStmt = $pdo->query('
        SELECT slug, updated_at FROM products 
        WHERE is_active = 1 
        ORDER BY id DESC
    ');
    while ($p = $prodStmt->fetch()) {
        $lastmod = !empty($p['updated_at']) ? date('Y-m-d', strtotime($p['updated_at'])) : date('Y-m-d');
        $pages[] = [
            'loc'        => site_url('product.php?slug=' . $p['slug']),
            'lastmod'    => $lastmod,
            'changefreq' => 'weekly',
            'priority'   => '0.8'
        ];
    }

    // 3. Fetch all active categories
    $catStmt = $pdo->query('
        SELECT slug FROM categories 
        WHERE is_active = 1 
        ORDER BY id DESC
    ');
    while ($c = $catStmt->fetch()) {
        $pages[] = [
            'loc'        => site_url('products.php?category=' . $c['slug']),
            'lastmod'    => date('Y-m-d'),
            'changefreq' => 'weekly',
            'priority'   => '0.7'
        ];
    }

} catch (PDOException $e) {
    error_log('[sitemap.php] Generation error: ' . $e->getMessage());
}

// Render XML
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <?php foreach ($pages as $p): ?>
        <url>
            <loc><?= e($p['loc']) ?></loc>
            <lastmod><?= e($p['lastmod']) ?></lastmod>
            <changefreq><?= e($p['changefreq']) ?></changefreq>
            <priority><?= e($p['priority']) ?></priority>
        </url>
    <?php endforeach; ?>
</urlset>
