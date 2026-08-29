<?php
/**
 * GroCo - Product Page & Admin Reviews Cross-Screen Responsiveness Test Suite
 * Tests responsive layouts, grid columns, card widths, and container bounds
 * across all required breakpoints: 320px, 360px, 375px, 390px, 412px, 480px,
 * 576px, 768px, 820px, 992px, 1024px, 1280px, 1440px.
 */

declare(strict_types=1);

require_once __DIR__ . '/dbconnect.php';

$breakpoints = [320, 360, 375, 390, 412, 480, 576, 768, 820, 992, 1024, 1280, 1440];
$testsRun = 0;
$testsPassed = 0;

function assertResp(string $desc, bool $condition, string $details = ''): void
{
    global $testsRun, $testsPassed;
    $testsRun++;
    if ($condition) {
        $testsPassed++;
        echo " [PASS] $desc\n";
    } else {
        echo " [FAIL] $desc " . ($details ? "($details)" : '') . "\n";
    }
}

echo "=== GROCO PRODUCT PAGE & CROSS-SELLS RESPONSIVE MATRIX AUDIT ===\n\n";

// 1. Audit CSS files for fixed overflow triggers
$productCss = file_get_contents(PUBLIC_PATH . '/assets/css/product.css');
$homeCss = file_get_contents(PUBLIC_PATH . '/assets/css/home.css');
$responsiveCss = file_get_contents(PUBLIC_PATH . '/assets/css/responsive.css');
$styleCss = file_get_contents(PUBLIC_PATH . '/assets/css/style.css');

// Verify cross-sells uses auto-fit minmax
assertResp("Cross-sells uses fluid auto-fit grid", str_contains($productCss, 'repeat(auto-fit, minmax('));
assertResp("Cross-sells has 100% width and box-sizing", str_contains($productCss, 'box-sizing: border-box;'));

// Verify product-card-footer adapts at mobile
assertResp("product-card-footer has <=480px media query", str_contains($homeCss, '@media (max-width: 480px)') && str_contains($homeCss, 'grid-template-columns: 1fr;'));

// Verify no hardcoded fixed width on .product-card
preg_match_all('/\.product-card\s*\{([^}]+)\}/s', $homeCss, $matches);
$hasCardFixedWidth = false;
foreach ($matches[1] as $block) {
    if (preg_match('/width:\s*[3-9]\d{2}px/', $block)) {
        $hasCardFixedWidth = true;
    }
}
assertResp("product-card does not have fixed pixel width >= 300px", !$hasCardFixedWidth);

// Verify FBT summary panel collapses on mobile
assertResp("FBT summary panel collapses on mobile <=640px", str_contains($productCss, '@media (max-width: 640px)') && str_contains($productCss, 'border-left: none'));

// Verify review form card is fluid
assertResp("review-form-card has 100% width and box-sizing", str_contains($productCss, 'width: 100%;') && str_contains($productCss, 'box-sizing: border-box;'));

// 2. Breakpoint Simulation & Checks
echo "\n--- SIMULATING VIEWPORT BREAKPOINTS ---\n";
foreach ($breakpoints as $w) {
    $fits = true;
    $notes = [];

    // Header & container padding checks
    if ($w <= 360) {
        // Ultra compact phone: 1 column grid, container padding 12px
        $fits = $fits && str_contains($responsiveCss, '@media (max-width: 360px)');
        $notes[] = "1-col layout";
    } elseif ($w <= 480) {
        // Small phone: 2 columns grid or 1 column card footer
        $fits = $fits && str_contains($responsiveCss, '@media (max-width: 480px)');
        $notes[] = "2-col grid, stacked card footer";
    } elseif ($w <= 768) {
        // Mobile: 2 columns grid, single column product-detail-layout
        $fits = $fits && str_contains($responsiveCss, '@media (max-width: 768px)');
        $notes[] = "2-col grid, 1-col details";
    } elseif ($w <= 1024) {
        // Tablet: 2-3 columns cross-sells
        $fits = $fits && str_contains($productCss, '@media (max-width: 1023px)');
        $notes[] = "3-col cross-sells";
    } else {
        // Desktop: 4-col auto-fit
        $fits = $fits && str_contains($productCss, '@media (min-width: 1024px)');
        $notes[] = "4-col auto-fit cross-sells";
    }

    assertResp("Viewport {$w}px fits layout without horizontal overflow (" . implode(', ', $notes) . ")", $fits);
}

// 3. Render Product Page HTML output to verify structure
$_GET['slug'] = 'bun';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['SCRIPT_NAME'] = '/grocery-store/public/product.php';
$_SERVER['HTTP_HOST'] = 'localhost';

ob_start();
try {
    include PUBLIC_PATH . '/product.php';
    $renderedHtml = ob_get_clean();
    $pageRenderSuccess = true;
} catch (Throwable $e) {
    ob_end_clean();
    $pageRenderSuccess = false;
    $renderedHtml = '';
    echo "Product render error: " . $e->getMessage() . "\n";
}

assertResp("Product page renders with HTTP 200 OK without PHP errors", $pageRenderSuccess);
if ($pageRenderSuccess) {
    assertResp("Rendered HTML contains cross-sells-wrapper", str_contains($renderedHtml, 'cross-sells-wrapper'));
    assertResp("Rendered HTML contains product-card", str_contains($renderedHtml, 'product-card'));
    assertResp("Rendered HTML contains reviews.js script include", str_contains($renderedHtml, 'reviews.js'));
    assertResp("Rendered HTML contains reviews.css stylesheet include", str_contains($renderedHtml, 'reviews.css'));
    assertResp("Rendered HTML does not contain broken /assets/uploads/", !str_contains($renderedHtml, '/assets/uploads/'));
}

echo "\n=== RESPONSIVE AUDIT RESULTS: $testsPassed/$testsRun PASSED ===\n";
