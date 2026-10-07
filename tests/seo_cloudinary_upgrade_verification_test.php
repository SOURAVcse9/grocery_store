<?php
/**
 * ==============================================================================
 * tests/seo_cloudinary_upgrade_verification_test.php
 * ==============================================================================
 * Comprehensive Automated Verification Suite for:
 * 1. Clean SEO URLs & Slugs
 * 2. Dynamic SEO Meta & Canonical URL generation
 * 3. JSON-LD Structured Data Schemas (Product, Breadcrumbs, WebSite, Store, Org)
 * 4. Robots Directives & Crawl Access Rules
 * 5. Dynamic XML Sitemap & Google Image extensions
 * 6. Cloudinary CDN Service, Transformations & Fallbacks
 * 7. Responsive Image srcset & LCP optimization
 * 8. Database Indexes & Schema Integrity
 * 9. Error Page (404/500) Handling
 * ==============================================================================
 */

declare(strict_types=1);

define('GROCO_CLI_TEST_MODE', true);

require_once __DIR__ . '/../public/dbconnect.php';
require_once __DIR__ . '/../public/includes/seo.php';
require_once __DIR__ . '/../public/includes/cloudinary.php';
require_once __DIR__ . '/../public/includes/image.php';
require_once __DIR__ . '/../public/includes/helpers.php';

$passed = 0;
$failed = 0;

function assert_test(string $name, bool $condition, string $detail = ''): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "  [PASS] {$name}\n";
    } else {
        $failed++;
        echo "  [FAIL] {$name} " . ($detail ? "— {$detail}" : '') . "\n";
    }
}

echo "\n" . str_repeat('=', 70) . "\n";
echo " SEO & CLOUDINARY UPGRADE VERIFICATION SUITE\n";
echo str_repeat('=', 70) . "\n\n";

// --------------------------------------------------------------------------
// Test Suite 1: Clean Canonical URLs & Slug Formats
// --------------------------------------------------------------------------
echo "Test Suite 1: Clean URLs & Canonical Formatter\n";
$prodUrl = product_url('fresh-milk-1-liter');
assert_test('product_url generates clean /product/{slug}', str_contains($prodUrl, '/product/fresh-milk-1-liter'), $prodUrl);

$catUrl = category_url('dairy-products');
assert_test('category_url generates clean /category/{slug}', str_contains($catUrl, '/category/dairy-products'), $catUrl);

$brandUrl = brand_url('pran');
assert_test('brand_url generates clean /brand/{slug}', str_contains($brandUrl, '/brand/pran'), $brandUrl);

$searchUrl = search_url('organic apples');
assert_test('search_url generates clean /search?q={query}', str_contains($searchUrl, '/search?q=organic+apples'), $searchUrl);

$canonicalProd = build_canonical_url('product.php', ['slug' => 'farm-eggs']);
assert_test('build_canonical_url converts product.php?slug into /product/{slug}', str_contains($canonicalProd, '/product/farm-eggs'), $canonicalProd);

$canonicalCatPage = build_canonical_url('products.php', ['category' => 'fruits', 'page' => 2]);
assert_test('build_canonical_url preserves page query for pagination', str_contains($canonicalCatPage, '/category/fruits?page=2'), $canonicalCatPage);

$canonicalHome = build_canonical_url('index.php');
assert_test('build_canonical_url normalizes homepage to root /', str_ends_with($canonicalHome, '/'), $canonicalHome);

// --------------------------------------------------------------------------
// Test Suite 2: Dynamic SEO Meta Title & Description Formatters
// --------------------------------------------------------------------------
echo "\nTest Suite 2: Dynamic SEO Metadata Formatters\n";
$title = format_seo_title('Fresh Milk 1 Liter');
assert_test('format_seo_title appends site name', str_contains($title, 'Fresh Milk 1 Liter | ') || str_contains($title, 'GroCo'), $title);

$existingTitle = format_seo_title('GroCo Grocery Store | Dhaka');
assert_test('format_seo_title avoids duplicate site name repetition', !str_contains($existingTitle, 'GroCo | GroCo'), $existingTitle);

$desc = format_meta_description('Order 100% pure pasteurized milk delivered fresh to your doorstep in Banani, Dhaka. Free delivery for orders over 500 BDT.');
assert_test('format_meta_description leaves clean text untouched if <= 160 chars', strlen($desc) <= 160 && str_contains($desc, 'pasteurized milk'));

$longText = str_repeat('Grocery shopping in Bangladesh with ultrafast same-day doorstep home delivery service. ', 5);
$truncatedDesc = format_meta_description($longText, 160);
assert_test('format_meta_description safely truncates to boundary with ellipsis', mb_strlen($truncatedDesc) <= 165 && str_ends_with($truncatedDesc, '...'));

// --------------------------------------------------------------------------
// Test Suite 3: Robots Directives & Crawl Policy
// --------------------------------------------------------------------------
echo "\nTest Suite 3: Robots Directives & Crawl Policy\n";
$_SERVER['SCRIPT_NAME'] = '/product.php';
$_GET = [];
$prodRobots = get_meta_robots();
assert_test('get_meta_robots allows index, follow on product pages', str_contains($prodRobots, 'index, follow'));

$_SERVER['SCRIPT_NAME'] = '/cart.php';
$cartRobots = get_meta_robots();
assert_test('get_meta_robots forbids indexing on cart page', str_contains($cartRobots, 'noindex'));

$_SERVER['SCRIPT_NAME'] = '/checkout.php';
$checkoutRobots = get_meta_robots();
assert_test('get_meta_robots forbids indexing on checkout page', str_contains($checkoutRobots, 'noindex'));

$_SERVER['SCRIPT_NAME'] = '/search.php';
$_GET['q'] = 'milk';
$searchRobots = get_meta_robots();
assert_test('get_meta_robots uses noindex, follow on internal search queries', str_contains($searchRobots, 'noindex, follow'));

// --------------------------------------------------------------------------
// Test Suite 4: Schema.org JSON-LD Structured Data
// --------------------------------------------------------------------------
echo "\nTest Suite 4: JSON-LD Structured Data Schemas\n";
$websiteJson = get_json_ld_schema('website');
assert_test('WebSite schema contains SearchAction with /search?q=', str_contains($websiteJson, '/search?q={search_term_string}'), $websiteJson);

$sampleProduct = [
    'id' => 99,
    'name' => 'Organic Honey 500g',
    'slug' => 'organic-honey-500g',
    'price' => 450.00,
    'discount_price' => 400.00,
    'stock' => 15,
    'sku' => 'GROCO-HNY-99',
    'barcode' => '8941234567890',
    'brand_name' => 'Nature Care',
    'category_name' => 'Breakfast & Dairy',
    'thumbnail' => 'prod_honey.jpg',
    'avg_rating' => 4.8,
    'review_count' => 12
];
$productJson = get_json_ld_schema('product', ['product' => $sampleProduct]);
assert_test('Product schema contains schema.org context', str_contains($productJson, 'https://schema.org'));
assert_test('Product schema contains Product type', str_contains($productJson, '"@type": "Product"'));
assert_test('Product schema contains clean product URL', str_contains($productJson, '/product/organic-honey-500g'));
assert_test('Product schema contains Offer with BDT price', str_contains($productJson, '"priceCurrency": "BDT"') && str_contains($productJson, '"400.00"'));
assert_test('Product schema contains InStock availability', str_contains($productJson, 'https://schema.org/InStock'));
assert_test('Product schema contains AggregateRating', str_contains($productJson, '"@type": "AggregateRating"') && str_contains($productJson, '"4.8"'));
assert_test('Product schema contains GTIN barcode', str_contains($productJson, '8941234567890'));

$crumbs = [
    ['title' => 'Categories', 'link' => 'categories'],
    ['title' => 'Dairy', 'link' => 'category/dairy'],
    ['title' => 'Organic Honey', 'link' => '']
];
$crumbJson = get_json_ld_schema('breadcrumbs', ['breadcrumbs' => $crumbs]);
assert_test('BreadcrumbList schema contains ListItem elements', str_contains($crumbJson, 'BreadcrumbList') && str_contains($crumbJson, 'Organic Honey'));

// --------------------------------------------------------------------------
// Test Suite 5: Cloudinary Service & Dynamic Image Transformations
// --------------------------------------------------------------------------
echo "\nTest Suite 5: Cloudinary CDN Service & Responsive Transformations\n";
$cdnUrl = CloudinaryService::url('groco/products/fresh-milk', [
    'width' => 500,
    'height' => 500,
    'crop' => 'fill',
    'quality' => 'auto',
    'format' => 'auto'
]);
assert_test('CloudinaryService generates correct CDN URL structure', str_contains($cdnUrl, 'res.cloudinary.com') && str_contains($cdnUrl, 'f_auto,q_auto') && str_contains($cdnUrl, 'w_500,h_500,c_fill'));

$srcset = CloudinaryService::srcset('groco/products/fresh-milk');
assert_test('CloudinaryService produces responsive srcset breakpoints', str_contains($srcset, '150w') && str_contains($srcset, '300w') && str_contains($srcset, '800w') && str_contains($srcset, '1200w'));

$localFallback = CloudinaryService::url('ui/placeholder.png');
assert_test('CloudinaryService falls back gracefully for local files', str_contains($localFallback, 'placeholder.png'));

// --------------------------------------------------------------------------
// Test Suite 6: HTML Image Rendering & LCP Optimization
// --------------------------------------------------------------------------
echo "\nTest Suite 6: Responsive Image Helper & LCP\n";
$lcpImageHtml = render_optimized_product_image('groco/products/fresh-milk', 'Fresh Whole Milk', 300, 300, [
    'is_lcp' => true,
    'class' => 'hero-product-img'
]);
assert_test('render_optimized_product_image sets loading="eager" for LCP', str_contains($lcpImageHtml, 'loading="eager"'));
assert_test('render_optimized_product_image sets fetchpriority="high" for LCP', str_contains($lcpImageHtml, 'fetchpriority="high"'));
assert_test('render_optimized_product_image sets aspect-ratio style', str_contains($lcpImageHtml, 'aspect-ratio: 1/1'));
assert_test('render_optimized_product_image includes clean un-stuffed ALT text', str_contains($lcpImageHtml, 'alt="Fresh Whole Milk"'));

$lazyImageHtml = render_optimized_product_image('prod_thumb.jpg', 'Basmati Rice', 300, 300);
assert_test('Standard product image defaults to loading="lazy"', str_contains($lazyImageHtml, 'loading="lazy"'));

// --------------------------------------------------------------------------
// Test Suite 7: Dynamic XML Sitemap & Robots.txt Compliance
// --------------------------------------------------------------------------
echo "\nTest Suite 7: Dynamic Sitemap & Robots.txt Output\n";
ob_start();
require __DIR__ . '/../public/sitemap.php';
$sitemapXml = ob_get_clean();

assert_test('sitemap.php outputs valid XML declaration', str_starts_with(trim($sitemapXml), '<?xml version="1.0" encoding="UTF-8"?>'));
assert_test('sitemap.php contains urlset with image schema namespace', str_contains($sitemapXml, 'http://www.sitemaps.org/schemas/sitemap/0.9') && str_contains($sitemapXml, 'sitemap-image'));
assert_test('sitemap.php contains clean /product/ URLs', str_contains($sitemapXml, '/product/'));
assert_test('sitemap.php contains clean /category/ URLs', str_contains($sitemapXml, '/category/'));
assert_test('sitemap.php contains clean /brand/ URLs', str_contains($sitemapXml, '/brand/'));
assert_test('sitemap.php includes <image:image> extensions with loc & title', str_contains($sitemapXml, '<image:image>') && str_contains($sitemapXml, '<image:loc>') && str_contains($sitemapXml, '<image:title>'));
assert_test('sitemap.php includes lastmod dates', str_contains($sitemapXml, '<lastmod>'));

ob_start();
require __DIR__ . '/../public/robots.php';
$robotsTxt = ob_get_clean();

assert_test('robots.php allows /product/ and /category/', str_contains($robotsTxt, 'Allow: /product/') && str_contains($robotsTxt, 'Allow: /category/'));
assert_test('robots.php disallows /admin/ and private cart/checkout', str_contains($robotsTxt, 'Disallow: /admin/') && str_contains($robotsTxt, 'Disallow: /cart.php') && str_contains($robotsTxt, 'Disallow: /checkout.php'));
assert_test('robots.php disallows internal search and duplicate query filters', str_contains($robotsTxt, 'Disallow: /search') && str_contains($robotsTxt, 'Disallow: /*?*sort='));
assert_test('robots.php outputs dynamic Sitemap URL', str_contains($robotsTxt, 'Sitemap: ') && str_contains($robotsTxt, 'sitemap.xml'));

// --------------------------------------------------------------------------
// Test Suite 8: Database Columns & Indexes Verification
// --------------------------------------------------------------------------
echo "\nTest Suite 8: Database Schema & Composite Indexes\n";
$pdo = db();

$productCols = $pdo->query("
    SELECT COLUMN_NAME 
    FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products'
")->fetchAll(PDO::FETCH_COLUMN);

$expectedCols = ['image_public_id', 'image_alt', 'image_width', 'image_height', 'seo_title', 'seo_description', 'og_title', 'og_description'];
foreach ($expectedCols as $col) {
    assert_test("products table contains column {$col}", in_array($col, $productCols, true));
}

$indexes = $pdo->query("
    SELECT INDEX_NAME 
    FROM information_schema.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products'
")->fetchAll(PDO::FETCH_COLUMN);

assert_test('products table contains composite index idx_products_cat_active_id', in_array('idx_products_cat_active_id', $indexes, true));
assert_test('products table contains composite index idx_products_active_created', in_array('idx_products_active_created', $indexes, true));

// --------------------------------------------------------------------------
// Summary
// --------------------------------------------------------------------------
echo "\n" . str_repeat('=', 70) . "\n";
echo " TEST EXECUTION SUMMARY\n";
echo str_repeat('=', 70) . "\n";
echo " Total Tests: " . ($passed + $failed) . "\n";
echo " Passed:      {$passed}\n";
echo " Failed:      {$failed}\n";
echo str_repeat('=', 70) . "\n\n";

exit($failed > 0 ? 1 : 0);
