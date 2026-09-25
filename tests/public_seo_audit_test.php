<?php
/**
 * ==========================================================================
 * tests/public_seo_audit_test.php — Automated Public Storefront SEO Test Suite
 * ==========================================================================
 * Validates dynamic SEO metadata, canonical URL normalization, Google Merchant
 * Product JSON-LD structured data, Breadcrumbs, Sitemap XML, robots.txt,
 * social OpenGraph metadata, and indexing directives.
 * ==========================================================================
 */

declare(strict_types=1);

define('GROCO_CLI_TEST_MODE', true);
putenv('APP_URL=https://groco.site.je');
$_ENV['APP_URL'] = 'https://groco.site.je';

require_once __DIR__ . '/../public/dbconnect.php';
require_once __DIR__ . '/../public/includes/seo.php';

$testCount = 0;
$passCount = 0;
$failCount = 0;

function assert_test(bool $condition, string $description, string &$details = ''): void
{
    global $testCount, $passCount, $failCount;
    $testCount++;
    if ($condition) {
        $passCount++;
        echo "  [PASS] Test #{$testCount}: {$description}\n";
    } else {
        $failCount++;
        echo "  [FAIL] Test #{$testCount}: {$description}\n";
        if (!empty($details)) {
            echo "         Details: {$details}\n";
        }
    }
}

echo "\n======================================================================\n";
echo " GROCO STOREFRONT — PRODUCTION SEO & INDEXING AUDIT SUITE\n";
echo "======================================================================\n\n";

$pdo = db();

// --------------------------------------------------------------------------
// Group 1: Title, Meta Description & Canonical URL System
// --------------------------------------------------------------------------
echo "--- Group 1: Title, Description & Canonical URL Builders ---\n";

$title1 = format_seo_title('Fresh Organic Tomato');
assert_test(str_contains($title1, 'Fresh Organic Tomato') && str_contains($title1, site_name()), 'format_seo_title appends site name');

$titleHome = format_seo_title('');
assert_test(str_contains($titleHome, site_name()), 'format_seo_title handles empty string with default fallback');

$desc1 = format_meta_description('Order fresh red tomatoes online at great prices.');
assert_test(mb_strlen($desc1) > 10 && !str_contains($desc1, '<'), 'format_meta_description strips HTML tags and cleans text');

$longText = str_repeat('Fresh Organic Grocery Products in Bangladesh ', 20);
$descTruncated = format_meta_description($longText, 120);
assert_test(mb_strlen($descTruncated) <= 125 && str_ends_with($descTruncated, '...'), 'format_meta_description truncates cleanly at word boundary');

$canonHome = build_canonical_url('');
assert_test($canonHome === 'https://groco.site.je/', "build_canonical_url homepage resolves to canonical domain ({$canonHome})");

$canonCat = build_canonical_url('products.php', ['category' => 'fresh-vegetables', 'sort' => 'price_asc', 'page' => '2']);
assert_test($canonCat === 'https://groco.site.je/products.php?category=fresh-vegetables', "build_canonical_url strips unwanted sort/page params from category canonical ({$canonCat})");

$canonProd = build_canonical_url('product.php', ['slug' => 'farm-eggs-12pcs', 'ref' => 'fb_ad', 'utm_source' => 'google']);
assert_test($canonProd === 'https://groco.site.je/product.php?slug=farm-eggs-12pcs', "build_canonical_url strips tracking UTM params from product canonical ({$canonProd})");

// --------------------------------------------------------------------------
// Group 2: Robots Indexing Directives
// --------------------------------------------------------------------------
echo "\n--- Group 2: Robots Indexing Directives ---\n";

$_SERVER['SCRIPT_NAME'] = '/grocery-store/public/index.php';
$_GET = [];
$robotsHome = get_meta_robots();
assert_test(str_starts_with($robotsHome, 'index, follow'), "Homepage robots directive is indexable: '{$robotsHome}'");

$_SERVER['SCRIPT_NAME'] = '/grocery-store/public/products.php';
$_GET = ['category' => 'fruits'];
$robotsCat = get_meta_robots();
assert_test(str_starts_with($robotsCat, 'index, follow'), "Category catalog robots directive is indexable: '{$robotsCat}'");

$_SERVER['SCRIPT_NAME'] = '/grocery-store/public/products.php';
$_GET = ['q' => 'organic sugar'];
$robotsSearch = get_meta_robots();
assert_test($robotsSearch === 'noindex, follow', "Internal search queries receive 'noindex, follow' to prevent index bloat");

$_SERVER['SCRIPT_NAME'] = '/grocery-store/public/cart.php';
$_GET = [];
$robotsCart = get_meta_robots();
assert_test($robotsCart === 'noindex, nofollow', "Cart page receives 'noindex, nofollow'");

$_SERVER['SCRIPT_NAME'] = '/grocery-store/public/checkout.php';
$robotsCheckout = get_meta_robots();
assert_test($robotsCheckout === 'noindex, nofollow', "Checkout page receives 'noindex, nofollow'");

$_SERVER['SCRIPT_NAME'] = '/grocery-store/public/login.php';
$robotsLogin = get_meta_robots();
assert_test($robotsLogin === 'noindex, nofollow', "Customer login page receives 'noindex, nofollow'");

// --------------------------------------------------------------------------
// Group 3: Schema.org Structured Data (Organization, Local Store & WebSite)
// --------------------------------------------------------------------------
echo "\n--- Group 3: Organization, Store & WebSite JSON-LD Schemas ---\n";

$orgJson = get_json_ld_schema('organization');
$orgData = json_decode(strip_tags($orgJson), true);
assert_test(is_array($orgData) && ($orgData['@type'] ?? '') === 'Organization', 'Organization JSON-LD schema is valid JSON with @type Organization');
assert_test(!empty($orgData['name']) && !empty($orgData['contactPoint']), 'Organization schema contains verified site name and customer service contact');

$storeJson = get_json_ld_schema('local_business');
$storeData = json_decode(strip_tags($storeJson), true);
assert_test(is_array($storeData) && ($storeData['@type'] ?? '') === 'GroceryStore', 'GroceryStore / LocalBusiness JSON-LD schema is valid JSON');
assert_test(!empty($storeData['address']) && ($storeData['currenciesAccepted'] ?? '') === 'BDT', 'Store schema includes valid Bangladesh PostalAddress and BDT currency');

$webJson = get_json_ld_schema('website');
$webData = json_decode(strip_tags($webJson), true);
assert_test(is_array($webData) && ($webData['@type'] ?? '') === 'WebSite', 'WebSite JSON-LD schema is valid JSON');
assert_test(isset($webData['potentialAction']['target']['urlTemplate']), 'WebSite schema contains SearchAction URL template');

// --------------------------------------------------------------------------
// Group 4: Product Detail Structured Data (Google Merchant & Search Compliant)
// --------------------------------------------------------------------------
echo "\n--- Group 4: Product Schema.org Structured Data ---\n";

$sampleProd = $pdo->query('
    SELECT p.*, c.name AS category_name, b.name AS brand_name 
    FROM products p 
    LEFT JOIN categories c ON c.id = p.category_id 
    LEFT JOIN brands b ON b.id = p.brand_id 
    WHERE p.is_active = 1 AND p.deleted_at IS NULL 
    LIMIT 1
')->fetch();

if (!$sampleProd) {
    // Fallback mock row
    $sampleProd = [
        'id' => 1,
        'name' => 'Premium Miniket Rice 5kg',
        'slug' => 'premium-miniket-rice-5kg',
        'price' => '380.00',
        'discount_price' => '360.00',
        'stock' => 50,
        'sku' => 'RICE-MINIKET-05',
        'thumbnail' => 'products/rice.webp',
        'description' => 'Best quality polished aromatic miniket rice.',
        'category_name' => 'Rice & Grains',
        'brand_name' => 'Teer',
        'avg_rating' => '4.8',
        'review_count' => 12
    ];
}

$prodJson = get_json_ld_schema('product', ['product' => $sampleProd]);
$prodData = json_decode(strip_tags($prodJson), true);

assert_test(is_array($prodData) && ($prodData['@type'] ?? '') === 'Product', 'Product JSON-LD schema is valid JSON with @type Product');
assert_test($prodData['name'] === $sampleProd['name'], 'Product schema contains exact real product name');
assert_test(isset($prodData['offers']) && $prodData['offers']['priceCurrency'] === 'BDT', 'Product offers section specifies BDT currency');
assert_test(isset($prodData['offers']['availability']) && str_contains($prodData['offers']['availability'], 'InStock'), 'Product in-stock availability correctly reflected in Offer');

if ((int)($sampleProd['review_count'] ?? 0) > 0) {
    assert_test(isset($prodData['aggregateRating']), 'Product with real reviews emits valid aggregateRating structured data');
} else {
    assert_test(!isset($prodData['aggregateRating']), 'Product with zero reviews strictly omits fake aggregateRating');
}

// --------------------------------------------------------------------------
// Group 5: Breadcrumbs Structured Data
// --------------------------------------------------------------------------
echo "\n--- Group 5: BreadcrumbList Structured Data ---\n";

$trail = [
    ['title' => 'Shop', 'link' => 'products.php'],
    ['title' => 'Rice & Grains', 'link' => 'products.php?category=rice-grains'],
    ['title' => 'Premium Miniket Rice 5kg']
];

$bcJson = get_json_ld_schema('breadcrumbs', ['breadcrumbs' => $trail]);
$bcData = json_decode(strip_tags($bcJson), true);

assert_test(is_array($bcData) && ($bcData['@type'] ?? '') === 'BreadcrumbList', 'BreadcrumbList JSON-LD schema is valid JSON');
assert_test(count($bcData['itemListElement'] ?? []) === 4, 'BreadcrumbList contains 4 items (Home + 3 trail items)');
assert_test($bcData['itemListElement'][0]['name'] === 'Home' && $bcData['itemListElement'][0]['item'] === 'https://groco.site.je/', 'Position 1 is Home pointing to canonical root');

// --------------------------------------------------------------------------
// Group 6: Dynamic XML Sitemap Verification
// --------------------------------------------------------------------------
echo "\n--- Group 6: XML Sitemap Verification ---\n";

ob_start();
require __DIR__ . '/../public/sitemap.php';
$sitemapXml = ob_get_clean();

assert_test(!empty($sitemapXml) && str_starts_with(trim($sitemapXml), '<?xml'), 'sitemap.php outputs valid XML header');
assert_test(str_contains($sitemapXml, '<urlset') && str_contains($sitemapXml, 'http://www.sitemaps.org/schemas/sitemap/0.9'), 'sitemap.php contains valid urlset namespace');
assert_test(str_contains($sitemapXml, 'https://groco.site.je/'), 'sitemap.php includes canonical homepage URL');
assert_test(str_contains($sitemapXml, 'https://groco.site.je/products.php'), 'sitemap.php includes canonical products catalog URL');
assert_test(str_contains($sitemapXml, '<image:image>'), 'sitemap.php includes Google Image sitemap tags');
assert_test(!str_contains($sitemapXml, '/admin/') && !str_contains($sitemapXml, 'login.php') && !str_contains($sitemapXml, 'cart.php'), 'sitemap.php strictly excludes admin, auth, and cart URLs');

// --------------------------------------------------------------------------
// Group 7: robots.txt Verification
// --------------------------------------------------------------------------
echo "\n--- Group 7: robots.txt Rules Verification ---\n";

$robotsContent = file_get_contents(__DIR__ . '/../public/robots.txt');
assert_test(str_contains($robotsContent, 'User-agent: *'), 'robots.txt includes User-agent: * rule');
assert_test(str_contains($robotsContent, 'Disallow: /admin/'), 'robots.txt disallows /admin/');
assert_test(str_contains($robotsContent, 'Disallow: /cart.php') && str_contains($robotsContent, 'Disallow: /checkout.php'), 'robots.txt disallows cart and checkout routes');
assert_test(str_contains($robotsContent, 'Allow: /') && str_contains($robotsContent, 'Allow: /product.php'), 'robots.txt allows public storefront and product detail routes');
assert_test(str_contains($robotsContent, 'Sitemap: https://groco.site.je/sitemap.xml'), 'robots.txt declares canonical Sitemap URL (https://groco.site.je/sitemap.xml)');

// --------------------------------------------------------------------------
// Group 8: Product & Category Cards Image Alt & Lazyloading
// --------------------------------------------------------------------------
echo "\n--- Group 8: Image SEO Markup Verification ---\n";

$productCardContent = file_get_contents(__DIR__ . '/../public/components/product-card.php');
assert_test(str_contains($productCardContent, 'alt="<?= e($productName) ?>"'), 'product-card.php includes descriptive image alt attribute');
assert_test(str_contains($productCardContent, 'loading="lazy"') && str_contains($productCardContent, 'decoding="async"'), 'product-card.php specifies loading=lazy and decoding=async');

$categoryCardContent = file_get_contents(__DIR__ . '/../public/components/category-card.php');
assert_test(str_contains($categoryCardContent, 'alt="<?= e($catName) ?>"'), 'category-card.php includes descriptive image alt attribute');

echo "\n======================================================================\n";
echo " SEO AUDIT TEST SUMMARY: {$passCount}/{$testCount} PASSED\n";
if ($failCount === 0) {
    echo " RESULT: ALL PRODUCTION SEO AUDIT ASSERTIONS PASSED (100% SUCCESS)\n";
} else {
    echo " RESULT: {$failCount} TEST(S) FAILED\n";
}
echo "======================================================================\n\n";

if ($failCount > 0) {
    exit(1);
}
