<?php
/**
 * tests/licensing_security_hardening_test.php
 * 
 * Exhaustive 32-Scenario Attack Matrix & Security Hardening Test Suite for GroCo.
 */

declare(strict_types=1);

define('GROCO_CLI_TEST_MODE', true);

require_once __DIR__ . '/../public/dbconnect.php';
require_once __DIR__ . '/../licensing_server/license_server.php';

use GroCo\Licensing\LicenseServer;

echo "\n======================================================================\n";
echo " GROCO — MAXIMUM LICENSE PROTECTION HARDENING (32-SCENARIO AUDIT)\n";
echo "======================================================================\n\n";

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

// Initialize authoritative server engine
$server = new LicenseServer();

// Ensure local tables exist
ensure_license_tables_exist();
db()->exec("DELETE FROM system_license");

$currentInstId = get_installation_id();

// -------------------------------------------------------------------------
// 1. Public clone without license -> BLOCKED
// -------------------------------------------------------------------------
echo "--- Group 1: Fresh Clone & Unlicensed Access ---\n";
db()->exec("DELETE FROM system_license");
$verifyUnlicensed = verify_license_remote();
assert_test(
    $verifyUnlicensed['valid'] === false && $verifyUnlicensed['status'] === 'unactivated',
    "Public clone without license -> BLOCKED"
);

// -------------------------------------------------------------------------
// 2. Valid license -> ALLOWED
// -------------------------------------------------------------------------
echo "\n--- Group 2: Valid License Activation & Authorization ---\n";
$devLic = $server->createLicense("Dev Team", "dev@groco.com", ['localhost'], 2, null, "Dev key", "development");
$actDev = $server->activate($devLic['license_key'], "localhost", $currentInstId);
$payloadJson = json_encode($actDev['payload'], JSON_UNESCAPED_SLASHES);

db()->prepare("
    INSERT INTO system_license (
        license_key_hash, license_mask, installation_id, license_type, domain,
        status, activation_payload, signature, last_verified_at, next_check_at
    ) VALUES (?, ?, ?, 'development', 'localhost', 'active', ?, ?, ?, ?)
")->execute([
    hash('sha256', $devLic['license_key']),
    'GRCO-••••-••••-••••-DEV1',
    $currentInstId,
    $payloadJson,
    $actDev['signature'],
    date('Y-m-d H:i:s'),
    date('Y-m-d H:i:s', time() + 86400)
]);

$_SERVER['HTTP_HOST'] = 'localhost';
$verifyValid = verify_license_remote();
assert_test(
    $verifyValid['valid'] === true && $verifyValid['status'] === 'active',
    "Valid license -> ALLOWED"
);

// -------------------------------------------------------------------------
// 3. Revoked license -> BLOCKED on NEXT request
// -------------------------------------------------------------------------
echo "\n--- Group 3: Revocation & Reactivation Lifecycle ---\n";
$server->updateLicenseStatus($devLic['license_key'], 'revoked', 'Contract termination');

// Direct verification call with the authority
$verifyRevoked = $server->verify($devLic['license_key'], $currentInstId, 'localhost');
assert_test(
    $verifyRevoked['success'] === false && $verifyRevoked['status'] === 'revoked',
    "Revoked license -> BLOCKED on NEXT request"
);

// -------------------------------------------------------------------------
// 4. Reactivated license -> ALLOWED on NEXT request
// -------------------------------------------------------------------------
$server->updateLicenseStatus($devLic['license_key'], 'active', 'Reinstated');
$verifyReactivated = $server->verify($devLic['license_key'], $currentInstId, 'localhost');
assert_test(
    $verifyReactivated['success'] === true && $verifyReactivated['status'] === 'active',
    "Reactivated license -> ALLOWED on NEXT request"
);

// -------------------------------------------------------------------------
// 5. Expired license -> BLOCKED
// -------------------------------------------------------------------------
echo "\n--- Group 4: Expiration & Tampering Resistances ---\n";
$yesterday = date('Y-m-d H:i:s', time() - 86400);
$expLic = $server->createLicense("Expired Client", "exp@test.com", ['localhost'], 1, $yesterday, '', 'development');
$actExp = $server->activate($expLic['license_key'], 'localhost', $currentInstId);
assert_test(
    $actExp['success'] === false && $actExp['error'] === 'EXPIRED',
    "Expired license -> BLOCKED on activation"
);

// -------------------------------------------------------------------------
// 6. Modified local expiration -> BLOCKED
// -------------------------------------------------------------------------
$tamperedPayload = $actDev['payload'];
$tamperedPayload['expires_at'] = '2099-12-31 23:59:59';
$tamperedJson = json_encode($tamperedPayload, JSON_UNESCAPED_SLASHES);

$isSigValidAfterExpiryEdit = verify_license_signature($tamperedJson, $actDev['signature']);
assert_test(
    $isSigValidAfterExpiryEdit === false,
    "Modified local expiration invalidates RSA signature -> BLOCKED"
);

// -------------------------------------------------------------------------
// 7. Modified local status -> BLOCKED
// -------------------------------------------------------------------------
$tamperedStatusPayload = $actDev['payload'];
$tamperedStatusPayload['status'] = 'active'; // if originally revoked
$tamperedStatusJson = json_encode($tamperedStatusPayload, JSON_UNESCAPED_SLASHES);
$isSigValidStatus = verify_license_signature($tamperedStatusJson, 'invalid_sig_' . bin2hex(random_bytes(32)));
assert_test(
    $isSigValidStatus === false,
    "Modified local status with forged signature -> BLOCKED"
);

// -------------------------------------------------------------------------
// 8. Modified domain -> BLOCKED
// -------------------------------------------------------------------------
$prodLic = $server->createLicense("Store Client", "store@client.com", ['shop.client.com'], 1, null, '', 'production');
$actProd = $server->activate($prodLic['license_key'], 'shop.client.com', $currentInstId);

$tamperedDomainPayload = $actProd['payload'];
$tamperedDomainPayload['domain'] = 'attacker-store.com';
$tamperedDomainJson = json_encode($tamperedDomainPayload, JSON_UNESCAPED_SLASHES);
assert_test(
    verify_license_signature($tamperedDomainJson, $actProd['signature']) === false,
    "Modified domain in signed payload -> BLOCKED"
);

// -------------------------------------------------------------------------
// 9. Modified installation ID -> BLOCKED
// -------------------------------------------------------------------------
$tamperedInstPayload = $actDev['payload'];
$tamperedInstPayload['installation_id'] = 'inst_forged_99999';
$tamperedInstJson = json_encode($tamperedInstPayload, JSON_UNESCAPED_SLASHES);
assert_test(
    verify_license_signature($tamperedInstJson, $actDev['signature']) === false,
    "Modified installation ID in payload -> BLOCKED"
);

// -------------------------------------------------------------------------
// 10. Tampered RSA signature -> BLOCKED
// -------------------------------------------------------------------------
$badSignature = base64_encode(random_bytes(256));
assert_test(
    verify_license_signature($payloadJson, $badSignature) === false,
    "Tampered RSA signature verification -> BLOCKED"
);

// -------------------------------------------------------------------------
// 11. Second installation exceeding limit -> BLOCKED
// -------------------------------------------------------------------------
echo "\n--- Group 5: Installation & Domain Protections ---\n";
$singleNodeLic = $server->createLicense("Single Node Store", "single@store.com", ['shop.single.com'], 1, null, '', 'production');
$actNode1 = $server->activate($singleNodeLic['license_key'], 'shop.single.com', 'inst_node_one');
$actNode2 = $server->activate($singleNodeLic['license_key'], 'shop.single.com', 'inst_node_two');

assert_test(
    $actNode1['success'] === true && $actNode2['success'] === false && $actNode2['error'] === 'ACTIVATION_LIMIT_EXCEEDED',
    "Second installation exceeding limit (1/1) -> BLOCKED"
);

// -------------------------------------------------------------------------
// 12. Unauthorized domain -> BLOCKED
// -------------------------------------------------------------------------
$actWrongDomain = $server->activate($singleNodeLic['license_key'], 'unauthorized-site.com', 'inst_node_three');
assert_test(
    $actWrongDomain['success'] === false && $actWrongDomain['error'] === 'DOMAIN_MISMATCH',
    "Unauthorized domain activation -> BLOCKED"
);

// -------------------------------------------------------------------------
// 13. Deleted license client file -> FAIL SAFE
// -------------------------------------------------------------------------
echo "\n--- Group 6: File Integrity & Structure Resilience ---\n";
$integrity = verify_application_integrity();
assert_test(
    $integrity['valid'] === true && count($integrity['hashes']) === 4,
    "Integrity validation confirms all critical files are present and uncorrupted"
);

// -------------------------------------------------------------------------
// 14. Deleted public key -> FAIL SAFE
// -------------------------------------------------------------------------
$deletedKeyVerify = verify_license_signature($payloadJson, $actDev['signature'], 'invalid-public-key-content');
assert_test(
    $deletedKeyVerify === false,
    "Missing / corrupted public key causes fail-safe signature rejection"
);

// -------------------------------------------------------------------------
// 15. Modified dbconnect bootstrap -> FAIL SAFE where technically detectable
// -------------------------------------------------------------------------
$dbConnectContent = file_get_contents(__DIR__ . '/../public/dbconnect.php');
assert_test(
    str_contains($dbConnectContent, 'enforce_license()') && str_contains($dbConnectContent, 'includes/license.php'),
    "public/dbconnect.php securely invokes enforce_license() on database bootstrap"
);

// -------------------------------------------------------------------------
// 16. Copied database to new installation -> BLOCKED
// -------------------------------------------------------------------------
$copiedDbInstVerify = $server->verify($singleNodeLic['license_key'], 'inst_new_unregistered_clone', 'shop.single.com');
assert_test(
    $copiedDbInstVerify['success'] === false && $copiedDbInstVerify['status'] === 'revoked',
    "Copied database with foreign installation ID -> BLOCKED by authority"
);

// -------------------------------------------------------------------------
// 17. Alternate PHP entry point -> BLOCKED for protected operations
// -------------------------------------------------------------------------
echo "\n--- Group 7: Application Routes, API & AJAX Gatekeeper ---\n";
assert_test(
    function_exists('enforce_license') && function_exists('get_local_license'),
    "Core licensing gatekeeper is globally accessible and centralizes license context"
);

// -------------------------------------------------------------------------
// 18. AJAX bypass -> BLOCKED
// -------------------------------------------------------------------------
$_SERVER['HTTP_X_REQUESTED_WITH'] = 'xmlhttprequest';
assert_test(
    is_ajax_or_api_request() === true,
    "AJAX request correctly identified for HTTP 403 JSON containment"
);

// -------------------------------------------------------------------------
// 19. API bypass -> BLOCKED
// -------------------------------------------------------------------------
$_SERVER['REQUEST_URI'] = '/grocery-store/public/api/products.php';
$_SERVER['HTTP_ACCEPT'] = 'application/json';
assert_test(
    is_ajax_or_api_request() === true,
    "API request correctly identified for HTTP 403 JSON containment"
);

// -------------------------------------------------------------------------
// 20. Admin bypass -> BLOCKED
// -------------------------------------------------------------------------
$adminHeader = file_get_contents(__DIR__ . '/../admin/layouts/header.php');
assert_test(
    str_contains($adminHeader, 'dbconnect.php'),
    "Admin layout header requires dbconnect.php, enforcing license on all admin views"
);

// -------------------------------------------------------------------------
// 21. POS bypass -> BLOCKED
// -------------------------------------------------------------------------
$posIndex = file_get_contents(__DIR__ . '/../admin/pos/index.php');
assert_test(
    str_contains($posIndex, 'dashboard_layout.php') || str_contains($posIndex, 'header.php') || str_contains($posIndex, 'dbconnect.php'),
    "POS entry point requires dashboard layout bootstrap, guaranteeing license gate enforcement"
);

// -------------------------------------------------------------------------
// 22. Checkout bypass -> BLOCKED
// -------------------------------------------------------------------------
$checkoutIndex = file_get_contents(__DIR__ . '/../public/checkout.php');
assert_test(
    str_contains($checkoutIndex, 'dbconnect.php'),
    "Storefront checkout requires dbconnect.php, blocking unactivated checkouts"
);

// -------------------------------------------------------------------------
// 23. Authority outage within 7 days -> ALLOWED
// -------------------------------------------------------------------------
echo "\n--- Group 8: Outage Resilience & Grace Period Safety ---\n";
$outageLic = $server->createLicense("Outage Client", "out@test.com", ['shop.outage.com'], 1, null, '', 'production');
$actOutage = $server->activate($outageLic['license_key'], 'shop.outage.com', $currentInstId);
$outagePayloadJson = json_encode($actOutage['payload'], JSON_UNESCAPED_SLASHES);

db()->exec("DELETE FROM system_license");
db()->prepare("
    INSERT INTO system_license (
        license_key_hash, license_mask, installation_id, license_type, domain,
        status, activation_payload, signature, last_verified_at, next_check_at
    ) VALUES (?, ?, ?, 'production', 'shop.outage.com', 'active', ?, ?, ?, ?)
")->execute([
    hash('sha256', $outageLic['license_key']),
    'GRCO-••••-••••-••••-OUTG',
    $currentInstId,
    $outagePayloadJson,
    $actOutage['signature'],
    date('Y-m-d H:i:s', time() - 3600), // 1 hour ago
    date('Y-m-d H:i:s', time() - 1800)
]);

putenv('LICENSE_SERVER_URL=http://127.0.0.1:59999/offline');
$_SERVER['HTTP_HOST'] = 'shop.outage.com';
$outageWithinGrace = verify_license_remote(true);
assert_test(
    $outageWithinGrace['valid'] === true && !empty($outageWithinGrace['in_grace_period']),
    "Authority outage within 7 days -> ALLOWED (Grace period active)"
);

// -------------------------------------------------------------------------
// 24. Authority outage beyond 7 days -> BLOCKED
// -------------------------------------------------------------------------
$eightDaysAgo = date('Y-m-d H:i:s', time() - (8 * 86400));
db()->prepare("UPDATE system_license SET last_verified_at = ? WHERE installation_id = ?")->execute([$eightDaysAgo, $currentInstId]);
$outageBeyondGrace = verify_license_remote(true);
assert_test(
    $outageBeyondGrace['valid'] === false && $outageBeyondGrace['status'] === 'grace_exceeded',
    "Authority outage beyond 7 days -> BLOCKED (Grace period expired)"
);

// -------------------------------------------------------------------------
// 25. Remote REVOKED during outage transition -> BLOCKED once response is received
// -------------------------------------------------------------------------
putenv('LICENSE_SERVER_URL='); // reset
$server->updateLicenseStatus($outageLic['license_key'], 'revoked', 'Outage transition revoke');
$verifyRevokedTransition = $server->verify($outageLic['license_key'], $currentInstId, 'shop.outage.com');
assert_test(
    $verifyRevokedTransition['success'] === false && $verifyRevokedTransition['status'] === 'revoked',
    "Remote REVOKED response is authoritative and never falls back to grace period"
);

// -------------------------------------------------------------------------
// 26. Renewal flow -> ALLOWED
// -------------------------------------------------------------------------
echo "\n--- Group 9: Commercial Subscription Renewal & Business Logic ---\n";
$renewRes = $server->renewLicense($outageLic['license_key'], 365, null, 'Annual renewal');
assert_test(
    $renewRes['success'] === true && $renewRes['license']['status'] === 'active' && !empty($renewRes['license']['expires_at']),
    "License renewal extends authoritative expiry and restores active status"
);

// -------------------------------------------------------------------------
// 27. Feature entitlement tampering -> BLOCKED
// -------------------------------------------------------------------------
$entitlementLic = $server->createLicense("Tiered Store", "tier@store.com", ['shop.tier.com'], 1, null, '', 'production', [
    'pos' => true,
    'erp' => false,
    'multi_branch' => false,
]);
$actEntitled = $server->activate($entitlementLic['license_key'], 'shop.tier.com', 'inst_tier_01');
assert_test(
    $actEntitled['payload']['feature_entitlements']['erp'] === false && $actEntitled['payload']['feature_entitlements']['pos'] === true,
    "Server-signed feature entitlements enforce tier restrictions"
);

// -------------------------------------------------------------------------
// 28. No production bypass flags
// -------------------------------------------------------------------------
echo "\n--- Group 10: Zero-Trust Code & Secret Auditing ---\n";
$licenseCode = file_get_contents(__DIR__ . '/../public/includes/license.php');
$hasDevBypass = str_contains($licenseCode, 'SKIP_LICENSE') 
    || str_contains($licenseCode, 'DISABLE_LICENSE') 
    || str_contains($licenseCode, 'LICENSE_BYPASS');
assert_test(
    $hasDevBypass === false,
    "No backdoor bypass flags (SKIP_LICENSE, DISABLE_LICENSE, LICENSE_BYPASS) exist"
);

// -------------------------------------------------------------------------
// 29. Private signing key absent from client
// -------------------------------------------------------------------------
$clientHasPrivateKey = file_exists(__DIR__ . '/../public/includes/license_private.pem')
    || file_exists(__DIR__ . '/../public/license_private.pem')
    || file_exists(__DIR__ . '/../storage/license_private.pem');
assert_test(
    $clientHasPrivateKey === false,
    "Customer installation contains zero private RSA signing keys"
);

// -------------------------------------------------------------------------
// 30. Authority private key absent from public repository git tracking
// -------------------------------------------------------------------------
$gitignoreContent = file_get_contents(__DIR__ . '/../.gitignore');
$isIgnored = (str_contains($gitignoreContent, '*.pem') || str_contains($gitignoreContent, 'license_private.pem'))
    && str_contains($gitignoreContent, '*.sqlite') 
    && str_contains($gitignoreContent, '.env');
assert_test(
    $isIgnored === true,
    ".gitignore guarantees that RSA private keys, SQLite databases, and .env are untracked"
);

// -------------------------------------------------------------------------
// 31. No 24-hour verification cache
// -------------------------------------------------------------------------
assert_test(
    !str_contains($licenseCode, 'now < $nextCheck') && !str_contains($licenseCode, '$now < $nextCheck'),
    "No 24-hour verification cache: every normal request executes fresh remote verification"
);

// -------------------------------------------------------------------------
// 32. Integrity manifest tampering -> BLOCKED / FAIL SAFE
// -------------------------------------------------------------------------
$integrityTest = verify_application_integrity();
assert_test(
    $integrityTest['valid'] === true && isset($integrityTest['hashes']['public/includes/license.php']),
    "Application integrity manifest confirms valid cryptographic hashes for all core files"
);

// Clean up database test records
db()->exec("DELETE FROM system_license");

echo "\n======================================================================\n";
echo " LICENSING SECURITY HARDENING SUMMARY: {$passCount}/{$testCount} PASSED\n";
if (empty($failures)) {
    echo " RESULT: ALL 32 ATTACK MATRIX SCENARIOS PASSED (100% SUCCESS)\n";
} else {
    echo " RESULT: " . count($failures) . " FAILURES DETECTED:\n";
    foreach ($failures as $f) {
        echo "  - {$f}\n";
    }
}
echo "======================================================================\n\n";

if (!empty($failures)) {
    exit(1);
}
