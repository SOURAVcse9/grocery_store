<?php
/**
 * ==========================================================================
 * public/components/meta-tags.php
 * ==========================================================================
 * Dynamic SEO Meta Tags, Open Graph, Twitter Cards, & JSON-LD Schemas.
 * Rendered within the <head> element of header.php.
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/seo.php';

// Normalize page title
$pageTitle = $pageTitle ?? site_name() . ' | Online Grocery Shopping in Bangladesh';
$pageTitle = format_seo_title($pageTitle);

// Normalize page description
$rawDescription = $pageDescription ?? get_setting('site_meta_description') ?? '';
$pageDescription = format_meta_description($rawDescription);

// Normalize canonical URL
if (!isset($pageCanonical)) {
    if (isset($canonicalUrl)) {
        $pageCanonical = $canonicalUrl;
    } else {
        $pageCanonical = build_canonical_url();
    }
}

// Normalize robots directive
$robotsDirective = get_meta_robots($pageRobots ?? null);

// Normalize social share preview image
if (!isset($pageImage)) {
    if (isset($ogImage)) {
        $pageImage = $ogImage;
    } elseif (isset($product['thumbnail']) && !empty($product['thumbnail'])) {
        $pageImage = image_url($product['thumbnail'], 'products');
    } else {
        $pageImage = image_url('ui/logo.png');
    }
}

// Identify page context
$scriptName = basename($_SERVER['SCRIPT_NAME'] ?? '');
$isHomepage = ($scriptName === 'index.php' || $scriptName === '') && empty($_SERVER['QUERY_STRING']);
$isProductPage = ($scriptName === 'product.php' && isset($product) && is_array($product));
$isCategoryPage = ($scriptName === 'products.php' && !empty($_GET['category']));
$isShopPage = ($scriptName === 'products.php');
?>
<!-- Pre-connect and DNS-prefetch external origins -->
<link rel="dns-prefetch" href="https://fonts.googleapis.com">
<link rel="dns-prefetch" href="https://cdnjs.cloudflare.com">

<!-- Favicon & Apple Touch Icons -->
<link rel="icon" type="image/png" href="<?= asset('images/ui/logo.png') ?>">
<link rel="apple-touch-icon" href="<?= asset('images/ui/logo.png') ?>">

<?php 
// Google Search Console Webmaster Verification
$gscToken = getenv('GSC_VERIFICATION_TOKEN') ?: (function_exists('get_setting') ? get_setting('gsc_verification_token') : null);
if (!empty($gscToken)): 
?>
<meta name="google-site-verification" content="<?= e((string)$gscToken) ?>">
<?php endif; ?>

<!-- Robots & Indexing Directives -->
<meta name="robots" content="<?= e($robotsDirective) ?>">
<meta name="googlebot" content="<?= e($robotsDirective) ?>">

<!-- Standard Meta Tags -->
<meta name="description" content="<?= e($pageDescription) ?>">
<link rel="canonical" href="<?= e($pageCanonical) ?>">

<!-- Open Graph / Facebook -->
<meta property="og:site_name" content="<?= e(site_name()) ?>">
<meta property="og:locale" content="en_US">
<meta property="og:title" content="<?= e($pageTitle) ?>">
<meta property="og:description" content="<?= e($pageDescription) ?>">
<meta property="og:type" content="<?= $isProductPage ? 'product' : 'website' ?>">
<meta property="og:url" content="<?= e($pageCanonical) ?>">
<meta property="og:image" content="<?= e($pageImage) ?>">
<meta property="og:image:alt" content="<?= e($pageTitle) ?>">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">

<?php if ($isProductPage && isset($product)): 
    $ogPrice = (float)($product['discount_price'] ?? $product['price']);
    $ogInStock = ((int)($product['stock'] ?? 0) > 0);
?>
<!-- Open Graph Product Meta Extensions -->
<meta property="product:price:amount" content="<?= number_format($ogPrice, 2, '.', '') ?>">
<meta property="product:price:currency" content="BDT">
<meta property="product:availability" content="<?= $ogInStock ? 'in stock' : 'out of stock' ?>">
<?php if (!empty($product['brand_name'])): ?>
<meta property="product:brand" content="<?= e($product['brand_name']) ?>">
<?php endif; ?>
<?php if (!empty($product['category_name'])): ?>
<meta property="product:category" content="<?= e($product['category_name']) ?>">
<?php endif; ?>
<?php endif; ?>

<!-- Twitter / X Card -->
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= e($pageTitle) ?>">
<meta name="twitter:description" content="<?= e($pageDescription) ?>">
<meta name="twitter:image" content="<?= e($pageImage) ?>">
<meta name="twitter:image:alt" content="<?= e($pageTitle) ?>">

<!-- Referrer Security Policy -->
<meta name="referrer" content="strict-origin-when-cross-origin">

<!-- --------------------------------------------------------------------- -->
<!-- JSON-LD SEO Structured Data Schemas                                  -->
<!-- --------------------------------------------------------------------- -->
<?php
// 1. Homepage Schemas: Organization, Local Store & WebSite SearchAction
if ($isHomepage) {
    echo get_json_ld_schema('organization');
    echo get_json_ld_schema('local_business');
    echo get_json_ld_schema('website');
}

// 2. Product Detail Page Schema (Google Merchant / Search Compliant)
if ($isProductPage && isset($product)) {
    $schemaData = [
        'product' => $product,
        'gallery' => $gallery ?? [],
        'reviews' => $productReviews ?? ($reviews ?? [])
    ];
    echo get_json_ld_schema('product', $schemaData);
}

// 3. Breadcrumbs Schema
if (!empty($breadcrumbs) && is_array($breadcrumbs)) {
    echo get_json_ld_schema('breadcrumbs', ['breadcrumbs' => $breadcrumbs]);
}
?>

<?php 
// Google Analytics 4 (GA4) Global Site Tag
$gaMeasurementId = getenv('GA_MEASUREMENT_ID') ?: (function_exists('get_setting') ? (get_setting('google_analytics') ?: get_setting('ga_measurement_id')) : null);
if (!empty($gaMeasurementId)): 
?>
<!-- Google Analytics 4 -->
<script async src="https://www.googletagmanager.com/gtag/js?id=<?= e((string)$gaMeasurementId) ?>"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  gtag('config', <?= json_encode((string)$gaMeasurementId) ?>);
</script>
<?php endif; ?>

<?php if ($isProductPage && isset($product) && is_array($product)): 
    $dlPrice = (float)($product['discount_price'] ?? $product['price'] ?? 0);
?>
<!-- eCommerce GA4 view_item Event -->
<script>
  window.dataLayer = window.dataLayer || [];
  dataLayer.push({ ecommerce: null }); // Clear previous ecommerce object
  dataLayer.push({
    event: 'view_item',
    ecommerce: {
      currency: 'BDT',
      value: <?= number_format($dlPrice, 2, '.', '') ?>,
      items: [{
        item_id: <?= json_encode((string)($product['id'] ?? '')) ?>,
        item_name: <?= json_encode((string)($product['name'] ?? '')) ?>,
        item_category: <?= json_encode((string)($product['category_name'] ?? '')) ?>,
        item_brand: <?= json_encode((string)($product['brand_name'] ?? '')) ?>,
        price: <?= number_format($dlPrice, 2, '.', '') ?>
      }]
    }
  });
</script>
<?php endif; ?>

