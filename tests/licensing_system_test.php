<?php
/**
 * tests/licensing_system_test.php
 * 
 * Exhaustive 28-Scenario Automated Audit Suite for GroCo Licensing System.
 * Verifies public repository protection, mandatory licensing enforcement,
 * development vs production license tiers, cryptographic signatures,
 * domain binding, outage tolerance, and absence of hardcoded bypasses.
 */

declare(strict_types=1);

// Define isolated test mode so dbconnect.php bootstrap does not exit early during test harness setup
if (!defined('GROCO_CLI_TEST_MODE')) {
    define('GROCO_CLI_TEST_MODE', true);
}

require_once __DIR__ . '/../public/dbconnect.php';
require_once __DIR__ . '/../public/includes/license.php';
require_once __DIR__ . '/../licensing_server/license_server.php';

use GroCo\Licensing\LicenseServer;

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
echo " GROCO — MANDATORY LICENSING HARDENING (28-SCENARIO AUDIT SUITE)\n";
echo "======================================================================\n\n";

$server = new LicenseServer();
$publicKey = $server->getPublicKey();

// Clean local client database state
db()->exec("DELETE FROM system_license");
db()->exec("DELETE FROM system_license_logs");

// -------------------------------------------------------------------------
// SCENARIO 1 & 2: Public Repository Clone & No Inherent License
// -------------------------------------------------------------------------
echo "--- Scenario 1 & 2: Public Repository Clone Simulation ---\n";

assert_test(
    file_exists(__DIR__ . '/../public/index.php') && file_exists(__DIR__ . '/../public/dbconnect.php'),
    "Public repository source files are present and can be cloned"
);

$freshCloneLicense = get_local_license();
assert_test(
    $freshCloneLicense === null,
    "Cloning the repository does NOT provide a valid license (system_license is empty)"
);

// -------------------------------------------------------------------------
// SCENARIOS 3, 4, 5, 6, 7, 8: Unactivated Blocking Across Environments
// -------------------------------------------------------------------------
echo "\n--- Scenarios 3 - 8: Unactivated Installation Blocking ---\n";

// Scenario 3: Production without license
putenv('APP_ENV=production');
$_SERVER['HTTP_HOST'] = 'shop.groco.com.bd';
$resProd = verify_license_remote();
assert_test(
    $resProd['valid'] === false && $resProd['status'] === 'unactivated',
    "Production environment without license -> BLOCKED"
);

// Scenario 4: Development without license
putenv('APP_ENV=development');
$resDev = verify_license_remote();
assert_test(
    $resDev['valid'] === false && $resDev['status'] === 'unactivated',
    "Development environment without license -> BLOCKED"
);

// Scenario 5: APP_ENV=development without license
putenv('APP_ENV=development');
$_SERVER['HTTP_HOST'] = 'localhost';
$resDevAppEnv = verify_license_remote();
assert_test(
    $resDevAppEnv['valid'] === false && $resDevAppEnv['status'] === 'unactivated',
    "APP_ENV=development without license -> BLOCKED (No automatic bypass)"
);

// Scenario 6: localhost without license
$_SERVER['HTTP_HOST'] = 'localhost';
$resLocalhost = verify_license_remote();
assert_test(
    $resLocalhost['valid'] === false,
    "localhost without license -> BLOCKED"
);

// Scenario 7: 127.0.0.1 without license
$_SERVER['HTTP_HOST'] = '127.0.0.1';
$resLoopback = verify_license_remote();
assert_test(
    $resLoopback['valid'] === false,
    "127.0.0.1 without license -> BLOCKED"
);

// Scenario 8: *.test domain without license
$_SERVER['HTTP_HOST'] = 'groco-dev.test';
$resDotTest = verify_license_remote();
assert_test(
    $resDotTest['valid'] === false,
    "*.test local domain without license -> BLOCKED"
);

// -------------------------------------------------------------------------
// SCENARIO 9: Valid Development License on Localhost
// -------------------------------------------------------------------------
echo "\n--- Scenario 9: Valid Development License on Localhost ---\n";

$devLic = $server->createLicense("Internal Team", "dev@groco.com", ['localhost', '127.0.0.1'], 2, null, "Dev key", "development");
assert_test(
    $devLic['license_type'] === 'development',
    "Authority generates an authorized DEVELOPMENT license"
);

$actDev = $server->activate($devLic['license_key'], "localhost", get_installation_id());
assert_test(
    $actDev['success'] === true && ($actDev['payload']['license_type'] ?? '') === 'development',
    "Development license successfully activates on localhost with signed payload"
);

// Seed local database with verified development license
$payloadDevJson = json_encode($actDev['payload'], JSON_UNESCAPED_SLASHES);
db()->exec("DELETE FROM system_license");
$stmtDev = db()->prepare("
    INSERT INTO system_license (
        license_key_hash, license_mask, installation_id, license_type, domain,
        status, activation_payload, signature, last_verified_at, next_check_at
    ) VALUES (?, ?, ?, 'development', 'localhost', 'active', ?, ?, ?, ?)
");
$stmtDev->execute([
    hash('sha256', $devLic['license_key']),
    'GRCO-••••-••••-••••-DEV1',
    get_installation_id(),
    $payloadDevJson,
    $actDev['signature'],
    date('Y-m-d H:i:s'),
    date('Y-m-d H:i:s', time() + 86400)
]);

$_SERVER['HTTP_HOST'] = 'localhost';
$verifyDevLocal = verify_license_remote();
assert_test(
    $verifyDevLocal['valid'] === true && $verifyDevLocal['status'] === 'active',
    "Valid development license running on localhost -> ALLOWED"
);

// -------------------------------------------------------------------------
// SCENARIO 10: Valid Production License on Domain
// -------------------------------------------------------------------------
echo "\n--- Scenario 10: Valid Production License on Production Domain ---\n";

$prodLic = $server->createLicense("Retail Client Ltd", "billing@retail.com", ['shop.retail.com'], 1, null, "Prod key", "production");
$actProd = $server->activate($prodLic['license_key'], "shop.retail.com", get_installation_id());
assert_test(
    $actProd['success'] === true && ($actProd['payload']['license_type'] ?? '') === 'production',
    "Production license successfully activates on authorized domain"
);

// Seed local client database with production license
$payloadProdJson = json_encode($actProd['payload'], JSON_UNESCAPED_SLASHES);
db()->exec("DELETE FROM system_license");
$stmtProd = db()->prepare("
    INSERT INTO system_license (
        license_key_hash, license_mask, installation_id, license_type, domain,
        status, activation_payload, signature, last_verified_at, next_check_at
    ) VALUES (?, ?, ?, 'production', 'shop.retail.com', 'active', ?, ?, ?, ?)
");
$stmtProd->execute([
    hash('sha256', $prodLic['license_key']),
    'GRCO-••••-••••-••••-PRD1',
    get_installation_id(),
    $payloadProdJson,
    $actProd['signature'],
    date('Y-m-d H:i:s'),
    date('Y-m-d H:i:s', time() + 86400)
]);

$_SERVER['HTTP_HOST'] = 'shop.retail.com';
$verifyProd = verify_license_remote();
assert_test(
    $verifyProd['valid'] === true && $verifyProd['status'] === 'active',
    "Valid production license on authorized domain -> ALLOWED"
);

// -------------------------------------------------------------------------
// SCENARIO 11: Production License on Wrong Domain
// -------------------------------------------------------------------------
echo "\n--- Scenario 11: Domain Mismatch ---\n";

$_SERVER['HTTP_HOST'] = 'unauthorized-clone.com';
$verifyWrongDomain = verify_license_remote();
assert_test(
    $verifyWrongDomain['valid'] === false && $verifyWrongDomain['status'] === 'domain_mismatch',
    "Production license executed on unauthorized domain -> BLOCKED"
);

// -------------------------------------------------------------------------
// SCENARIO 12 & 13: Expired Development & Production Licenses
// -------------------------------------------------------------------------
echo "\n--- Scenarios 12 & 13: Expired Licenses ---\n";

$yesterday = date('Y-m-d H:i:s', time() - 86400);

$expDevLic = $server->createLicense("Expired Dev", "dev@exp.com", ['localhost'], 1, $yesterday, '', 'development');
$actExpDev = $server->activate($expDevLic['license_key'], 'localhost', 'inst_exp_dev');
assert_test(
    $actExpDev['success'] === false && $actExpDev['error'] === 'EXPIRED',
    "Expired development license -> BLOCKED"
);

$expProdLic = $server->createLicense("Expired Prod", "prod@exp.com", ['prod.com'], 1, $yesterday, '', 'production');
$actExpProd = $server->activate($expProdLic['license_key'], 'prod.com', 'inst_exp_prod');
assert_test(
    $actExpProd['success'] === false && $actExpProd['error'] === 'EXPIRED',
    "Expired production license -> BLOCKED"
);

// -------------------------------------------------------------------------
// SCENARIO 14 & 15: Revoked Development & Production Licenses
// -------------------------------------------------------------------------
echo "\n--- Scenarios 14 & 15: Revoked Licenses ---\n";

// Revoke Dev
$server->updateLicenseStatus($devLic['license_key'], 'revoked', 'Terminated contractor');
$verifyRevDev = $server->verify($devLic['license_key'], 'inst_dev_node_1', 'localhost');
assert_test(
    $verifyRevDev['success'] === false && $verifyRevDev['status'] === 'revoked',
    "Revoked development license -> BLOCKED"
);

// Revoke Prod
$server->updateLicenseStatus($prodLic['license_key'], 'revoked', 'Payment dispute');
$verifyRevProd = $server->verify($prodLic['license_key'], 'inst_prod_01', 'shop.retail.com');
assert_test(
    $verifyRevProd['success'] === false && $verifyRevProd['status'] === 'revoked',
    "Revoked production license -> BLOCKED"
);

// -------------------------------------------------------------------------
// SCENARIO 16: Activation Limit Enforced
// -------------------------------------------------------------------------
echo "\n--- Scenario 16: Activation Limit Enforcement ---\n";

$singleLic = $server->createLicense("Strict Node", "node@strict.com", ['*'], 1, null, '', 'production');
$actSlot1 = $server->activate($singleLic['license_key'], 'node1.com', 'inst_slot_1');
assert_test($actSlot1['success'] === true, "Slot 1/1 consumed successfully");

$actSlot2 = $server->activate($singleLic['license_key'], 'node2.com', 'inst_slot_2');
assert_test(
    $actSlot2['success'] === false && $actSlot2['error'] === 'ACTIVATION_LIMIT_EXCEEDED',
    "Attempt to activate node exceeding activation_limit (1/1) -> BLOCKED"
);

// -------------------------------------------------------------------------
// SCENARIO 17: Installation Deactivation Releases Slot
// -------------------------------------------------------------------------
echo "\n--- Scenario 17: Installation Deactivation ---\n";

$deactRes = $server->deactivate($singleLic['license_key'], 'inst_slot_1');
assert_test($deactRes['success'] === true, "Installation deactivation released node slot");

$actSlot2Retry = $server->activate($singleLic['license_key'], 'node2.com', 'inst_slot_2');
assert_test(
    $actSlot2Retry['success'] === true,
    "Fresh node activation succeeds after slot release"
);

// -------------------------------------------------------------------------
// SCENARIO 18: Tampered RSA Signature Rejection
// -------------------------------------------------------------------------
echo "\n--- Scenario 18: Tampered Cryptographic Signature ---\n";

$corruptSig = base64_encode("attacker_forged_signature_string");
$isSigGood = verify_license_signature($payloadProdJson, $corruptSig, $publicKey);
assert_test(
    $isSigGood === false,
    "Tampered RSA-2048 signature fails cryptographic verification -> BLOCKED"
);

// -------------------------------------------------------------------------
// SCENARIO 19: Modified License Status
// -------------------------------------------------------------------------
echo "\n--- Scenario 19: Modified License Status ---\n";

$tamperedStatus = str_replace('"status":"active"', '"status":"perpetual_free"', $payloadProdJson);
$isStatusGood = verify_license_signature($tamperedStatus, $actProd['signature'], $publicKey);
assert_test(
    $isStatusGood === false,
    "Locally modified license status invalidates RSA signature -> BLOCKED"
);

// -------------------------------------------------------------------------
// SCENARIO 20: Modified Expiration Date
// -------------------------------------------------------------------------
echo "\n--- Scenario 20: Modified Expiration Date ---\n";

$tamperedExpiry = str_replace('"expires_at":null', '"expires_at":"2099-12-31 23:59:59"', $payloadProdJson);
$isExpiryGood = verify_license_signature($tamperedExpiry, $actProd['signature'], $publicKey);
assert_test(
    $isExpiryGood === false,
    "Locally modified expiration date invalidates RSA signature -> BLOCKED"
);

// -------------------------------------------------------------------------
// SCENARIO 21 & 22: License Outage Tolerance & Grace Period Expiration
// -------------------------------------------------------------------------
echo "\n--- Scenarios 21 & 22: Remote Outage Tolerance & Grace Period ---\n";

// Create isolated license for outage tests
$outageLic = $server->createLicense("Outage Store", "out@store.com", ['shop.outage.com'], 1, null, '', 'production');
$freshAct = $server->activate($outageLic['license_key'], 'shop.outage.com', get_installation_id());
$freshPayloadJson = json_encode($freshAct['payload'], JSON_UNESCAPED_SLASHES);

db()->exec("DELETE FROM system_license");
$stmtOutage = db()->prepare("
    INSERT INTO system_license (
        license_key_hash, license_mask, installation_id, license_type, domain,
        status, activation_payload, signature, last_verified_at, next_check_at
    ) VALUES (?, ?, ?, 'production', 'shop.outage.com', 'active', ?, ?, ?, ?)
");

// 2 hours ago (within 7-day grace period, recheck overdue)
$twoHoursAgo = date('Y-m-d H:i:s', time() - 7200);
$overdueRecheck = date('Y-m-d H:i:s', time() - 3600);
$stmtOutage->execute([
    hash('sha256', $outageLic['license_key']),
    'GRCO-••••-••••-••••-OUT1',
    get_installation_id(),
    $freshPayloadJson,
    $freshAct['signature'],
    $twoHoursAgo,
    $overdueRecheck
]);

// Point to dead server port to simulate outage
putenv('LICENSE_SERVER_URL=http://127.0.0.1:58888/offline');
$_SERVER['HTTP_HOST'] = 'shop.outage.com';
$verifyOutageWithinGrace = verify_license_remote(true);
assert_test(
    $verifyOutageWithinGrace['valid'] === true && !empty($verifyOutageWithinGrace['in_grace_period']),
    "Remote server outage within 7-day grace period -> ALLOWED (Zero unexpected downtime)"
);

// Beyond 7-day grace period (8 days offline)
$eightDaysAgo = date('Y-m-d H:i:s', time() - (8 * 86400));
db()->prepare("UPDATE system_license SET last_verified_at = ? WHERE domain = 'shop.outage.com'")->execute([$eightDaysAgo]);
$verifyOutageBeyondGrace = verify_license_remote(true);
assert_test(
    $verifyOutageBeyondGrace['valid'] === false && $verifyOutageBeyondGrace['status'] === 'grace_exceeded',
    "Remote server outage beyond grace period (>7 days) -> BLOCKED"
);

// -------------------------------------------------------------------------
// SCENARIO 23, 24, 25: API, AJAX & Admin Protected Routes
// -------------------------------------------------------------------------
echo "\n--- Scenarios 23, 24, 25: API, AJAX & Admin Endpoint Protection ---\n";

// Clear license to simulate unactivated installation
db()->exec("DELETE FROM system_license");

// Test API detection
$_SERVER['REQUEST_URI'] = '/grocery-store/public/api/cart.php';
$_SERVER['HTTP_ACCEPT'] = 'application/json';
assert_test(
    is_ajax_or_api_request() === true,
    "API direct endpoint request correctly identified for 403 JSON response"
);

// Test AJAX detection
$_SERVER['REQUEST_URI'] = '/grocery-store/public/ajax/add_to_cart.php';
$_SERVER['HTTP_X_REQUESTED_WITH'] = 'xmlhttprequest';
assert_test(
    is_ajax_or_api_request() === true,
    "AJAX request correctly identified for 403 JSON response"
);
unset($_SERVER['REQUEST_URI'], $_SERVER['HTTP_ACCEPT'], $_SERVER['HTTP_X_REQUESTED_WITH']);

// Verify admin protected route calls enforce_license via dbconnect
$adminHeader = file_get_contents(__DIR__ . '/../admin/layouts/header.php');
assert_test(
    strpos($adminHeader, 'dbconnect.php') !== false,
    "Admin layout header requires dbconnect.php, enforcing license check on admin routes"
);

// -------------------------------------------------------------------------
// SCENARIO 26: No Hardcoded Bypass in Source Code
// -------------------------------------------------------------------------
echo "\n--- Scenario 26: Absence of Hardcoded Bypasses ---\n";

$licenseCode = file_get_contents(__DIR__ . '/../public/includes/license.php');

$badPatterns = [
    'is_license_dev_mode',
    'if ($is_localhost) return true',
    'if ($app_env === \'development\') return true',
    'if ($_SERVER[\'SERVER_NAME\'] === \'localhost\') return true',
    '?dev=true',
    '?license_bypass',
    'LICENSE_DISABLED',
    'SKIP_LICENSE',
    'DISABLE_LICENSE'
];

$hasBadPattern = false;
$foundPattern = '';
foreach ($badPatterns as $pattern) {
    if (stripos($licenseCode, $pattern) !== false) {
        $hasBadPattern = true;
        $foundPattern = $pattern;
        break;
    }
}

assert_test(
    !$hasBadPattern,
    "No hardcoded bypass, localhost short-circuit, or bypass query parameter exists in license.php"
);

// -------------------------------------------------------------------------
// SCENARIO 27: No License Disable Environment Variable Exists
// -------------------------------------------------------------------------
echo "\n--- Scenario 27: No License Disable Environment Variables ---\n";

$envExample = file_get_contents(__DIR__ . '/../.env.example');
assert_test(
    strpos($envExample, 'LICENSE_DEV_BYPASS') === false 
    && strpos($envExample, 'SKIP_LICENSE') === false 
    && strpos($envExample, 'DISABLE_LICENSE') === false,
    ".env.example contains zero bypass flags or license disabling variables"
);

// -------------------------------------------------------------------------
// SCENARIO 28: No Production Secrets Committed
// -------------------------------------------------------------------------
echo "\n--- Scenario 28: Git Security & Secret Protection ---\n";

$gitIgnore = file_get_contents(__DIR__ . '/../.gitignore');
assert_test(
    strpos($gitIgnore, 'licensing_server/data/') !== false
    && strpos($gitIgnore, 'licensing_server/*.pem') !== false
    && strpos($gitIgnore, '.env') !== false,
    ".gitignore guarantees that RSA private keys, authority sqlite database, and .env are never committed"
);

// -------------------------------------------------------------------------
// CLEANUP
// -------------------------------------------------------------------------
unset($server);
gc_collect_cycles();
putenv('APP_ENV=development');
putenv('LICENSE_SERVER_URL=');
unset($_SERVER['HTTP_HOST']);

// Clean up all temporary test rows so database remains in a clean unactivated state
db()->exec("DELETE FROM system_license");


// -------------------------------------------------------------------------
// FINAL SUMMARY
// -------------------------------------------------------------------------
echo "\n======================================================================\n";
echo " LICENSING AUDIT TEST SUMMARY: {$passCount}/{$testCount} PASSED\n";
if (empty($failures)) {
    echo " RESULT: ALL 28 MANDATORY HARDENING SCENARIOS PASSED (100% SUCCESS)\n";
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
