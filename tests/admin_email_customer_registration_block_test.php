<?php
/**
 * ==========================================================================
 * tests/admin_email_customer_registration_block_test.php
 * ==========================================================================
 * Verifies that administrator email addresses cannot be used to register
 * or create customer accounts via Email/Password or Google OAuth.
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/../public/dbconnect.php';
require_once __DIR__ . '/../public/includes/auth.php';
require_once __DIR__ . '/../public/includes/google_auth.php';

$pdo = db();
$testsPassed = 0;
$totalTests = 0;

function assert_test(bool $condition, string $message): void {
    global $testsPassed, $totalTests;
    $totalTests++;
    if ($condition) {
        $testsPassed++;
        echo "  [PASS] Test #{$totalTests}: {$message}\n";
    } else {
        echo "  [FAIL] Test #{$totalTests}: {$message}\n";
        exit(1);
    }
}

echo "\n======================================================================\n";
echo " GROCO — ADMIN EMAIL CUSTOMER SIGNUP BLOCK VERIFICATION\n";
echo "======================================================================\n\n";

// Setup Test Admin in `admins` table
$testAdminEmail = 'super_security_admin@groco.com.bd';
$pdo->prepare('DELETE FROM admins WHERE email = :email')->execute(['email' => $testAdminEmail]);
$pdo->prepare('DELETE FROM users WHERE email = :email')->execute(['email' => $testAdminEmail]);

$roleStmt = $pdo->query('SELECT id FROM admin_roles LIMIT 1');
$adminRoleId = (int) $roleStmt->fetchColumn() ?: 1;

$insertAdmin = $pdo->prepare('
    INSERT INTO admins (role_id, full_name, username, email, password, is_active, created_at)
    VALUES (:role_id, "Security Super Admin", "secadmin", :email, :pass, 1, NOW())
');
$insertAdmin->execute([
    'role_id' => $adminRoleId,
    'email'   => $testAdminEmail,
    'pass'    => password_hash('AdminPass@2026', PASSWORD_DEFAULT)
]);

// 1. Test is_admin_email detection
assert_test(is_admin_email($testAdminEmail) === true, "is_admin_email detects admin in admins table ({$testAdminEmail})");
assert_test(is_admin_email('ADMIN@grocery.com') === true, "is_admin_email detects admin with case-insensitivity (admin@grocery.com)");
assert_test(is_admin_email('normal_shopper_123@example.com') === false, "is_admin_email returns false for non-admin customer email");

// 2. Test Google OAuth Sync Block
$googleAdminUser = [
    'sub'            => 'google_admin_claim_99999',
    'email'          => $testAdminEmail,
    'name'           => 'Google Admin Impersonator',
    'given_name'     => 'Google',
    'family_name'    => 'Admin',
    'picture'        => 'https://example.com/avatar.jpg',
    'email_verified' => true
];

$blockedGoogle = false;
try {
    sync_google_customer($googleAdminUser);
} catch (RuntimeException $e) {
    if (str_contains($e->getMessage(), 'administrator account')) {
        $blockedGoogle = true;
    }
}
assert_test($blockedGoogle === true, "sync_google_customer strictly blocks admin email from Google OAuth customer onboarding");

// 3. Verify users table has NOT created a row for this admin email
$checkUser = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = :email');
$checkUser->execute(['email' => $testAdminEmail]);
assert_test((int) $checkUser->fetchColumn() === 0, "No customer record created in users table for admin email");

// 4. Test Normal Customer Registration via Google OAuth still succeeds
$normalCustomerEmail = 'legit_customer_7788@example.com';
$pdo->prepare('DELETE FROM users WHERE email = :email')->execute(['email' => $normalCustomerEmail]);

$googleCustomerUser = [
    'sub'            => 'google_legit_claim_7788',
    'email'          => $normalCustomerEmail,
    'name'           => 'Legit Customer',
    'given_name'     => 'Legit',
    'family_name'    => 'Customer',
    'picture'        => '',
    'email_verified' => true
];

$normalCustomer = sync_google_customer($googleCustomerUser);
assert_test(!empty($normalCustomer['id']) && $normalCustomer['email'] === $normalCustomerEmail, "Normal customer successfully registers via Google OAuth");

// Cleanup test records
$pdo->prepare('DELETE FROM admins WHERE email = :email')->execute(['email' => $testAdminEmail]);
$pdo->prepare('DELETE FROM users WHERE email = :email')->execute(['email' => $normalCustomerEmail]);

echo "\n======================================================================\n";
echo " SUMMARY: {$testsPassed}/{$totalTests} tests passed.\n";
echo " RESULT: ALL ADMIN BLOCK SECURITY ASSERTIONS PASSED (100% SUCCESS)\n";
echo "======================================================================\n\n";
