<?php
/**
 * ==========================================================================
 * public/sitemap.php — Production Dynamic XML Sitemap Generator
 * ==========================================================================
 * Dynamically queries active catalog products, categories, brands, and public
 * content pages, compiling a valid search-engine compliant XML sitemap with
 * image extensions for Google Image Search.
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/dbconnect.php';
require_once __DIR__ . '/includes/seo.php';

// Set correct XML content-type and encoding header
if (!headers_sent()) {
    header('Content-Type: application/xml; charset=utf-8');
    header('X-Robots-Tag: noindex, follow');
}

$pdo = db();
$pages = [];
$baseUrl = get_canonical_base_url();

// 1. Core Public Static Pages
$staticPages = [
    ''               => ['freq' => 'daily',   'prio' => '1.0'],
    'products.php'   => ['freq' => 'daily',   'prio' => '0.9'],
    'categories.php' => ['freq' => 'daily',   'prio' => '0.8'],
    'offers.php'     => ['freq' => 'daily',   'prio' => '0.8'],
    'about.php'      => ['freq' => 'monthly', 'prio' => '0.6'],
    'contact.php'    => ['freq' => 'monthly', 'prio' => '0.6'],
    'faq.php'        => ['freq' => 'monthly', 'prio' => '0.5'],
    'privacy.php'    => ['freq' => 'yearly',  'prio' => '0.3'],
    'terms.php'      => ['freq' => 'yearly',  'prio' => '0.3'],
];

foreach ($staticPages as $page => $meta) {
    $loc = empty($page) ? $baseUrl . '/' : $baseUrl . '/' . $page;
    $pages[] = [
        'loc'        => $loc,
        'lastmod'    => date('Y-m-d'),
        'changefreq' => $meta['freq'],
        'priority'   => $meta['prio'],
        'images'     => []
    ];
}

try {
    // 2. Fetch all active categories
    $catStmt = $pdo->query('
        SELECT slug, name, image, updated_at, created_at 
        FROM categories 
        WHERE is_active = 1 
        ORDER BY name ASC
    ');
    while ($c = $catStmt->fetch(PDO::FETCH_ASSOC)) {
        $timestamp = !empty($c['updated_at']) ? $c['updated_at'] : ($c['created_at'] ?? 'now');
        $lastmod = date('Y-m-d', strtotime((string)$timestamp));
        $catImages = [];
        if (!empty($c['image'])) {
            $catImages[] = [
                'loc'   => image_url($c['image'], 'categories'),
                'title' => $c['name'] . ' Groceries'
            ];
        }
        $pages[] = [
            'loc'        => $baseUrl . '/products.php?category=' . urlencode($c['slug']),
            'lastmod'    => $lastmod,
            'changefreq' => 'daily',
            'priority'   => '0.8',
            'images'     => $catImages
        ];
    }

    // 3. Fetch all active brands
    $brandStmt = $pdo->query('
        SELECT slug, name, logo, updated_at, created_at 
        FROM brands 
        WHERE is_active = 1 
        ORDER BY name ASC
    ');
    while ($b = $brandStmt->fetch(PDO::FETCH_ASSOC)) {
        $timestamp = !empty($b['updated_at']) ? $b['updated_at'] : ($b['created_at'] ?? 'now');
        $lastmod = date('Y-m-d', strtotime((string)$timestamp));
        $brandImages = [];
        if (!empty($b['logo'])) {
            $brandImages[] = [
                'loc'   => image_url($b['logo'], 'brands'),
                'title' => $b['name'] . ' Brand Products'
            ];
        }
        $pages[] = [
            'loc'        => $baseUrl . '/products.php?brand=' . urlencode($b['slug']),
            'lastmod'    => $lastmod,
            'changefreq' => 'weekly',
            'priority'   => '0.7',
            'images'     => $brandImages
        ];
    }

    // 4. Fetch all active, non-deleted products
    $prodStmt = $pdo->query('
        SELECT p.id, p.slug, p.name, p.thumbnail, p.updated_at, p.created_at, p.stock
        FROM products p 
        WHERE p.is_active = 1 AND p.deleted_at IS NULL
        ORDER BY p.id DESC
    ');
    while ($p = $prodStmt->fetch(PDO::FETCH_ASSOC)) {
        $timestamp = !empty($p['updated_at']) ? $p['updated_at'] : ($p['created_at'] ?? 'now');
        $lastmod = date('Y-m-d', strtotime((string)$timestamp));
        $prodImages = [];
        if (!empty($p['thumbnail'])) {
            $prodImages[] = [
                'loc'   => image_url($p['thumbnail'], 'products'),
                'title' => $p['name']
            ];
        }
        $pages[] = [
            'loc'        => $baseUrl . '/product.php?slug=' . urlencode($p['slug']),
            'lastmod'    => $lastmod,
            'changefreq' => 'daily',
            'priority'   => '0.9',
            'images'     => $prodImages
        ];
    }

} catch (PDOException $e) {
    error_log('[sitemap.php] Generation error: ' . $e->getMessage());
}

// Build XML output
$xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
$xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";

foreach ($pages as $p) {
    $xml .= "    <url>\n";
    $xml .= '        <loc>' . htmlspecialchars($p['loc'], ENT_XML1, 'UTF-8') . "</loc>\n";
    $xml .= '        <lastmod>' . htmlspecialchars($p['lastmod'], ENT_XML1, 'UTF-8') . "</lastmod>\n";
    $xml .= '        <changefreq>' . htmlspecialchars($p['changefreq'], ENT_XML1, 'UTF-8') . "</changefreq>\n";
    $xml .= '        <priority>' . htmlspecialchars($p['priority'], ENT_XML1, 'UTF-8') . "</priority>\n";
    
    if (!empty($p['images'])) {
        foreach ($p['images'] as $img) {
            $xml .= "        <image:image>\n";
            $xml .= '            <image:loc>' . htmlspecialchars($img['loc'], ENT_XML1, 'UTF-8') . "</image:loc>\n";
            if (!empty($img['title'])) {
                $xml .= '            <image:title>' . htmlspecialchars($img['title'], ENT_XML1, 'UTF-8') . "</image:title>\n";
            }
            $xml .= "        </image:image>\n";
        }
    }
    $xml .= "    </url>\n";
}
$xml .= "</urlset>\n";

echo $xml;
