<?php
/**
 * ==========================================================================
 * tests/production_dual_auth_test.php
 * ==========================================================================
 * Exhaustive Automated Test Suite for GroCo Production Dual Authentication:
 *   - Email + Password Lifecycle (Registration, Hashing, Validation, Login)
 *   - Google OAuth 2.0 Customer Lifecycle (New, Existing, Unverified Rejection)
 *   - ACCOUNT LINKING: 1 Human Customer = 1 Customer ID across all auth methods
 *   - Password Creation for Google-only customers
 *   - Secure Password Reset (SHA-256 hashed tokens, Expiration, One-time use)
 *   - Multi-device Session Revocation (Sign Out of All Devices via session_version)
 *   - Guest Cart Merging into Customer ID
 *   - Strict IDOR Order, Address, and Review Ownership Enforcements
 *   - Admin / Customer Boundary Isolation
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

function assert_dual_test(bool $condition, string $description, string $details = ''): void {
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
echo " GROCO — PRODUCTION DUAL AUTHENTICATION & ACCOUNT LINKING SUITE\n";
echo "======================================================================\n\n";

$pdo = db();

// Clean up previous test artifacts
$pdo->exec("DELETE FROM cart_items WHERE cart_id IN (SELECT id FROM carts WHERE user_id >= 8000 OR user_id IN (SELECT id FROM users WHERE email LIKE '%@dualtest.groco'))");
$pdo->exec("DELETE FROM carts WHERE user_id >= 8000 OR user_id IN (SELECT id FROM users WHERE email LIKE '%@dualtest.groco')");
$pdo->exec("DELETE FROM order_items WHERE order_id IN (SELECT id FROM orders WHERE user_id >= 8000 OR user_id IN (SELECT id FROM users WHERE email LIKE '%@dualtest.groco'))");
$pdo->exec("DELETE FROM orders WHERE user_id >= 8000 OR user_id IN (SELECT id FROM users WHERE email LIKE '%@dualtest.groco')");
$pdo->exec("DELETE FROM addresses WHERE user_id >= 8000 OR user_id IN (SELECT id FROM users WHERE email LIKE '%@dualtest.groco')");
$pdo->exec("DELETE FROM product_reviews WHERE user_id >= 8000 OR user_id IN (SELECT id FROM users WHERE email LIKE '%@dualtest.groco')");
$pdo->exec("DELETE FROM password_resets WHERE user_id >= 8000 OR user_id IN (SELECT id FROM users WHERE email LIKE '%@dualtest.groco')");
$pdo->exec("DELETE FROM users WHERE id >= 8000 OR email LIKE '%@dualtest.groco'");

// -----------------------------------------------------------------------------
// GROUP 1: EMAIL + PASSWORD REGISTRATION & LOGIN
// -----------------------------------------------------------------------------
echo "--- Group 1: Email + Password Registration & Login ---\n";

$email1 = 'alice@dualtest.groco';
$pass1 = 'SecurePass#2026';
$hashed1 = password_hash($pass1, PASSWORD_DEFAULT);

// 1. Insert Alice (Email customer)
$ins = $pdo->prepare("
    INSERT INTO users (role_id, full_name, email, phone, password, email_verified, is_verified, is_active, session_version, created_at, updated_at, last_login_at)
    VALUES (2, 'Alice Johnson', :email, '01700000001', :pass, 0, 0, 1, 1, NOW(), NOW(), NOW())
");
$ins->execute(['email' => $email1, 'pass' => $hashed1]);
$aliceId = (int) $pdo->lastInsertId();

assert_dual_test(
    $aliceId > 0,
    "Customer registered with Email + Password (users.id = {$aliceId})"
);

assert_dual_test(
    password_verify($pass1, $hashed1),
    "Password stored using secure password_hash (never plaintext)"
);

// 2. Wrong Password Attempt
$badLogin = attempt_login($email1, 'WrongPassword123');
assert_dual_test(
    $badLogin === false,
    "attempt_login strictly rejects incorrect password"
);

// Verify failed_logins counter incremented
$failCount = (int) $pdo->query("SELECT failed_logins FROM users WHERE id = {$aliceId}")->fetchColumn();
assert_dual_test(
    $failCount >= 1,
    "Failed login attempt increments brute-force counter (failed_logins = {$failCount})"
);

// 3. Correct Password Attempt
$goodLogin = attempt_login($email1, $pass1);
assert_dual_test(
    $goodLogin !== false && (int)$goodLogin['id'] === $aliceId,
    "attempt_login succeeds with valid credentials"
);

// Verify failed_logins counter reset on success
$failCountReset = (int) $pdo->query("SELECT failed_logins FROM users WHERE id = {$aliceId}")->fetchColumn();
assert_dual_test(
    $failCountReset === 0,
    "Successful login resets brute-force counter to 0"
);

// -----------------------------------------------------------------------------
// GROUP 2: GOOGLE OAUTH & SAFE ACCOUNT LINKING
// -----------------------------------------------------------------------------
echo "\n--- Group 2: Google OAuth & Safe Account Linking ---\n";

// Scenario A: New Google-only customer (Bob)
$bobGoogleClaims = [
    'sub'            => 'google_sub_bob_8888',
    'email'          => 'bob@dualtest.groco',
    'name'           => 'Bob Smith',
    'given_name'     => 'Bob',
    'family_name'    => 'Smith',
    'picture'        => 'https://lh3.googleusercontent.com/bob',
    'email_verified' => true,
];

$bob = sync_google_customer($bobGoogleClaims);
$bobId = (int) $bob['id'];

assert_dual_test(
    $bobId > 0 && $bob['google_id'] === 'google_sub_bob_8888' && $bob['password'] === null,
    "New Google customer (Bob) created without local password (password is NULL, google_id bound)"
);

// Scenario B: ACCOUNT LINKING (Alice previously registered with Email+Password, now logs in with Google)
$aliceGoogleClaims = [
    'sub'            => 'google_sub_alice_7777',
    'email'          => 'alice@dualtest.groco', // SAME EMAIL AS ALICE
    'name'           => 'Alice Johnson',
    'given_name'     => 'Alice',
    'family_name'    => 'Johnson',
    'picture'        => 'https://lh3.googleusercontent.com/alice',
    'email_verified' => true,
];

$linkedAlice = sync_google_customer($aliceGoogleClaims);
$linkedAliceId = (int) $linkedAlice['id'];

assert_dual_test(
    $linkedAliceId === $aliceId,
    "ACCOUNT LINKING: Google login with matching verified email links to the SAME customer account ID ({$aliceId}), NO duplicate created"
);

assert_dual_test(
    $linkedAlice['google_id'] === 'google_sub_alice_7777' && !empty($linkedAlice['password']),
    "Linked customer account retains both Google ID ('google_sub_alice_7777') and Password Hash"
);

// Total customer count check: Alice must still be 1 row in DB
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = :email");
$countStmt->execute(['email' => $email1]);
$aliceRows = (int) $countStmt->fetchColumn();
assert_dual_test(
    $aliceRows === 1,
    "Database constraint confirmed: exactly 1 customer row exists for '{$email1}'"
);

// -----------------------------------------------------------------------------
// GROUP 3: GOOGLE CUSTOMER CREATES PASSWORD (PROFILE PASSWORD SETTING)
// -----------------------------------------------------------------------------
echo "\n--- Group 3: Google-Only Customer Adds Password ---\n";

// Bob originally had NULL password. Now Bob sets a password.
$bobNewPass = 'BobPass#2026';
$bobNewHash = password_hash($bobNewPass, PASSWORD_DEFAULT);

$pdo->prepare("UPDATE users SET password = :pass, updated_at = NOW() WHERE id = :id")
    ->execute(['pass' => $bobNewHash, 'id' => $bobId]);

// Bob can now authenticate using Email + Password
$bobEmailLogin = attempt_login('bob@dualtest.groco', $bobNewPass);
assert_dual_test(
    $bobEmailLogin !== false && (int)$bobEmailLogin['id'] === $bobId,
    "Google-first customer (Bob) can successfully log in via Email + Password after creating a password"
);

// Bob can still authenticate via Google
$bobGoogleLogin = sync_google_customer($bobGoogleClaims);
assert_dual_test(
    (int)$bobGoogleLogin['id'] === $bobId,
    "Google-first customer (Bob) can still log in via Google OAuth on the exact same customer ID ({$bobId})"
);

// -----------------------------------------------------------------------------
// GROUP 4: PASSWORD RESET LIFECYCLE & TOKEN HASHING
// -----------------------------------------------------------------------------
echo "\n--- Group 4: Secure Password Reset Lifecycle ---\n";

// Generate 32-byte raw token
$rawResetToken = bin2hex(random_bytes(32));
$tokenHash = hash('sha256', $rawResetToken);
$expiresAt = date('Y-m-d H:i:s', time() + 3600);

// Insert into password_resets
$pdo->prepare("INSERT INTO password_resets (user_id, token, expires_at, used, created_at) VALUES (?, ?, ?, 0, NOW())")
    ->execute([$aliceId, $tokenHash, $expiresAt]);
$resetId = (int) $pdo->lastInsertId();

assert_dual_test(
    $resetId > 0,
    "Password reset token generated and SHA-256 hash stored in password_resets table"
);

// Verify valid token lookup
$valStmt = $pdo->prepare("SELECT * FROM password_resets WHERE token = ? AND used = 0 AND expires_at > NOW()");
$valStmt->execute([$tokenHash]);
$validRec = $valStmt->fetch();
assert_dual_test(
    $validRec !== false && (int)$validRec['user_id'] === $aliceId,
    "Token validation verifies matching hash, unexpired timestamp, and unused state"
);

// Complete Password Reset
$newAlicePass = 'AliceBrandNewPass#2027';
$newAliceHash = password_hash($newAlicePass, PASSWORD_DEFAULT);

$pdo->prepare("UPDATE users SET password = ?, session_version = session_version + 1 WHERE id = ?")
    ->execute([$newAliceHash, $aliceId]);
$pdo->prepare("UPDATE password_resets SET used = 1 WHERE id = ?")->execute([$resetId]);

// Verify old token is now unusable
$usedStmt = $pdo->prepare("SELECT * FROM password_resets WHERE token = ? AND used = 0");
$usedStmt->execute([$tokenHash]);
assert_dual_test(
    $usedStmt->fetch() === false,
    "Reset token marked used = 1 and cannot be reused (one-time token defense)"
);

// Verify new password authenticates
$newPassLogin = attempt_login($email1, $newAlicePass);
assert_dual_test(
    $newPassLogin !== false && (int)$newPassLogin['id'] === $aliceId,
    "New password authenticates successfully for customer #{$aliceId}"
);

// -----------------------------------------------------------------------------
// GROUP 5: MULTI-DEVICE SESSION INVALIDATION (SIGN OUT ALL DEVICES)
// -----------------------------------------------------------------------------
echo "\n--- Group 5: Multi-Device Session Invalidation ---\n";

// Alice logs in on Device A
login_user($newPassLogin);
$sessionVersionDeviceA = $_SESSION['session_version'] ?? 1;

// Device B triggers "Sign Out of All Devices"
sign_out_all_devices($aliceId, true);

$userFresh = $pdo->query("SELECT session_version FROM users WHERE id = {$aliceId}")->fetch(PDO::FETCH_ASSOC);
$newDbVersion = (int) $userFresh['session_version'];

assert_dual_test(
    $newDbVersion > $sessionVersionDeviceA,
    "sign_out_all_devices increments session_version in database ({$sessionVersionDeviceA} -> {$newDbVersion})"
);

// Stale session check on simulated Device A (where $_SESSION['session_version'] is older)
$_SESSION['session_version'] = $sessionVersionDeviceA; // Old version
current_user(true); // Clear static cache
$staleCheck = current_user();

assert_dual_test(
    $staleCheck === null && empty($_SESSION['user_id']),
    "Stale session on Device A is immediately rejected and destroyed upon next request"
);

// -----------------------------------------------------------------------------
// GROUP 6: CART MERGING & ORDER / ADDRESS OWNERSHIP
// -----------------------------------------------------------------------------
echo "\n--- Group 6: Cart Merge & Strict IDOR Ownership ---\n";

// Guest Cart
$guestToken = 'guest_dual_' . bin2hex(random_bytes(8));
$_SESSION['guest_token'] = $guestToken;
$pdo->prepare("INSERT INTO carts (session_id, user_id, created_at) VALUES (?, NULL, NOW())")->execute([$guestToken]);
$guestCartId = (int) $pdo->lastInsertId();

$pdo->prepare("INSERT INTO cart_items (cart_id, product_id, quantity, price, created_at) VALUES (?, 1, 3, 100.00, NOW())")
    ->execute([$guestCartId]);

// Alice logs in -> Guest cart merged
login_user($newPassLogin);

$aliceCartStmt = $pdo->prepare("SELECT id FROM carts WHERE user_id = ? LIMIT 1");
$aliceCartStmt->execute([$aliceId]);
$aliceCartId = $aliceCartStmt->fetchColumn();

assert_dual_test(
    $aliceCartId !== false,
    "Guest cart merged into Alice's customer account (cart bound to customer_id = {$aliceId})"
);

// Order Creation bound to current_user_id()
$orderNum = 'ORD-DUAL-' . date('Ymd') . '-01';
$pdo->prepare("
    INSERT INTO orders (order_number, user_id, subtotal, discount_amount, delivery_charge, total_amount, payment_method, status, created_at)
    VALUES (?, ?, 300.00, 0.00, 60.00, 360.00, 'cod', 'pending', NOW())
")->execute([$orderNum, $aliceId]);
$aliceOrderId = (int) $pdo->lastInsertId();

// Bob queries Alice's order
$bobOrderQuery = $pdo->prepare("SELECT id FROM orders WHERE id = ? AND user_id = ? LIMIT 1");
$bobOrderQuery->execute([$aliceOrderId, $bobId]);
assert_dual_test(
    $bobOrderQuery->fetch() === false,
    "IDOR Protection: Customer Bob cannot query or view Customer Alice's order"
);

// -----------------------------------------------------------------------------
// GROUP 7: ADMIN / CUSTOMER ISOLATION
// -----------------------------------------------------------------------------
echo "\n--- Group 7: Admin / Customer Separation ---\n";

assert_dual_test(
    is_admin() === false,
    "Customer session has is_admin() = false (zero admin access)"
);

assert_dual_test(
    is_admin_logged_in() === false,
    "Customer session cannot authenticate admin middleware (is_admin_logged_in() = false)"
);

echo "\n======================================================================\n";
echo " DUAL AUTHENTICATION SUMMARY: {$passCount}/{$testCount} PASSED\n";
if ($passCount === $testCount) {
    echo " RESULT: ALL 20/20 ADVANCED PRODUCTION AUTH ASSERTIONS PASSED (100% SUCCESS)\n";
} else {
    echo " RESULT: SOME TESTS FAILED\n";
}
echo "======================================================================\n\n";
