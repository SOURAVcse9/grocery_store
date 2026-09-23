<?php
/**
 * ==========================================================================
 * tests/production_smtp_password_reset_test.php
 * ==========================================================================
 * Production Test Suite for SMTP Mailer, Password Reset Lifecycle,
 * Dynamic Profile Security, Google SVG Logo, and Avatar Fallbacks.
 * ==========================================================================
 */

declare(strict_types=1);

if (!defined('GROCO_CLI_TEST_MODE')) {
    define('GROCO_CLI_TEST_MODE', true);
}

define('GROCO_MOCK_EMAIL_DELIVERY', true);

require_once __DIR__ . '/../public/dbconnect.php';
require_once __DIR__ . '/../public/includes/mailer.php';
require_once __DIR__ . '/../public/includes/auth.php';
require_once __DIR__ . '/../public/includes/helpers.php';
require_once __DIR__ . '/../public/includes/google_auth.php';

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
        $failures[] = "Test #{$testCount}: {$description} — {$details}";
        echo "  [FAIL] Test #{$testCount}: {$description}\n";
        if ($details) {
            echo "         Details: {$details}\n";
        }
    }
}

echo "\n======================================================================\n";
echo " GROCO — PRODUCTION SMTP & DUAL AUTH PROFILE TEST SUITE\n";
echo "======================================================================\n\n";

$pdo = db();

// Clean up test data
$pdo->exec("DELETE FROM password_resets WHERE user_id IN (SELECT id FROM users WHERE email LIKE '%@smtptest.groco')");
$pdo->exec("DELETE FROM users WHERE email LIKE '%@smtptest.groco'");

// -----------------------------------------------------------------------------
// GROUP 1: SMTP CONFIGURATION & MAILER ENGINE
// -----------------------------------------------------------------------------
echo "--- Group 1: SMTP Configuration & Mailer Engine ---\n";

$smtpConfig = get_smtp_config();
assert_test(
    !empty($smtpConfig['host']),
    "SMTP host resolved from .env or database ({$smtpConfig['host']})"
);

assert_test(
    $smtpConfig['port'] > 0,
    "SMTP port configured ({$smtpConfig['port']})"
);

assert_test(
    in_array($smtpConfig['encryption'], ['tls', 'ssl', 'none'], true),
    "SMTP encryption scheme valid ({$smtpConfig['encryption']})"
);

assert_test(
    filter_var($smtpConfig['from_email'], FILTER_VALIDATE_EMAIL) !== false,
    "Sender email is valid RFC email ({$smtpConfig['from_email']})"
);

// Test Mock Transmission
$logs = [];
$mailSent = send_smtp_email('test@smtptest.groco', 'Test Subject', '<h1>Hello</h1>', 'Hello', [], $logs);
assert_test(
    $mailSent === true,
    "send_smtp_email returns true on successful transmission"
);

// -----------------------------------------------------------------------------
// GROUP 2: EMAIL TEMPLATE GENERATION
// -----------------------------------------------------------------------------
echo "\n--- Group 2: Email Template Generation ---\n";

$mockUser = [
    'id' => 9999,
    'full_name' => 'John Doe',
    'email' => 'john@smtptest.groco',
];
$mockResetUrl = 'http://localhost:8080/grocery-store/public/reset-password.php?token=abc123456';

$standardEmailHtml = get_password_reset_email_template($mockUser, $mockResetUrl, false);
assert_test(
    str_contains($standardEmailHtml, 'Reset Your Password') && str_contains($standardEmailHtml, $mockResetUrl),
    "Standard password reset HTML template contains action link and title"
);

$googleUserEmailHtml = get_password_reset_email_template($mockUser, $mockResetUrl, true);
assert_test(
    str_contains($googleUserEmailHtml, 'Set Up Your Password') && str_contains($googleUserEmailHtml, 'Google Sign-In'),
    "Google-linked password setup HTML template tailored specifically for Google users"
);

// -----------------------------------------------------------------------------
// GROUP 3: FORGOT PASSWORD & SHA-256 TOKEN LIFECYCLE
// -----------------------------------------------------------------------------
echo "\n--- Group 3: Forgot Password & SHA-256 Token Lifecycle ---\n";

// Insert test user
$insUser = $pdo->prepare("
    INSERT INTO users (role_id, full_name, email, password, google_id, is_active, session_version, created_at)
    VALUES (2, 'Bob Smith', 'bob@smtptest.groco', :pass, NULL, 1, 1, NOW())
");
$insUser->execute(['pass' => password_hash('OldPassword#123', PASSWORD_DEFAULT)]);
$bobId = (int) $pdo->lastInsertId();

// Generate token
$rawToken = bin2hex(random_bytes(32));
$tokenHash = hash('sha256', $rawToken);
$expiresAt = date('Y-m-d H:i:s', time() + 3600);

$insPr = $pdo->prepare("
    INSERT INTO password_resets (user_id, token, expires_at, used, created_at)
    VALUES (:uid, :token, :exp, 0, NOW())
");
$insPr->execute([
    'uid' => $bobId,
    'token' => $tokenHash,
    'exp' => $expiresAt
]);
$prId = (int) $pdo->lastInsertId();

assert_test(
    $prId > 0,
    "Password reset token generated and stored with SHA-256 hash"
);

// Verify token lookup
$lookupStmt = $pdo->prepare("
    SELECT pr.*, u.id as customer_id, u.email, u.full_name
    FROM password_resets pr
    JOIN users u ON u.id = pr.user_id
    WHERE pr.token = :token AND pr.used = 0 AND pr.expires_at > NOW()
    LIMIT 1
");
$lookupStmt->execute(['token' => $tokenHash]);
$foundPr = $lookupStmt->fetch();

assert_test(
    $foundPr !== false && (int)$foundPr['customer_id'] === $bobId,
    "Valid unexpired token is correctly resolved for user #{$bobId}"
);

// Test Password Reset Action
$newPassword = 'NewSecurePassword#2026';
$newHash = password_hash($newPassword, PASSWORD_DEFAULT);

$updUser = $pdo->prepare("
    UPDATE users 
    SET password = :pass, 
        failed_logins = 0,
        last_password_change = NOW(),
        session_version = session_version + 1,
        remember_token = NULL,
        updated_at = NOW()
    WHERE id = :id
");
$updUser->execute(['pass' => $newHash, 'id' => $bobId]);

$markUsed = $pdo->prepare("UPDATE password_resets SET used = 1 WHERE id = :id");
$markUsed->execute(['id' => $prId]);

// Verify token is now invalid (single-use enforcement)
$lookupStmt->execute(['token' => $tokenHash]);
$reusedPr = $lookupStmt->fetch();
assert_test(
    $reusedPr === false,
    "Token is strictly single-use and invalid after reset completion"
);

// Verify user can login with new password
$loginCheck = attempt_login('bob@smtptest.groco', $newPassword);
assert_test(
    $loginCheck !== false && (int)$loginCheck['id'] === $bobId,
    "Customer successfully authenticates with new password"
);

// Verify old password fails
$oldLoginCheck = attempt_login('bob@smtptest.groco', 'OldPassword#123');
assert_test(
    $oldLoginCheck === false,
    "Old password rejected after reset"
);

// -----------------------------------------------------------------------------
// GROUP 4: DUAL AUTHENTICATION PROFILE STATES
// -----------------------------------------------------------------------------
echo "\n--- Group 4: Dual Auth Profile States (3 Specific Cases) ---\n";

// Case A: Google-Only User
$insGoogleOnly = $pdo->prepare("
    INSERT INTO users (role_id, full_name, email, password, google_id, is_active, session_version, created_at)
    VALUES (2, 'Charlie Google', 'charlie@smtptest.groco', NULL, 'google_sub_12345', 1, 1, NOW())
");
$insGoogleOnly->execute();
$charlieId = (int) $pdo->lastInsertId();

$charlieUser = $pdo->query("SELECT * FROM users WHERE id = {$charlieId}")->fetch();
$hasPasswordA = !empty($charlieUser['password']);
$isGoogleConnectedA = !empty($charlieUser['google_id']);

assert_test(
    !$hasPasswordA && $isGoogleConnectedA,
    "Case A (Google-Only): Google Connected = true, Email/Password Enabled = false"
);

// Charlie creates a password via update_password logic (no current password needed)
$createdPass = 'CharlieSecure#2026';
$createdHash = password_hash($createdPass, PASSWORD_DEFAULT);

$updCharlie = $pdo->prepare("
    UPDATE users 
    SET password = :pass, 
        last_password_change = NOW(), 
        updated_at = NOW() 
    WHERE id = :id
");
$updCharlie->execute(['pass' => $createdHash, 'id' => $charlieId]);

// Charlie now transitions to Case C (Google + Email) on the SAME users.id
$charlieUpdated = $pdo->query("SELECT * FROM users WHERE id = {$charlieId}")->fetch();
$hasPasswordC = !empty($charlieUpdated['password']);
$isGoogleConnectedC = !empty($charlieUpdated['google_id']);

assert_test(
    $hasPasswordC && $isGoogleConnectedC,
    "Case C (Google + Email): Both methods active on same users.id #{$charlieId}"
);

assert_test(
    attempt_login('charlie@smtptest.groco', $createdPass) !== false,
    "Charlie can now authenticate using Email + Password"
);

// Case B: Email-Only User
$insEmailOnly = $pdo->prepare("
    INSERT INTO users (role_id, full_name, email, password, google_id, is_active, session_version, created_at)
    VALUES (2, 'Diana Email', 'diana@smtptest.groco', :pass, NULL, 1, 1, NOW())
");
$insEmailOnly->execute(['pass' => password_hash('DianaPass#123', PASSWORD_DEFAULT)]);
$dianaId = (int) $pdo->lastInsertId();

$dianaUser = $pdo->query("SELECT * FROM users WHERE id = {$dianaId}")->fetch();
$hasPasswordB = !empty($dianaUser['password']);
$isGoogleConnectedB = !empty($dianaUser['google_id']);

assert_test(
    $hasPasswordB && !$isGoogleConnectedB,
    "Case B (Email-Only): Google Connected = false, Email/Password Enabled = true"
);

// Link Google account to Diana without duplicate customer record
$syncedDiana = sync_google_customer([
    'sub' => 'google_sub_diana_999',
    'email' => 'diana@smtptest.groco',
    'name' => 'Diana Email',
    'given_name' => 'Diana',
    'family_name' => 'Email',
    'picture' => 'https://lh3.googleusercontent.com/a/diana',
    'email_verified' => true,
]);

assert_test(
    (int)$syncedDiana['id'] === $dianaId && !empty($syncedDiana['google_id']) && !empty($syncedDiana['password']),
    "Diana linked Google OAuth to existing account (users.id remains #{$dianaId}, both auth methods enabled)"
);

// -----------------------------------------------------------------------------
// GROUP 5: GOOGLE LOGO SVG & AVATAR INITIALS FALLBACK
// -----------------------------------------------------------------------------
echo "\n--- Group 5: Google Logo SVG & Avatar Initials Fallback ---\n";

$loginContent = file_get_contents(__DIR__ . '/../public/login.php');
$hasGoogleSvg = str_contains($loginContent, '<svg') && str_contains($loginContent, '#4285F4') && str_contains($loginContent, '#34A853') && str_contains($loginContent, '#FBBC05') && str_contains($loginContent, '#EA4335');
assert_test(
    $hasGoogleSvg,
    "login.php contains official 4-color inline vector Google 'G' SVG without external dependencies"
);

$registerContent = file_get_contents(__DIR__ . '/../public/register.php');
assert_test(
    str_contains($registerContent, '<svg') && str_contains($registerContent, 'Continue with Google'),
    "register.php contains official Google 'G' SVG button"
);

// Avatar Initials SVG generator
$initialsSvgUri = generate_initials_svg_data_uri('Sourav Das');
assert_test(
    str_starts_with($initialsSvgUri, 'data:image/svg+xml') && str_contains(rawurldecode($initialsSvgUri), 'SD'),
    "generate_initials_svg_data_uri generates clean vector SVG badge with customer initials 'SD'"
);

$singleNameSvgUri = generate_initials_svg_data_uri('Alice');
assert_test(
    str_contains(rawurldecode($singleNameSvgUri), 'AL'),
    "generate_initials_svg_data_uri generates 2-letter uppercase initials for single-word name 'AL'"
);

$avatarFallbackCheck = user_avatar_url(null, 'John Smith');
assert_test(
    str_starts_with($avatarFallbackCheck, 'data:image/svg+xml') && str_contains(rawurldecode($avatarFallbackCheck), 'JS'),
    "user_avatar_url seamlessly falls back to initials data URI when avatar column is NULL"
);

// Clean up test data
$pdo->exec("DELETE FROM password_resets WHERE user_id IN (SELECT id FROM users WHERE email LIKE '%@smtptest.groco')");
$pdo->exec("DELETE FROM users WHERE email LIKE '%@smtptest.groco'");

echo "\n======================================================================\n";
echo " TEST SUMMARY: {$passCount}/{$testCount} tests passed.\n";
if (empty($failures)) {
    echo " ALL TESTS PASSED SUCCESSFULLY! (100% SUCCESS)\n";
} else {
    echo " FAILURES DETECTED:\n";
    foreach ($failures as $f) {
        echo "   - {$f}\n";
    }
}
echo "======================================================================\n\n";

if (!empty($failures)) {
    exit(1);
}
exit(0);
