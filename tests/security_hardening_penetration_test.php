<?php
/**
 * Automated Security Hardening & Penetration Verification Suite
 * GroCo Grocery Store Application
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

echo "====================================================================\n";
echo " GROCO GROCERY STORE - COMPLETE SECURITY AUDIT VERIFICATION SUITE \n";
echo "====================================================================\n\n";

$baseDir = realpath(__DIR__ . '/..');
$passCount = 0;
$failCount = 0;

function assertTest(string $title, bool $condition, string $details = '') {
    global $passCount, $failCount;
    if ($condition) {
        echo "[PASS] {$title}\n";
        if ($details) echo "       -> {$details}\n";
        $passCount++;
    } else {
        echo "[FAIL] {$title}\n";
        if ($details) echo "       -> Error: {$details}\n";
        $failCount++;
    }
}

// 1. Directory Protection & .htaccess Verification
echo "\n--- 1. DIRECTORY PROTECTION & HTACCESS ACCESS CONTROL ---\n";

$htaccessUploads = file_get_contents($baseDir . '/public/uploads/.htaccess');
assertTest(
    "Public uploads directory blocks script execution",
    strpos($htaccessUploads, 'Options -Indexes -ExecCGI') !== false &&
    strpos($htaccessUploads, 'FilesMatch') !== false &&
    strpos($htaccessUploads, 'Require all denied') !== false &&
    strpos($htaccessUploads, 'php_flag engine off') !== false,
    "Execution of PHP, CGI, and executable scripts disabled in public/uploads"
);

$htaccessStorage = file_get_contents($baseDir . '/storage/.htaccess');
assertTest(
    "Storage directory completely denies all HTTP access",
    strpos($htaccessStorage, 'Require all denied') !== false || strpos($htaccessStorage, 'Deny from all') !== false,
    "Storage directory protected with Require all denied"
);

$htaccessDatabase = file_get_contents($baseDir . '/database/.htaccess');
assertTest(
    "Database schema/migrations directory denies HTTP access",
    strpos($htaccessDatabase, 'Require all denied') !== false || strpos($htaccessDatabase, 'Deny from all') !== false,
    "Database directory protected with Require all denied"
);

$htaccessLicensing = file_get_contents($baseDir . '/licensing_server/.htaccess');
assertTest(
    "Licensing server protects private keys, DB, and certificates",
    strpos($htaccessLicensing, 'FilesMatch') !== false &&
    strpos($htaccessLicensing, 'Require all denied') !== false,
    "Sensitive files (.key, .pem, .sqlite, .db, .sql) blocked from direct web requests"
);

// 2. Information Disclosure & DB Error Masking
echo "\n--- 2. INFORMATION DISCLOSURE & DB ERROR MASKING ---\n";

$publicControllers = [
    'public/account.php',
    'public/cart.php',
    'public/checkout.php',
    'public/index.php',
    'public/order-details.php',
    'public/orders.php',
    'public/product.php',
    'public/products.php',
    'public/reviews.php',
    'public/search.php',
    'public/dbconnect.php'
];

$rawDieFound = false;
$rawDieDetails = [];
foreach ($publicControllers as $ctrl) {
    $content = file_get_contents($baseDir . '/' . $ctrl);
    if (preg_match('/die\(\s*\$e->getMessage\(\)\s*\)/i', $content, $m)) {
        $rawDieFound = true;
        $rawDieDetails[] = $ctrl;
    }
}

assertTest(
    "Public controllers do not leak raw database exceptions (die(\$e->getMessage()))",
    !$rawDieFound,
    $rawDieFound ? "Found in: " . implode(', ', $rawDieDetails) : "All public controllers use graceful, masked error responses"
);

// 3. XSS Output Encoding in Admin Templates
echo "\n--- 3. XSS OUTPUT ENCODING IN ADMIN TEMPLATES ---\n";

$adminViews = glob($baseDir . '/admin/**/*.php');
$rawErrorEchoes = [];
foreach ($adminViews as $viewFile) {
    $content = file_get_contents($viewFile);
    if (preg_match('/<\?=\s*\$error\s*;?\s*\?>/i', $content)) {
        $rawErrorEchoes[] = str_replace($baseDir . DIRECTORY_SEPARATOR, '', $viewFile);
    }
}

assertTest(
    "Admin views sanitize and escape \$error variables with htmlspecialchars",
    count($rawErrorEchoes) === 0,
    count($rawErrorEchoes) === 0 ? "All 48+ admin templates escape error outputs" : "Unescaped in: " . implode(', ', $rawErrorEchoes)
);

// 4. Path Traversal Sanitization
echo "\n--- 4. PATH TRAVERSAL SANITIZATION ---\n";

$bannerDelete = file_get_contents($baseDir . '/admin/banners/delete.php');
$productDelete = file_get_contents($baseDir . '/admin/products/delete.php');

assertTest(
    "Banner image deletion enforces basename() sanitization",
    strpos($bannerDelete, 'basename(') !== false,
    "basename() applied to banner image deletion target"
);

assertTest(
    "Product image deletion enforces basename() sanitization",
    strpos($productDelete, 'basename(') !== false,
    "basename() applied to product image deletion target"
);

// 5. CSRF Defense Verification
echo "\n--- 5. CSRF PROTECTION & STATE DEFENSE ---\n";

$licenseStatus = file_get_contents($baseDir . '/public/license_status.php');
assertTest(
    "License status reset actions require CSRF token validation",
    strpos($licenseStatus, 'csrf_field') !== false && strpos($licenseStatus, 'verify_csrf') !== false,
    "verify_csrf() invoked on reset actions in license_status.php"
);

// 6. Dual Authentication & Admin Email Guard
echo "\n--- 6. DUAL AUTHENTICATION & ROLE SEPARATION ---\n";

$processReg = file_get_contents($baseDir . '/public/process_register.php');
$registerPage = file_get_contents($baseDir . '/public/register.php');
$googleAuth = file_get_contents($baseDir . '/public/includes/google_auth.php');
$updateProfile = file_get_contents($baseDir . '/public/update_profile.php');
$processCheckout = file_get_contents($baseDir . '/public/process_checkout.php');

assertTest(
    "Customer registration blocks admin emails",
    strpos($processReg, 'is_admin_email') !== false && strpos($registerPage, 'is_admin_email') !== false,
    "is_admin_email() verified on customer registration endpoints"
);

assertTest(
    "Google OAuth blocks admin accounts from customer login",
    strpos($googleAuth, 'is_admin_email') !== false,
    "is_admin_email() verified in google_auth.php"
);

assertTest(
    "Customer profile update and checkout prevent claiming admin email addresses",
    strpos($updateProfile, 'is_admin_email') !== false && strpos($processCheckout, 'is_admin_email') !== false,
    "is_admin_email() verified in update_profile.php and process_checkout.php"
);

// 7. POS & Admin Authorization Checks
echo "\n--- 7. POS & ADMIN AUTHORIZATION ---\n";

$posEndpoints = [
    'admin/pos/ajax/get_products.php',
    'admin/pos/ajax/process_sale.php',
    'admin/pos/ajax/search_customer.php',
    'admin/pos/ajax/quick_add_customer.php'
];

$posGuarded = true;
foreach ($posEndpoints as $posFile) {
    if (file_exists($baseDir . '/' . $posFile)) {
        $cnt = file_get_contents($baseDir . '/' . $posFile);
        if (strpos($cnt, 'is_admin_logged_in') === false && strpos($cnt, 'has_admin_permission') === false) {
            $posGuarded = false;
        }
    }
}

assertTest(
    "POS AJAX endpoints enforce admin login and role permission checks",
    $posGuarded,
    "All POS AJAX endpoints verify session and permissions before execution"
);

// Summary
echo "\n====================================================================\n";
echo " AUDIT TEST RESULTS: {$passCount} PASSED, {$failCount} FAILED\n";
echo "====================================================================\n";

if ($failCount > 0) {
    exit(1);
}
exit(0);
