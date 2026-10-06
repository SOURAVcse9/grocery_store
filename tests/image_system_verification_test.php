<?php
/**
 * ==========================================================================
 * tests/image_system_verification_test.php
 * ==========================================================================
 * Automated Verification Suite for Product and Category Image Systems.
 * Validates aspect ratios, containment, clipping, dark theme compliance,
 * responsive breakpoints, and fallback handling.
 * ==========================================================================
 */

declare(strict_types=1);



$totalTests = 0;
$passedTests = 0;
$failedTests = 0;

function it(string $description, bool $condition, string $details = ''): void {
    global $totalTests, $passedTests, $failedTests;
    $totalTests++;
    if ($condition) {
        $passedTests++;
        echo "  [PASS] {$description}\n";
    } else {
        $failedTests++;
        echo "  [FAIL] {$description}" . ($details ? " -> {$details}" : "") . "\n";
    }
}

echo "\n=======================================================\n";
echo " GroCo Image System Verification Test Suite\n";
echo "=======================================================\n\n";

// --- Section 1: Category Image System Verification ---
echo "--- 1. Category Image System Verification ---\n";

$categoryCardFile = file_get_contents(__DIR__ . '/../public/components/category-card.php');
$homeCss = file_get_contents(__DIR__ . '/../public/assets/css/home.css');
$productsCss = file_get_contents(__DIR__ . '/../public/assets/css/products.css');
$productCardFile = file_get_contents(__DIR__ . '/../public/components/product-card.php');

it('category-card.php includes category-image-wrapper class',
    strpos($categoryCardFile, 'category-image-wrapper') !== false);

it('category-card.php includes category-card-image-wrapper class',
    strpos($categoryCardFile, 'category-card-image-wrapper') !== false);

it('category-card.php includes category-card-image image class',
    strpos($categoryCardFile, 'category-card-image') !== false);

it('category-card.php includes onerror fallback handler',
    strpos($categoryCardFile, 'onerror="this.onerror=null;') !== false);

it('category-card.php uses limit crop mode for Cloudinary',
    strpos($categoryCardFile, "'c' => 'limit'") !== false);

it('home.css category image wrapper enforces border-radius: 50%',
    preg_match('/\.category-image-wrapper[^{]*\{[^}]*border-radius:\s*50%/s', $homeCss) === 1);

it('home.css category image wrapper enforces clip-path circle',
    preg_match('/\.category-image-wrapper[^{]*\{[^}]*clip-path:\s*circle\(50% at 50% 50%\)/s', $homeCss) === 1);

it('home.css category image wrapper uses theme background var(--color-bg) (not white)',
    preg_match('/\.category-image-wrapper[^{]*\{[^}]*background:\s*var\(--color-bg\)/s', $homeCss) === 1 &&
    preg_match('/\.category-image-wrapper[^{]*\{[^}]*background:\s*(#ffffff|white);/s', $homeCss) === 0);

it('home.css category image itself enforces object-fit: contain',
    preg_match('/\.category-card-image[^{]*\{[^}]*object-fit:\s*contain/s', $homeCss) === 1);

it('home.css category image itself enforces border-radius: 50% and clip-path',
    preg_match('/\.category-card-image[^{]*\{[^}]*border-radius:\s*50%/s', $homeCss) === 1 &&
    preg_match('/\.category-card-image[^{]*\{[^}]*clip-path:\s*circle\(50% at 50% 50%\)/s', $homeCss) === 1);

it('home.css category image itself does not force white background',
    preg_match('/\.category-card-image[^{]*\{[^}]*background:\s*(#ffffff|white);/s', $homeCss) === 0);

it('home.css category image wrapper has mobile responsive breakpoints (576px and 375px)',
    preg_match('/@media\s*\(\s*max-width:\s*576px\s*\)[^@]+category-image-wrapper/s', $homeCss) === 1 &&
    preg_match('/@media\s*\(\s*max-width:\s*375px\s*\)[^@]+category-image-wrapper/s', $homeCss) === 1);


// --- Section 2: Product Image System Verification ---
echo "\n--- 2. Product Image System Verification ---\n";

it('product-card.php includes product-card-image wrapper class',
    strpos($productCardFile, 'product-card-image') !== false);

it('product-card.php includes product-image-wrapper class',
    strpos($productCardFile, 'product-image-wrapper') !== false);

it('product-card.php includes product-card-img image class',
    strpos($productCardFile, 'product-card-img') !== false);

it('product-card.php includes onerror fallback handler',
    strpos($productCardFile, 'onerror="this.onerror=null;') !== false);

it('product-card.php uses limit crop mode for Cloudinary (not fill)',
    strpos($productCardFile, "'c' => 'limit'") !== false &&
    strpos($productCardFile, "'c' => 'fill'") === false);

it('home.css product image wrapper enforces aspect-ratio: 1 / 1',
    preg_match('/\.product-card-image[^{]*\{[^}]*aspect-ratio:\s*1\s*\/\s*1/s', $homeCss) === 1);

it('home.css product image enforces object-fit: contain',
    preg_match('/\.product-card-img[^{]*\{[^}]*object-fit:\s*contain/s', $homeCss) === 1);

it('home.css product image wrapper uses var(--color-bg) (not white)',
    preg_match('/\.product-card-image[^{]*\{[^}]*background:\s*var\(--color-bg\)/s', $homeCss) === 1 &&
    preg_match('/\.product-card-image[^{]*\{[^}]*background:\s*(#ffffff|white);/s', $homeCss) === 0);

it('products.css list-view supports .product-card-image',
    strpos($productsCss, '.products-grid.list-view .product-card-image') !== false);

it('products.css list-view supports responsive 768px layout',
    preg_match('/@media\s*\(\s*max-width:\s*768px\s*\)[^@]+product-card-image/s', $productsCss) === 1);


// --- Section 3: Summary ---
echo "\n=======================================================\n";
echo " Image System Verification Results: {$passedTests}/{$totalTests} PASSED\n";
if ($failedTests > 0) {
    echo " {$failedTests} tests FAILED.\n";
    exit(1);
} else {
    echo " All verification checks PASSED successfully! Both image systems are verified.\n";
    echo "=======================================================\n\n";
    exit(0);
}
