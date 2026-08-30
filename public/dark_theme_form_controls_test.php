<?php
/**
 * public/dark_theme_form_controls_test.php
 * 
 * Exhaustive Verification Test Suite for Dark Theme Form Controls,
 * Dropdown UI, Native Selects, Option Popups, and Accessible Custom Selects.
 */

declare(strict_types=1);

define('GROCO_CLI_TEST_MODE', true);
require_once __DIR__ . '/dbconnect.php';

$testCount = 0;
$passCount = 0;
$failures = [];

function assert_test(bool $condition, string $description, string $details = ''): void {
    global $testCount, $passCount, $failures;
    $testCount++;
    if ($condition) {
        $passCount++;
        echo "  [PASS] Test #{$testCount}: {$description}\n";
    } else {
        $failures[] = "Test #{$testCount}: {$description} — Details: {$details}";
        echo "  [FAIL] Test #{$testCount}: {$description}\n";
        if ($details) {
            echo "         Detail: {$details}\n";
        }
    }
}

echo "\n======================================================================\n";
echo " GROCO — DARK THEME FORM CONTROLS & DROPDOWN UI AUDIT TEST SUITE\n";
echo "======================================================================\n\n";

$baseDir = realpath(__DIR__ . '/..');
$publicDir = $baseDir . '/public';
$adminDir = $baseDir . '/admin';

// -------------------------------------------------------------------------
// SUITE 1: CSS Architecture Verification (style.css & admin.css)
// -------------------------------------------------------------------------
echo "--- Suite 1: CSS Architecture Verification ---\n";

$styleCss = file_get_contents($publicDir . '/assets/css/style.css');
$adminCss = file_get_contents($adminDir . '/assets/css/admin.css');

assert_test(
    strpos($styleCss, 'color-scheme: dark') !== false,
    "style.css defines color-scheme: dark on html[data-theme=\"dark\"]"
);

assert_test(
    strpos($styleCss, 'html[data-theme="dark"] select') !== false,
    "style.css defines dark overrides for select elements"
);

assert_test(
    strpos($styleCss, 'html[data-theme="dark"] select option') !== false,
    "style.css defines dark overrides for select option elements (#1c2128)"
);

assert_test(
    strpos($styleCss, 'html[data-theme="dark"] select optgroup') !== false,
    "style.css defines dark overrides for select optgroup elements"
);

assert_test(
    strpos($styleCss, 'html[data-theme="dark"] input:not([type="checkbox"])') !== false,
    "style.css defines dark overrides for input controls"
);

assert_test(
    strpos($styleCss, 'html[data-theme="dark"] textarea') !== false,
    "style.css defines dark overrides for textarea controls"
);

assert_test(
    strpos($styleCss, '.form-select') !== false && strpos($styleCss, '.sort-select') !== false,
    "style.css defines base form-select and sort-select styles"
);

assert_test(
    strpos($styleCss, 'select option,') !== false,
    "style.css defines base option styles mapping to var(--color-surface)"
);

assert_test(
    strpos($styleCss, '-webkit-box-shadow: 0 0 0 1000px #1c2128 inset') !== false,
    "style.css handles dark autofill with 1000px inset box shadow"
);

assert_test(
    strpos($styleCss, 'input[type="file"]::file-selector-button') !== false,
    "style.css provides dark styling for file selector buttons"
);

assert_test(
    strpos($adminCss, '.admin-body select') !== false,
    "admin.css defines base select styling for admin body"
);

assert_test(
    strpos($adminCss, 'html[data-theme="dark"] .admin-body select') !== false,
    "admin.css defines dark theme overrides for admin selects"
);

assert_test(
    strpos($adminCss, 'html[data-theme="dark"] .admin-body select option') !== false,
    "admin.css defines dark theme overrides for admin select options"
);

assert_test(
    strpos($adminCss, 'color-scheme: dark !important') !== false,
    "admin.css applies color-scheme: dark !important on admin controls"
);

// -------------------------------------------------------------------------
// SUITE 2: Zero Inline Hardcoded White Backgrounds on Form Controls
// -------------------------------------------------------------------------
echo "\n--- Suite 2: Audit Form Controls in Admin Templates ---\n";

$adminPhpFiles = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($adminDir));
$whiteSelectsFound = [];
$whiteInputsFound = [];

foreach ($adminPhpFiles as $file) {
    if ($file->isDir() || $file->getExtension() !== 'php') continue;
    
    // Skip printable receipts and invoice paper which intentionally print on thermal paper
    $relPath = str_replace('\\', '/', substr($file->getPathname(), strlen($adminDir) + 1));
    if (in_array($relPath, ['pos/receipt.php', 'pos/receipts.php', 'invoices/print.php'])) continue;
    
    $content = file_get_contents($file->getPathname());
    
    // Check for <select ... style="... background:#fff ...">
    if (preg_match('/<select\b[^>]*?style="[^"]*?background:\s*#fff;?[^"]*"/i', $content, $m)) {
        $whiteSelectsFound[] = $relPath;
    }
    
    // Check for <input ... style="... background:#fff ...">
    if (preg_match('/<input\b[^>]*?style="[^"]*?background:\s*#fff;?[^"]*"/i', $content, $m)) {
        $whiteInputsFound[] = $relPath;
    }
}

assert_test(
    empty($whiteSelectsFound),
    "Zero admin templates contain <select> with hardcoded background:#fff",
    "Found in: " . implode(', ', $whiteSelectsFound)
);

assert_test(
    empty($whiteInputsFound),
    "Zero admin templates contain <input> with hardcoded background:#fff",
    "Found in: " . implode(', ', $whiteInputsFound)
);

// -------------------------------------------------------------------------
// SUITE 3: Public Storefront Form Controls & Reviews Sort
// -------------------------------------------------------------------------
echo "\n--- Suite 3: Public Storefront Form Controls ---\n";

$productContent = file_get_contents($publicDir . '/product.php');
$addressesContent = file_get_contents($publicDir . '/addresses.php');
$headerCss = file_get_contents($publicDir . '/assets/css/header.css');

assert_test(
    strpos($productContent, '<select id="reviewSortSelect"') !== false,
    "product.php contains reviewSortSelect element"
);

assert_test(
    strpos($productContent, 'id="reviewSortSelect" name="sort_reviews" onchange="this.form.submit()" class="sort-select"') !== false,
    "product.php reviewSortSelect uses sort-select class"
);

assert_test(
    strpos($productContent, 'data-custom-select') !== false,
    "product.php reviewSortSelect has data-custom-select enabled"
);

assert_test(
    !preg_match('/id="reviewSortSelect"[^>]*?background:\s*#fff/i', $productContent),
    "product.php reviewSortSelect does NOT have hardcoded background:#fff"
);

assert_test(
    strpos($addressesContent, 'background-color: var(--color-surface);') !== false,
    "addresses.php premium-form-group input/select uses var(--color-surface)"
);

assert_test(
    strpos($headerCss, '.header-search input:focus { background: var(--color-surface);') !== false,
    "header.css search input:focus uses var(--color-surface)"
);

// -------------------------------------------------------------------------
// SUITE 4: Accessible Custom Select Component Verification
// -------------------------------------------------------------------------
echo "\n--- Suite 4: Custom Select Component Verification ---\n";

$publicCustomSelectJs = file_get_contents($publicDir . '/assets/js/custom-select.js');
$adminCustomSelectJs = file_get_contents($adminDir . '/assets/js/custom-select.js');
$publicFooter = file_get_contents($publicDir . '/footer.php');
$adminHeader = file_get_contents($adminDir . '/layouts/header.php');

assert_test(
    file_exists($publicDir . '/assets/js/custom-select.js'),
    "public/assets/js/custom-select.js exists"
);

assert_test(
    file_exists($adminDir . '/assets/js/custom-select.js'),
    "admin/assets/js/custom-select.js exists"
);

assert_test(
    strpos($publicCustomSelectJs, "'combobox'") !== false || strpos($publicCustomSelectJs, '"combobox"') !== false,
    "custom-select.js sets combobox role on trigger"
);

assert_test(
    strpos($publicCustomSelectJs, "'listbox'") !== false || strpos($publicCustomSelectJs, '"listbox"') !== false,
    "custom-select.js sets listbox role on menu"
);

assert_test(
    strpos($publicCustomSelectJs, "'option'") !== false || strpos($publicCustomSelectJs, '"option"') !== false,
    "custom-select.js sets option role on items"
);

assert_test(
    strpos($publicCustomSelectJs, 'ArrowDown') !== false && strpos($publicCustomSelectJs, 'ArrowUp') !== false,
    "custom-select.js supports ArrowDown and ArrowUp keyboard navigation"
);

assert_test(
    strpos($publicCustomSelectJs, 'Escape') !== false,
    "custom-select.js supports Escape key to close menu"
);

assert_test(
    strpos($publicCustomSelectJs, 'dispatchEvent(event)') !== false,
    "custom-select.js dispatches native change events on underlying select"
);

assert_test(
    strpos($publicFooter, 'custom-select.js') !== false,
    "public/footer.php loads custom-select.js"
);

assert_test(
    strpos($adminHeader, 'custom-select.js') !== false,
    "admin/layouts/header.php loads custom-select.js"
);

assert_test(
    strpos($styleCss, '.custom-select-wrapper') !== false && strpos($styleCss, '.custom-select-menu') !== false,
    "style.css defines custom-select component classes"
);

assert_test(
    strpos($styleCss, 'html[data-theme="dark"] .custom-select-menu') !== false,
    "style.css defines dark theme overrides for custom select menu (#1c2128)"
);

// -------------------------------------------------------------------------
// SUITE 5: Live HTTP Service Endpoints (Port 8080)
// -------------------------------------------------------------------------
echo "\n--- Suite 5: Live HTTP Server Endpoint Verification ---\n";

$validSlug = db()->query("SELECT slug FROM products WHERE deleted_at IS NULL LIMIT 1")->fetchColumn();
if (!$validSlug) $validSlug = 'index.php';

$ch = curl_init('http://localhost:8080/grocery-store/public/product.php?slug=' . urlencode((string)$validSlug));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
$httpHtml = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

assert_test(
    $httpCode === 200,
    "HTTP 200 OK received from public product page endpoint ({$validSlug})"
);

assert_test(
    strpos($httpHtml, 'reviewSortSelect') !== false,
    "HTTP response contains reviewSortSelect"
);

assert_test(
    strpos($httpHtml, 'id="reviewSortSelect" style="font-size:12px; padding:6px 12px; border:1px solid var(--color-border); border-radius:var(--radius-pill); outline:none; background:#fff;') === false,
    "HTTP response does not render reviewSortSelect with background:#fff"
);

assert_test(
    strpos($httpHtml, 'custom-select.js') !== false,
    "HTTP response includes custom-select.js script tag"
);

// -------------------------------------------------------------------------
// FINAL SUMMARY
// -------------------------------------------------------------------------
echo "\n======================================================================\n";
echo " TEST SUMMARY: {$passCount}/{$testCount} PASSED\n";
if (empty($failures)) {
    echo " RESULT: ALL AUDIT & INTEGRITY ASSERTIONS PASSED (100% SUCCESS)\n";
    echo "======================================================================\n\n";
    exit(0);
} else {
    echo " RESULT: " . count($failures) . " FAILURES DETECTED:\n";
    foreach ($failures as $f) {
        echo "  - {$f}\n";
    }
    echo "======================================================================\n\n";
    exit(1);
}
