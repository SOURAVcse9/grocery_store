<?php
/**
 * ==========================================================================
 * public/components/meta-tags.php
 * ==========================================================================
 * Dynamic SEO Meta Tags & JSON-LD Schema builder.
 * Injected in head section of header.php.
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/seo.php';

$pageTitle = $pageTitle ?? site_name() . ' — Fresh Groceries Online';
$pageDescription = $pageDescription ?? 'Order fresh organic vegetables, fruits, dairy, meat, and grocery essentials online with fast home delivery.';
$pageCanonical = $pageCanonical ?? current_url();
$pageImage = $pageImage ?? asset('images/ui/logo.png');

// Check active request pages to resolve schemas
$isProductPage = (str_contains($_SERVER['SCRIPT_NAME'], 'product.php') && isset($product));
?>
<!-- Pre-connect and DNS-prefetch external domains for performance -->
<link rel="dns-prefetch" href="https://fonts.googleapis.com">
<link rel="dns-prefetch" href="https://cdnjs.cloudflare.com">

<meta name="description" content="<?= e($pageDescription) ?>">
<link rel="canonical" href="<?= e($pageCanonical) ?>">

<!-- Open Graph / Facebook -->
<meta property="og:site_name" content="<?= e(site_name()) ?>">
<meta property="og:title" content="<?= e($pageTitle) ?>">
<meta property="og:description" content="<?= e($pageDescription) ?>">
<meta property="og:type" content="<?= $isProductPage ? 'og:product' : 'website' ?>">
<meta property="og:url" content="<?= e($pageCanonical) ?>">
<meta property="og:image" content="<?= e($pageImage) ?>">
<meta property="og:image:alt" content="<?= e($pageTitle) ?>">

<!-- Twitter Card -->
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= e($pageTitle) ?>">
<meta name="twitter:description" content="<?= e($pageDescription) ?>">
<meta name="twitter:image" content="<?= e($pageImage) ?>">

<!-- Secure Headers (Referrer & Client Hints) -->
<meta name="referrer" content="no-referrer-when-downgrade">

<!-- --------------------------------------------------------------------- -->
<!-- JSON-LD SEO Structured Data schemas                                   -->
<!-- --------------------------------------------------------------------- -->
<?php
// 1. Global Organization Schema
echo get_json_ld_schema('organization');

// 2. Global Local Business Schema
echo get_json_ld_schema('local_business');

// 3. Page Specific: Product Schema (if detail page)
if ($isProductPage && isset($product)) {
    echo get_json_ld_schema('product', ['product' => $product]);
}

// 4. Breadcrumbs Schema (if trail is defined)
if (!empty($breadcrumbs) && is_array($breadcrumbs)) {
    echo get_json_ld_schema('breadcrumbs', ['breadcrumbs' => $breadcrumbs]);
}
?>
