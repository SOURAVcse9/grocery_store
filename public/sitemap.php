<?php
/**
 * ==========================================================================
 * public/sitemap.php — Production XML Sitemap Generator
 * ==========================================================================
 * Dynamically queries active catalog products, categories, and public pages,
 * compiling a valid, search-engine compliant XML sitemap.
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/dbconnect.php';

// Set correct XML header
header('Content-Type: application/xml; charset=utf-8');

$pdo = db();
$pages = [];

$appUrl = getenv('APP_URL') ?: ($_ENV['APP_URL'] ?? BASE_URL);
$appUrl = rtrim($appUrl, '/');

// 1. Core Public Static Pages
$staticPages = [
    ''               => ['freq' => 'daily',   'prio' => '1.0'],
    'products.php'   => ['freq' => 'daily',   'prio' => '0.9'],
    'categories.php' => ['freq' => 'weekly',  'prio' => '0.8'],
    'about.php'      => ['freq' => 'monthly', 'prio' => '0.5'],
    'contact.php'    => ['freq' => 'monthly', 'prio' => '0.5'],
    'faq.php'        => ['freq' => 'monthly', 'prio' => '0.4'],
    'privacy.php'    => ['freq' => 'yearly',  'prio' => '0.3'],
    'terms.php'      => ['freq' => 'yearly',  'prio' => '0.3'],
];

foreach ($staticPages as $page => $meta) {
    $loc = empty($page) ? $appUrl . '/' : $appUrl . '/public/' . $page;
    $pages[] = [
        'loc'        => $loc,
        'lastmod'    => date('Y-m-d'),
        'changefreq' => $meta['freq'],
        'priority'   => $meta['prio']
    ];
}

try {
    // 2. Fetch all active categories
    $catStmt = $pdo->query('
        SELECT slug, updated_at FROM categories 
        WHERE is_active = 1 
        ORDER BY id DESC
    ');
    while ($c = $catStmt->fetch(PDO::FETCH_ASSOC)) {
        $lastmod = !empty($c['updated_at']) ? date('Y-m-d', strtotime($c['updated_at'])) : date('Y-m-d');
        $pages[] = [
            'loc'        => $appUrl . '/public/products.php?category=' . urlencode($c['slug']),
            'lastmod'    => $lastmod,
            'changefreq' => 'weekly',
            'priority'   => '0.8'
        ];
    }

    // 3. Fetch all active products
    $prodStmt = $pdo->query('
        SELECT slug, updated_at FROM products 
        WHERE is_active = 1 AND deleted_at IS NULL
        ORDER BY id DESC
    ');
    while ($p = $prodStmt->fetch(PDO::FETCH_ASSOC)) {
        $lastmod = !empty($p['updated_at']) ? date('Y-m-d', strtotime($p['updated_at'])) : date('Y-m-d');
        $pages[] = [
            'loc'        => $appUrl . '/public/product.php?slug=' . urlencode($p['slug']),
            'lastmod'    => $lastmod,
            'changefreq' => 'weekly',
            'priority'   => '0.9'
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
            <loc><?= htmlspecialchars($p['loc'], ENT_XML1, 'UTF-8') ?></loc>
            <lastmod><?= htmlspecialchars($p['lastmod'], ENT_XML1, 'UTF-8') ?></lastmod>
            <changefreq><?= htmlspecialchars($p['changefreq'], ENT_XML1, 'UTF-8') ?></changefreq>
            <priority><?= htmlspecialchars($p['priority'], ENT_XML1, 'UTF-8') ?></priority>
        </url>
    <?php endforeach; ?>
</urlset>
