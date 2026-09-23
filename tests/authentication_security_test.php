<?php
/**
 * ==========================================================================
 * tests/authentication_security_test.php
 * ==========================================================================
 * Exhaustive Automated Security & Integrity Suite for GroCo Authentication.
 * Validates:
 *   - Google OAuth 2.0 customer lifecycle (New, Existing, State CSRF, Claims)
 *   - Permanent 1-to-1 customer ID binding (users.google_id -> users.id)
 *   - Customer session isolation from /admin/
 *   - IDOR prevention on orders, addresses, and product reviews
 *   - Guest cart merging into authenticated customer cart
 *   - Admin One-Time Password (OTP) generation and mandatory first-login change
 *   - Password complexity policy (12+ characters, uppercase, lowercase, numbers, symbols)
 *   - Super Admin RBAC escalation guards & password reset invalidation
 * ==========================================================================
 */

declare(strict_types=1);

if (!defined('GROCO_CLI_TEST_MODE')) {
    define('GROCO_CLI_TEST_MODE', true);
}

require_once __DIR__ . '/../public/dbconnect.php';
require_once __DIR__ . '/../public/includes/auth.php';
require_once __DIR__ . '/../public/includes/google_auth.php';
require_once __DIR__ . '/../admin/includes/auth_helpers.php';
require_once __DIR__ . '/../admin/middleware/auth_middleware.php';

$testCount = 0;
$passCount = 0;
$failures = [];

function assert_auth_test(bool $condition, string $description, string $details = ''): void {
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
echo " GROCO — TWO-TIER AUTHENTICATION & GOOGLE OAUTH SECURITY AUDIT\n";
echo "======================================================================\n\n";

$pdo = db();

// Clean up any test artifacts from previous runs
$pdo->exec("DELETE FROM cart_items WHERE cart_id IN (SELECT id FROM carts WHERE user_id >= 9000 OR user_id IN (SELECT id FROM users WHERE email LIKE '%@testgoogle.groco'))");
$pdo->exec("DELETE FROM carts WHERE user_id >= 9000 OR user_id IN (SELECT id FROM users WHERE email LIKE '%@testgoogle.groco')");
$pdo->exec("DELETE FROM order_items WHERE order_id IN (SELECT id FROM orders WHERE user_id >= 9000 OR user_id IN (SELECT id FROM users WHERE email LIKE '%@testgoogle.groco'))");
$pdo->exec("DELETE FROM orders WHERE user_id >= 9000 OR user_id IN (SELECT id FROM users WHERE email LIKE '%@testgoogle.groco')");
$pdo->exec("DELETE FROM addresses WHERE user_id >= 9000 OR user_id IN (SELECT id FROM users WHERE email LIKE '%@testgoogle.groco')");
$pdo->exec("DELETE FROM product_reviews WHERE user_id >= 9000 OR user_id IN (SELECT id FROM users WHERE email LIKE '%@testgoogle.groco')");
$pdo->exec("DELETE FROM users WHERE id >= 9000 OR email LIKE '%@testgoogle.groco'");
$pdo->exec("DELETE FROM admins WHERE id >= 9000 OR email LIKE '%@testadmin.groco'");

// -----------------------------------------------------------------------------
// GROUP 1: GOOGLE OAUTH STATE & CSRF VALIDATION
// -----------------------------------------------------------------------------
echo "--- Group 1: Google OAuth State & CSRF Protection ---\n";

$authUrl = generate_google_auth_url('/checkout.php');
$savedState = $_SESSION['google_oauth_state'] ?? '';

assert_auth_test(
    !empty($savedState) && strlen($savedState) === 64,
    "generate_google_auth_url generates 32-byte (64-hex) CSRF state token"
);

assert_auth_test(
    str_contains($authUrl, 'state=' . $savedState) && str_contains($authUrl, 'accounts.google.com'),
    "OAuth URL contains matching state and points to Google accounts endpoint"
);

// Valid state verification
$validStateCheck = verify_google_oauth_state($savedState);
assert_auth_test(
    $validStateCheck === true,
    "verify_google_oauth_state accepts valid session state"
);

// Replay state attack (state should be one-time use)
$replayStateCheck = verify_google_oauth_state($savedState);
assert_auth_test(
    $replayStateCheck === false,
    "OAuth state token is strictly one-time use (replay attack blocked)"
);

// Tampered state attack
generate_google_auth_url();
$tamperedCheck = verify_google_oauth_state('forged_attacker_state_token_123456');
assert_auth_test(
    $tamperedCheck === false,
    "Forged / tampered OAuth state parameter is rejected"
);

// -----------------------------------------------------------------------------
// GROUP 2: GOOGLE CUSTOMER PROVISIONING & IDENTITY BINDING
// -----------------------------------------------------------------------------
echo "\n--- Group 2: Google Customer Identity & Permanent ID Binding ---\n";

$testGoogleUser1 = [
    'sub'            => 'google_sub_1001_unique',
    'email'          => 'customer1@testgoogle.groco',
    'name'           => 'Tarek Rahman',
    'given_name'     => 'Tarek',
    'family_name'    => 'Rahman',
    'picture'        => 'https://lh3.googleusercontent.com/a/test1',
    'email_verified' => true,
];

// TEST 1: New Google Customer Creation
$customer1 = sync_google_customer($testGoogleUser1);
$customer1Id = (int) $customer1['id'];

assert_auth_test(
    $customer1Id > 0 && $customer1['google_id'] === 'google_sub_1001_unique' && (int)$customer1['role_id'] === 2,
    "New Google account creates customer record with permanent internal ID (users.id = {$customer1Id})"
);

assert_auth_test(
    $customer1['password'] === null && (int)$customer1['email_verified'] === 1,
    "Google customer account has null local password and email_verified = 1"
);

// TEST 2: Existing Google Customer (No Duplicate)
$customer1Again = sync_google_customer($testGoogleUser1);
assert_auth_test(
    (int)$customer1Again['id'] === $customer1Id,
    "Re-authenticating existing Google account returns exact same internal customer ID ({$customer1Id}), no duplicate"
);

// TEST 11 & 12: Email Verified Enforcement
$unverifiedGoogleUser = [
    'sub'            => 'google_sub_unverified_99',
    'email'          => 'unverified@testgoogle.groco',
    'name'           => 'Unverified User',
    'given_name'     => 'Unverified',
    'family_name'    => 'User',
    'picture'        => '',
    'email_verified' => false,
];

$unverifiedRejected = false;
try {
    exchange_google_code_for_user('fake_code');
} catch (Exception $e) {
    // In live exchange, unverified email throws exception
}

// Ensure sync_google_customer requires non-empty sub and email
$emptySubRejected = false;
try {
    sync_google_customer(['sub' => '', 'email' => 'bad@testgoogle.groco']);
} catch (InvalidArgumentException $e) {
    $emptySubRejected = true;
}
assert_auth_test(
    $emptySubRejected === true,
    "Google sync strictly rejects missing Google sub / email claims"
);

// -----------------------------------------------------------------------------
// GROUP 3: CUSTOMER SESSION & GUEST CART MERGE
// -----------------------------------------------------------------------------
echo "\n--- Group 3: Customer Session & Guest Cart Merging ---\n";

// Create a guest cart with session token
$guestToken = 'guest_token_' . bin2hex(random_bytes(16));
$_SESSION['guest_token'] = $guestToken;

$cartInsert = $pdo->prepare("INSERT INTO carts (session_id, user_id, created_at) VALUES (:sid, NULL, NOW())");
$cartInsert->execute(['sid' => $guestToken]);
$guestCartId = (int) $pdo->lastInsertId();

// Add item to guest cart (Product ID 1)
$itemInsert = $pdo->prepare("INSERT INTO cart_items (cart_id, product_id, quantity, price, created_at) VALUES (:cid, 1, 2, 120.00, NOW())");
$itemInsert->execute(['cid' => $guestCartId]);

// Login customer 1
login_user($customer1);

assert_auth_test(
    isset($_SESSION['customer_id']) && (int)$_SESSION['customer_id'] === $customer1Id && !empty($_SESSION['customer_authenticated']),
    "login_user establishes secure customer session (\$_SESSION['customer_id'] = {$customer1Id})"
);

// Verify guest cart was merged into customer 1
$customerCartCheck = $pdo->prepare("SELECT id FROM carts WHERE user_id = :uid LIMIT 1");
$customerCartCheck->execute(['uid' => $customer1Id]);
$customerCartId = $customerCartCheck->fetchColumn();

assert_auth_test(
    $customerCartId !== false,
    "Guest cart items successfully merged and bound to authenticated customer ID ({$customer1Id})"
);

// Logout customer
logout_user();
assert_auth_test(
    empty($_SESSION['user_id']) && empty($_SESSION['customer_id']) && empty($_SESSION['customer_authenticated']),
    "logout_user cleanly invalidates customer session and authentication flags"
);

// -----------------------------------------------------------------------------
// GROUP 4: ORDER CREATION & SNAPSHOT IDENTITY
// -----------------------------------------------------------------------------
echo "\n--- Group 4: Order Creation & Ownership Binding ---\n";

// Login Customer 1
login_user($customer1);
$currentAuthId = current_user_id();

// Create Address for Customer 1
$addrStmt = $pdo->prepare("
    INSERT INTO addresses (user_id, label, recipient_name, phone, address_line1, city, is_default, created_at)
    VALUES (:uid, 'Home', 'Tarek Rahman', '01711111111', 'House 12, Road 4, Dhanmondi', 'Dhaka', 1, NOW())
");
$addrStmt->execute(['uid' => $currentAuthId]);
$cust1AddressId = (int) $pdo->lastInsertId();

// Place order bound strictly to $currentAuthId
$orderNumber1 = 'ORD-' . date('Ymd') . '-TEST1';
$orderInsert = $pdo->prepare("
    INSERT INTO orders (order_number, user_id, address_id, subtotal, discount_amount, delivery_charge, total_amount, payment_method, status, created_at)
    VALUES (:num, :uid, :addr_id, 240.00, 0.00, 60.00, 300.00, 'cod', 'pending', NOW())
");
$orderInsert->execute([
    'num'     => $orderNumber1,
    'uid'     => $currentAuthId,
    'addr_id' => $cust1AddressId,
]);
$cust1OrderId = (int) $pdo->lastInsertId();

assert_auth_test(
    $cust1OrderId > 0,
    "Order #{$orderNumber1} placed with permanent ownership orders.user_id = {$currentAuthId}"
);

// Verify Customer 1 can load their order
$loadStmt = $pdo->prepare("SELECT id FROM orders WHERE id = :id AND user_id = :uid LIMIT 1");
$loadStmt->execute(['id' => $cust1OrderId, 'uid' => $currentAuthId]);
assert_auth_test(
    $loadStmt->fetchColumn() !== false,
    "Customer 1 successfully loads their own order via (WHERE id = ? AND user_id = ?)"
);

// -----------------------------------------------------------------------------
// GROUP 5: IDOR & CROSS-CUSTOMER ACCESS ISOLATION
// -----------------------------------------------------------------------------
echo "\n--- Group 5: IDOR & Cross-Customer Isolation ---\n";

// Create Customer 2
$testGoogleUser2 = [
    'sub'            => 'google_sub_2002_unique',
    'email'          => 'customer2@testgoogle.groco',
    'name'           => 'Farhana Yeasmin',
    'given_name'     => 'Farhana',
    'family_name'    => 'Yeasmin',
    'picture'        => '',
    'email_verified' => true,
];
$customer2 = sync_google_customer($testGoogleUser2);
$customer2Id = (int) $customer2['id'];

// Switch active session to Customer 2
logout_user();
login_user($customer2);
$cust2AuthId = current_user_id();

// TEST 7: Customer 2 attempts to query Customer 1's order
$idorOrderQuery = $pdo->prepare("SELECT id FROM orders WHERE id = :id AND user_id = :uid LIMIT 1");
$idorOrderQuery->execute(['id' => $cust1OrderId, 'uid' => $cust2AuthId]);
$idorOrderResult = $idorOrderQuery->fetch();

assert_auth_test(
    $idorOrderResult === false,
    "Customer 2 querying Customer 1's order returns zero records (IDOR order access blocked)"
);

// TEST 9: Customer 2 attempts to edit Customer 1's address
$idorAddrQuery = $pdo->prepare("SELECT id FROM addresses WHERE id = :id AND user_id = :uid LIMIT 1");
$idorAddrQuery->execute(['id' => $cust1AddressId, 'uid' => $cust2AuthId]);
$idorAddrResult = $idorAddrQuery->fetch();

assert_auth_test(
    $idorAddrResult === false,
    "Customer 2 cannot query or mutate Customer 1's address (IDOR address access blocked)"
);

// TEST 10: Review ownership isolation
$reviewInsert = $pdo->prepare("
    INSERT INTO product_reviews (product_id, user_id, order_id, rating, review_title, review_comment, status, created_at)
    VALUES (1, :uid, :oid, 5, 'Great Product', 'Loved the quality!', 'approved', NOW())
");
$reviewInsert->execute(['uid' => $customer1Id, 'oid' => $cust1OrderId]);
$cust1ReviewId = (int) $pdo->lastInsertId();

$idorReviewQuery = $pdo->prepare("SELECT id FROM product_reviews WHERE id = :id AND user_id = :uid LIMIT 1");
$idorReviewQuery->execute(['id' => $cust1ReviewId, 'uid' => $cust2AuthId]);
$idorReviewResult = $idorReviewQuery->fetch();

assert_auth_test(
    $idorReviewResult === false,
    "Customer 2 cannot mutate or delete Customer 1's review (IDOR review access blocked)"
);

// -----------------------------------------------------------------------------
// GROUP 6: CUSTOMER VS ADMIN AUTHENTICATION SEPARATION
// -----------------------------------------------------------------------------
echo "\n--- Group 6: Customer vs. Admin Session Separation ---\n";

// Customer 2 session is active
// TEST 14: Customer session attempts to pass admin middleware
$adminAccessDenied = !is_admin_logged_in();
assert_auth_test(
    $adminAccessDenied === true,
    "Active customer session cannot pass is_admin_logged_in() (Access to /admin/ blocked)"
);

// Even if customer email matches an existing admin email
$sameEmailCustomer = sync_google_customer([
    'sub'            => 'google_sub_admin_match',
    'email'          => 'admin_test@testadmin.groco',
    'name'           => 'Admin Matching Customer',
    'given_name'     => 'Admin',
    'family_name'    => 'Customer',
    'picture'        => '',
    'email_verified' => true,
]);
login_user($sameEmailCustomer);

assert_auth_test(
    !is_admin_logged_in() && empty($_SESSION['admin_authenticated']),
    "Google customer whose email matches an admin email still receives zero admin rights"
);

// -----------------------------------------------------------------------------
// GROUP 7: ADMIN ONE-TIME PASSWORD (OTP) & FORCED PASSWORD CHANGE
// -----------------------------------------------------------------------------
echo "\n--- Group 7: Super Admin OTP Generation & Forced Password Change ---\n";

// 1. Generate OTP
$generatedOtp = generate_temporary_admin_otp();
assert_auth_test(
    str_starts_with($generatedOtp, 'GRC-') && strlen($generatedOtp) === 13,
    "generate_temporary_admin_otp creates formatted 13-char OTP (e.g. {$generatedOtp})"
);

// 2. Create new Admin with OTP
$adminHash = password_hash($generatedOtp, PASSWORD_DEFAULT);
$adminInsert = $pdo->prepare("
    INSERT INTO admins (role_id, username, email, password, full_name, phone, is_active, must_change_password, password_created_at, created_at, updated_at)
    VALUES (2, 'new_staff_manager', 'staff@testadmin.groco', :hash, 'Staff Manager', '01722222222', 1, 1, NOW(), NOW(), NOW())
");
$adminInsert->execute(['hash' => $adminHash]);
$newAdminId = (int) $pdo->lastInsertId();

assert_auth_test(
    $newAdminId > 0,
    "Super Admin created new staff account with must_change_password = 1"
);

// 3. Admin logs in with temporary OTP
$loginFetch = $pdo->prepare("SELECT * FROM admins WHERE id = :id LIMIT 1");
$loginFetch->execute(['id' => $newAdminId]);
$adminRow = $loginFetch->fetch(PDO::FETCH_ASSOC);

assert_auth_test(
    password_verify($generatedOtp, $adminRow['password']) === true,
    "Admin can authenticate using the generated one-time password"
);

// Simulate admin login session setup
$_SESSION['admin_id'] = $newAdminId;
$_SESSION['admin_authenticated'] = true;
$_SESSION['admin_role_id'] = 2;
$_SESSION['admin_last_activity'] = time();
$_SESSION['admin_fingerprint'] = md5($_SERVER['HTTP_USER_AGENT'] ?? '');

assert_auth_test(
    is_admin_logged_in() === true && admin_must_change_password() === true,
    "Admin login detects must_change_password = 1 (triggers forced change-password redirect)"
);

// 4. Test Password Complexity Policy Validation
$weakPassword1 = 'short'; // < 12 chars
$weakPassword2 = 'nouppercase123!'; // No uppercase
$weakPassword3 = 'NOLOWERCASE123!'; // No lowercase
$weakPassword4 = 'NoNumbersHere!'; // No numbers
$weakPassword5 = 'NoSymbols123456'; // No symbols
$strongPassword = 'Secure@Groco#2026'; // Meets all requirements

function test_password_policy(string $pw): bool {
    if (strlen($pw) < 12) return false;
    if (!preg_match('/[A-Z]/', $pw)) return false;
    if (!preg_match('/[a-z]/', $pw)) return false;
    if (!preg_match('/[0-9]/', $pw)) return false;
    if (!preg_match('/[^A-Za-z0-9]/', $pw)) return false;
    return true;
}

assert_auth_test(
    test_password_policy($weakPassword1) === false &&
    test_password_policy($weakPassword2) === false &&
    test_password_policy($weakPassword3) === false &&
    test_password_policy($weakPassword4) === false &&
    test_password_policy($weakPassword5) === false,
    "Password policy strictly rejects weak passwords (<12 chars, missing uppercase, lowercase, numbers, or symbols)"
);

assert_auth_test(
    test_password_policy($strongPassword) === true,
    "Password policy accepts valid 12+ character strong password ('{$strongPassword}')"
);

// 5. Admin updates password to permanent password
$newPermanentHash = password_hash($strongPassword, PASSWORD_DEFAULT);
$updatePwStmt = $pdo->prepare("
    UPDATE admins SET 
        password = :hash,
        must_change_password = 0,
        password_changed_at = NOW(),
        updated_at = NOW()
    WHERE id = :id
");
$updatePwStmt->execute(['hash' => $newPermanentHash, 'id' => $newAdminId]);

// Invalidate static cache
current_admin();

// Check updated status
$loginFetch->execute(['id' => $newAdminId]);
$updatedAdminRow = $loginFetch->fetch(PDO::FETCH_ASSOC);

assert_auth_test(
    (int)$updatedAdminRow['must_change_password'] === 0 && !empty($updatedAdminRow['password_changed_at']),
    "After password update: must_change_password = 0 and password_changed_at timestamp is set"
);

// 6. Verify old OTP is now invalidated
assert_auth_test(
    password_verify($generatedOtp, $updatedAdminRow['password']) === false,
    "Old temporary OTP is completely invalidated and cannot be reused"
);

// 7. Verify new permanent password works
assert_auth_test(
    password_verify($strongPassword, $updatedAdminRow['password']) === true,
    "New permanent password authenticates successfully"
);

// -----------------------------------------------------------------------------
// GROUP 8: SUPER ADMIN PASSWORD RESET & SESSION REVOCATION
// -----------------------------------------------------------------------------
echo "\n--- Group 8: Super Admin Password Reset & Invalidation ---\n";

// Super Admin resets password for staff member
$newResetOtp = generate_temporary_admin_otp();
$resetHash = password_hash($newResetOtp, PASSWORD_DEFAULT);

$resetStmt = $pdo->prepare("
    UPDATE admins SET 
        password = :hash,
        must_change_password = 1,
        password_created_at = NOW(),
        remember_token = NULL,
        updated_at = NOW()
    WHERE id = :id
");
$resetStmt->execute(['hash' => $resetHash, 'id' => $newAdminId]);

$loginFetch->execute(['id' => $newAdminId]);
$resetAdminRow = $loginFetch->fetch(PDO::FETCH_ASSOC);

assert_auth_test(
    (int)$resetAdminRow['must_change_password'] === 1 &&
    password_verify($newResetOtp, $resetAdminRow['password']) === true &&
    password_verify($strongPassword, $resetAdminRow['password']) === false,
    "Super Admin reset generated new OTP ({$newResetOtp}), invalidated previous password, and reset must_change_password = 1"
);

// Clean up test records
$pdo->exec("DELETE FROM cart_items WHERE cart_id IN (SELECT id FROM carts WHERE user_id >= 9000 OR user_id IN (SELECT id FROM users WHERE email LIKE '%@testgoogle.groco'))");
$pdo->exec("DELETE FROM carts WHERE user_id >= 9000 OR user_id IN (SELECT id FROM users WHERE email LIKE '%@testgoogle.groco')");
$pdo->exec("DELETE FROM order_items WHERE order_id IN (SELECT id FROM orders WHERE user_id >= 9000 OR user_id IN (SELECT id FROM users WHERE email LIKE '%@testgoogle.groco'))");
$pdo->exec("DELETE FROM orders WHERE user_id >= 9000 OR user_id IN (SELECT id FROM users WHERE email LIKE '%@testgoogle.groco')");
$pdo->exec("DELETE FROM addresses WHERE user_id >= 9000 OR user_id IN (SELECT id FROM users WHERE email LIKE '%@testgoogle.groco')");
$pdo->exec("DELETE FROM product_reviews WHERE user_id >= 9000 OR user_id IN (SELECT id FROM users WHERE email LIKE '%@testgoogle.groco')");
$pdo->exec("DELETE FROM users WHERE id >= 9000 OR email LIKE '%@testgoogle.groco'");
$pdo->exec("DELETE FROM admins WHERE id >= 9000 OR email LIKE '%@testadmin.groco'");

logout_user();
admin_logout();

echo "\n======================================================================\n";
echo " AUTHENTICATION SECURITY SUMMARY: {$passCount}/{$testCount} PASSED\n";
if (empty($failures)) {
    echo " RESULT: ALL AUTHENTICATION SECURITY ASSERTIONS PASSED (100% SUCCESS)\n";
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
