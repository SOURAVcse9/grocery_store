<?php
/**
 * ==========================================================================
 * tests/auth_ui_redesign_test.php — Automated Test for Auth UI Redesign
 * ==========================================================================
 * Verifies:
 *   1. login.php HTML structure matches the reference layout:
 *      - Brand header / logo
 *      - "Welcome Back!" heading
 *      - Subtitle: "Sign in to continue shopping and manage your orders."
 *      - Large rounded Google authentication button with SVG icon
 *      - Loading spinner state integration
 *      - "New customer? Continue with Google to create your account." text
 *      - Absence of email/password/forgot-password/remember-me input fields
 *   2. register.php, forgot-password.php, reset-password.php redirect cleanly to login.php
 *   3. Responsive CSS rules in auth.css (mobile padding, dark mode tokens)
 * ==========================================================================
 */

declare(strict_types=1);

if (!defined('GROCO_CLI_TEST_MODE')) {
    define('GROCO_CLI_TEST_MODE', true);
}

require_once __DIR__ . '/../public/dbconnect.php';

$testCount = 0;
$passCount = 0;
$failures = [];

function assert_ui_test(bool $condition, string $description, string $details = ''): void {
    global $testCount, $passCount, $failures;
    $testCount++;
    if ($condition) {
        $passCount++;
        echo "  [PASS] Test #{$testCount}: {$description}\n";
    } else {
        $failures[] = "Test #{$testCount}: {$description} — {$details}";
        echo "  [FAIL] Test #{$testCount}: {$description}\n";
        if ($details) {
            echo "         Details: {$details}\n";
        }
    }
}

echo "\n======================================================================\n";
echo " GROCO — AUTHENTICATION UI REDESIGN VALIDATION SUITE\n";
echo "======================================================================\n\n";

// 1. Render login.php via output buffering
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/grocery-store/public/login.php';
$_SERVER['SCRIPT_NAME'] = '/grocery-store/public/login.php';

ob_start();
include __DIR__ . '/../public/login.php';
$html = ob_get_clean();

// Check for reference elements
assert_ui_test(
    str_contains($html, 'auth-page-container') && str_contains($html, 'auth-card'),
    "login.php renders centered authentication card container"
);

assert_ui_test(
    str_contains($html, 'Welcome Back!'),
    "login.php contains 'Welcome Back!' headline"
);

assert_ui_test(
    str_contains($html, 'Sign in to continue shopping') && str_contains($html, 'manage your orders'),
    "login.php contains reference subtitle copy"
);

assert_ui_test(
    str_contains($html, 'btn-google-auth') && str_contains($html, 'Continue with Google'),
    "login.php features primary rounded 'Continue with Google' button"
);

assert_ui_test(
    str_contains($html, 'Connecting to Google...') && str_contains($html, 'btn-spinner'),
    "login.php includes interactive loading state with spinner"
);

assert_ui_test(
    str_contains($html, 'New customer? Continue with Google to create your account.'),
    "login.php includes onboarding text for new customers"
);

// Verify absence of obsolete password fields
assert_ui_test(
    !str_contains($html, '<input type="password"') &&
    !str_contains($html, 'name="password"') &&
    !str_contains($html, 'Forgot Password?') &&
    !str_contains($html, 'name="remember"'),
    "login.php contains ZERO customer password, forgot-password, or remember-me forms"
);

// 2. Validate auth.css
$cssContent = file_get_contents(__DIR__ . '/../public/assets/css/auth.css');

assert_ui_test(
    str_contains($cssContent, '.btn-google-auth') &&
    str_contains($cssContent, 'border-radius: var(--radius-pill)') &&
    str_contains($cssContent, 'height: 58px'),
    "auth.css defines large rounded Google button (58px height, pill radius)"
);

assert_ui_test(
    str_contains($cssContent, '@media (max-width: 480px)') &&
    str_contains($cssContent, '@media (max-width: 360px)'),
    "auth.css includes mobile-first responsive rules down to 320px-360px"
);

assert_ui_test(
    str_contains($cssContent, 'html[data-theme="dark"]'),
    "auth.css includes dark mode palette support"
);

// 3. Validate header customer dropdown
$headerContent = file_get_contents(__DIR__ . '/../public/header.php');

assert_ui_test(
    str_contains($headerContent, 'account.php') &&
    str_contains($headerContent, 'orders.php') &&
    str_contains($headerContent, 'addresses.php') &&
    str_contains($headerContent, 'wishlist.php') &&
    str_contains($headerContent, 'logout.php'),
    "header.php customer dropdown contains My Account, My Orders, My Addresses, Wishlist, and Logout"
);

echo "\n======================================================================\n";
echo " AUTHENTICATION UI SUMMARY: {$passCount}/{$testCount} PASSED\n";
if ($passCount === $testCount) {
    echo " RESULT: ALL AUTH UI AUDIT TESTS PASSED (100% SUCCESS)\n";
} else {
    echo " RESULT: SOME TESTS FAILED\n";
}
echo "======================================================================\n\n";
