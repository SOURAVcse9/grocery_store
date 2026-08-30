<?php
/**
 * public/includes/license.php
 * 
 * Central Licensing Engine & Middleware for GroCo Platform.
 * Enforces production software activation, remote cryptographic verification,
 * domain binding, offline grace periods, and safe local development mode.
 */

declare(strict_types=1);

// Built-in GroCo Licensing Public Verification Key (RSA 2048-bit)
// Used to cryptographically verify responses from the authoritative licensing server.
if (!defined('GROCO_DEFAULT_PUBLIC_KEY')) {
    define('GROCO_DEFAULT_PUBLIC_KEY', "-----BEGIN PUBLIC KEY-----\n" .
        "MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEApXmfOeYanwroC2shy/2W\n" .
        "kL37UmEuhGOMshRayf/WRdBe+4x2stfsp+aVcLCrUd89eTPlahuhKeyrG9i8a2RL\n" .
        "tHFXKVfHNigGwDNgtXNzNTx1RmuEjsyeI06RoxEKPuCrF3dmKlCDjQ9UKFAoUO3K\n" .
        "fxGncq2cwZX3Ah21hVYXXWuLAV1b17i34/wIG47CFqsnonUA4AQ7bdBMlBZaG/6a\n" .
        "XWuESSDJQ07UA3pu2WwLRsQ39bWFEzs397ib/sI70EeSfXutmEJQKMtc1VGqCPw4\n" .
        "Symm++Ew8KoqLHHF+RVWQ7xNQAgzkmTS2hzkZh+4yglUZ3n3fjUick+erJqhuvEU\n" .
        "QQIDAQAB\n" .
        "-----END PUBLIC KEY-----");
}

defined('ROOT_PATH') || define('ROOT_PATH', dirname(__DIR__, 2));

/**
 * Retrieve a licensing configuration parameter from environment or defaults.
 */
function license_config(string $key, $default = null)
{
    $val = getenv($key);
    if ($val !== false && $val !== '') {
        return $val;
    }
    if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
        return $_ENV[$key];
    }
    return $default;
}

/**
 * Normalize domain names (removes scheme, port, query, and www. prefix).
 */
function normalize_license_domain(string $domain): string
{
    $d = trim(strtolower($domain));
    $d = preg_replace('#^https?://#i', '', $d);
    $parts = explode('/', $d);
    $hostWithPort = $parts[0];
    $hostParts = explode(':', $hostWithPort);
    $host = $hostParts[0];
    return preg_replace('/^www\./i', '', $host);
}

/**
 * Detect the current host / domain from server environment.
 */
function get_current_domain(): string
{
    if (!empty($_SERVER['HTTP_HOST'])) {
        return normalize_license_domain((string)$_SERVER['HTTP_HOST']);
    }
    if (!empty($_SERVER['SERVER_NAME'])) {
        return normalize_license_domain((string)$_SERVER['SERVER_NAME']);
    }
    return 'localhost';
}

/**
 * Generate a deterministic installation ID unique to this machine and path.
 */
function get_installation_id(): string
{
    $machineIdFile = ROOT_PATH . '/storage/install_id.key';
    if (file_exists($machineIdFile)) {
        $id = trim((string)file_get_contents($machineIdFile));
        if (!empty($id)) {
            return $id;
        }
    }

    $raw = php_uname() . '_' . ROOT_PATH . '_' . (getenv('COMPUTERNAME') ?: getenv('HOSTNAME') ?: 'groco_node');
    $id = 'inst_' . substr(hash('sha256', $raw), 0, 32);

    @file_put_contents($machineIdFile, $id);
    return $id;
}

/**
 * Check if a domain is a local loopback or local development domain.
 */
function is_local_domain(string $domain): bool
{
    $d = normalize_license_domain($domain);
    if (in_array($d, ['localhost', '127.0.0.1', '::1'], true)) {
        return true;
    }
    if (str_ends_with($d, '.test') || str_ends_with($d, '.local')) {
        return true;
    }
    return false;
}

/**
 * Verify RSA-SHA256 signature of canonical payload.
 */
function verify_license_signature(string $payloadJson, string $base64Signature, ?string $publicKey = null): bool
{
    $key = $publicKey ?: license_config('LICENSE_PUBLIC_KEY', GROCO_DEFAULT_PUBLIC_KEY);
    $pubKeyRes = openssl_pkey_get_public($key);
    if (!$pubKeyRes) {
        return false;
    }

    $rawSignature = base64_decode($base64Signature);
    $result = openssl_verify($payloadJson, $rawSignature, $pubKeyRes, OPENSSL_ALGO_SHA256);
    return ($result === 1);
}

/**
 * Ensure database license tables exist (auto-migration on fresh clone).
 */
function ensure_license_tables_exist(): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;
    try {
        db()->exec("
            CREATE TABLE IF NOT EXISTS system_license (
                id INT AUTO_INCREMENT PRIMARY KEY,
                license_key_hash VARCHAR(64) NOT NULL,
                license_mask VARCHAR(32) NOT NULL,
                installation_id VARCHAR(64) NOT NULL,
                license_type ENUM('development', 'production', 'trial') NOT NULL DEFAULT 'production',
                domain VARCHAR(255) NOT NULL,
                customer_email VARCHAR(255) NULL,
                status ENUM('active', 'suspended', 'expired', 'revoked') NOT NULL DEFAULT 'active',
                activation_payload LONGTEXT NOT NULL,
                signature LONGTEXT NOT NULL,
                last_verified_at TIMESTAMP NULL DEFAULT NULL,
                next_check_at TIMESTAMP NULL DEFAULT NULL,
                expires_at TIMESTAMP NULL DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_license_status (status),
                INDEX idx_license_domain (domain),
                INDEX idx_license_installation (installation_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS system_license_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                event_type VARCHAR(50) NOT NULL,
                message VARCHAR(255) NOT NULL,
                details TEXT NULL,
                ip_address VARCHAR(45) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_license_event (event_type),
                INDEX idx_license_log_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    } catch (\Throwable $e) {
        // Table existence fallback
    }
}

/**
 * Retrieve the active local installation license record strictly bound to this installation.
 */
function get_local_license(?string $installationId = null): ?array
{
    ensure_license_tables_exist();
    $instId = $installationId ?: get_installation_id();

    try {
        $stmt = db()->prepare("SELECT * FROM system_license WHERE installation_id = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$instId]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        // Verify cryptographic signature of the stored activation payload
        $isValidSig = verify_license_signature($row['activation_payload'], $row['signature']);
        $row['is_signature_valid'] = $isValidSig;
        $row['payload'] = json_decode($row['activation_payload'], true) ?: [];

        return $row;
    } catch (PDOException $e) {
        return null;
    }
}

/**
 * Log a licensing event to the database audit trail.
 */
function log_license_event(string $eventType, string $message, ?string $details = null): void
{
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? (php_sapi_name() === 'cli' ? 'CLI' : '127.0.0.1');
        $stmt = db()->prepare("
            INSERT INTO system_license_logs (event_type, message, details, ip_address)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$eventType, substr($message, 0, 250), $details, $ip]);
    } catch (PDOException $e) {
        error_log("[LICENSING LOG ERROR] " . $e->getMessage());
    }
}

/**
 * Resolve the authoritative licensing server API endpoint.
 */
function get_licensing_server_url(): string
{
    $envUrl = getenv('LICENSE_SERVER_URL');
    if ($envUrl) {
        return $envUrl;
    }
    return license_config('LICENSE_SERVER_URL', 'http://localhost:8080/grocery-store/licensing_server/api.php');
}

/**
 * Submit an activation request to the authoritative licensing server.
 */
function activate_license_remote(string $licenseKey, ?string $domain = null, ?string $email = null): array
{
    $licenseKey = trim(strtoupper($licenseKey));
    $domain = $domain ? normalize_license_domain($domain) : get_current_domain();
    $installationId = get_installation_id();
    $serverUrl = get_licensing_server_url();

    $postData = [
        'action' => 'activate',
        'license_key' => $licenseKey,
        'domain' => $domain,
        'installation_id' => $installationId,
        'email' => $email,
        'timestamp' => time(),
        'nonce' => bin2hex(random_bytes(16)),
    ];

    $ch = curl_init($serverUrl . '?action=activate');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 12);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 6);

    $rawResponse = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($rawResponse === false) {
        log_license_event('ACTIVATION_FAILED', 'License server connection failed', $curlErr);
        return [
            'success' => false,
            'error' => 'CONNECTION_FAILED',
            'message' => 'Unable to connect to the GroCo licensing server. Please check your internet connection or try again later.',
        ];
    }

    $res = json_decode($rawResponse, true);
    if (!is_array($res) || !isset($res['success'])) {
        log_license_event('ACTIVATION_FAILED', 'Invalid JSON from licensing server', substr($rawResponse, 0, 300));
        return [
            'success' => false,
            'error' => 'INVALID_RESPONSE',
            'message' => 'The licensing server returned an unrecognized response format.',
        ];
    }

    if (!$res['success']) {
        log_license_event('ACTIVATION_REJECTED', $res['message'] ?? 'Activation rejected by licensing server', $res['error'] ?? 'UNKNOWN');
        return [
            'success' => false,
            'error' => $res['error'] ?? 'REJECTED',
            'message' => $res['message'] ?? 'License activation was declined by the authority server.',
        ];
    }

    // Verify cryptographic signature from server
    $payloadJson = json_encode($res['payload'], JSON_UNESCAPED_SLASHES);
    $signature = $res['signature'] ?? '';

    if (!verify_license_signature($payloadJson, $signature)) {
        log_license_event('ACTIVATION_TAMPERED', 'Cryptographic signature verification failed on activation response');
        return [
            'success' => false,
            'error' => 'TAMPERED_RESPONSE',
            'message' => 'Cryptographic signature verification failed. The activation response could not be verified against the official public key.',
        ];
    }

    // Save activation locally in database
    $keyHash = hash('sha256', $licenseKey);
    $mask = substr($licenseKey, 0, 5) . '••••-••••-••••-' . substr($licenseKey, -4);
    $licenseType = $res['payload']['license_type'] ?? 'production';
    $now = date('Y-m-d H:i:s');
    $nextCheck = date('Y-m-d H:i:s', time() + 86400); // 24 hours
    $expiresAt = !empty($res['payload']['expires_at']) ? $res['payload']['expires_at'] : null;

    db()->beginTransaction();
    try {
        $stmtDel = db()->prepare("DELETE FROM system_license WHERE installation_id = ?");
        $stmtDel->execute([$installationId]);
        $stmt = db()->prepare("
            INSERT INTO system_license (
                license_key_hash, license_mask, installation_id, license_type, domain, customer_email,
                status, activation_payload, signature, last_verified_at, next_check_at, expires_at
            ) VALUES (?, ?, ?, ?, ?, ?, 'active', ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $keyHash,
            $mask,
            $installationId,
            $licenseType,
            $domain,
            $email ?: ($res['payload']['customer_email'] ?? null),
            $payloadJson,
            $signature,
            $now,
            $nextCheck,
            $expiresAt,
        ]);
        db()->commit();

        log_license_event('ACTIVATION_SUCCESS', "Installation activated successfully for {$licenseType} license {$mask}");

        return [
            'success' => true,
            'message' => 'GroCo installation activated successfully!',
            'license_mask' => $mask,
            'license_type' => $licenseType,
            'domain' => $domain,
            'expires_at' => $expiresAt,
        ];
    } catch (PDOException $e) {
        db()->rollBack();
        log_license_event('ACTIVATION_SAVE_ERROR', 'Failed to store license record', $e->getMessage());
        return [
            'success' => false,
            'error' => 'DATABASE_ERROR',
            'message' => 'Failed to save local activation record to database: ' . $e->getMessage(),
        ];
    }
}

/**
 * Periodically re-verify the active license against the remote licensing server.
 */
function verify_license_remote(bool $force = false): array
{
    $currentInstId = get_installation_id();
    $lic = get_local_license($currentInstId);
    if (!$lic) {
        return ['valid' => false, 'status' => 'unactivated', 'reason' => 'No active license found for this installation'];
    }

    // 1. Strict Node Identity verification
    if (($lic['installation_id'] ?? '') !== $currentInstId) {
        log_license_event('NODE_MISMATCH', "License installed for node {$lic['installation_id']}, executed on {$currentInstId}");
        return ['valid' => false, 'status' => 'unactivated', 'reason' => 'Installation ID mismatch'];
    }

    // 2. Cryptographic signature check
    if (!$lic['is_signature_valid']) {
        log_license_event('TAMPER_DETECTED', 'Stored license signature verification failed');
        return ['valid' => false, 'status' => 'tampered', 'reason' => 'Local license data has been altered'];
    }

    // 3. Cryptographic payload node binding check
    $payloadInstId = $lic['payload']['installation_id'] ?? '';
    if ($payloadInstId !== $currentInstId) {
        log_license_event('TAMPER_DETECTED', "Signed payload installation mismatch: expected {$currentInstId}, got {$payloadInstId}");
        return ['valid' => false, 'status' => 'tampered', 'reason' => 'Cryptographic signature is not valid for this installation'];
    }

    $currentDomain = get_current_domain();
    $licenseType = $lic['license_type'] ?? ($lic['payload']['license_type'] ?? 'production');

    if ($licenseType === 'development') {
        if (!is_local_domain($currentDomain)) {
            log_license_event('DEV_ON_PRODUCTION', "Development license attempted on public domain {$currentDomain}");
            return ['valid' => false, 'status' => 'domain_mismatch', 'reason' => "Development license is restricted to local development environments (localhost, 127.0.0.1, *.test). Domain '{$currentDomain}' is not a local host."];
        }
    } else {
        // Production license
        if (is_local_domain($currentDomain) && normalize_license_domain($lic['domain']) !== $currentDomain) {
            log_license_event('PROD_ON_LOCALHOST', "Production license attempted on local development domain {$currentDomain}");
            return ['valid' => false, 'status' => 'domain_mismatch', 'reason' => 'Production license cannot be used on local development environment without authorization. Please activate a Development license.'];
        }

        if (normalize_license_domain($lic['domain']) !== $currentDomain) {
            log_license_event('DOMAIN_MISMATCH', "Licensed for {$lic['domain']}, running on {$currentDomain}");
            return ['valid' => false, 'status' => 'domain_mismatch', 'reason' => "License is bound to {$lic['domain']}"];
        }
    }

    // Check expiration date
    if (!empty($lic['expires_at']) && strtotime($lic['expires_at']) < time()) {
        db()->prepare("UPDATE system_license SET status = 'expired' WHERE id = ?")->execute([$lic['id']]);
        log_license_event('LICENSE_EXPIRED', "License {$lic['license_mask']} has expired");
        return ['valid' => false, 'status' => 'expired', 'reason' => 'License expired on ' . $lic['expires_at']];
    }

    $now = time();
    $lastVerified = strtotime($lic['last_verified_at'] ?: '1970-01-01');
    $gracePeriodSecs = (int)license_config('LICENSE_GRACE_PERIOD_DAYS', 7) * 86400;

    // Direct, immediate remote verification handshake with licensing authority on every request.
    // The authority is the single source of truth for revocation, suspension, and expiration.
    $serverUrl = get_licensing_server_url();
    $payloadData = $lic['payload'] ?? [];
    $rawKey = $payloadData['license_key'] ?? '';

    if (empty($rawKey)) {
        return ['valid' => false, 'status' => 'invalid_state', 'reason' => 'Missing license key in stored payload'];
    }

    $postData = [
        'action' => 'verify',
        'license_key' => $rawKey,
        'installation_id' => $lic['installation_id'],
        'domain' => $currentDomain,
        'timestamp' => $now,
        'nonce' => bin2hex(random_bytes(16)),
    ];

    $ch = curl_init($serverUrl . '?action=verify');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 4);

    $rawResponse = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Handle temporary remote server outage (Network error, HTTP 500/502/503/504)
    if ($rawResponse === false || $httpCode >= 500) {
        $elapsedSinceVerified = $now - $lastVerified;
        if ($elapsedSinceVerified <= $gracePeriodSecs && $lic['status'] === 'active') {
            $daysLeft = ceil(($gracePeriodSecs - $elapsedSinceVerified) / 86400);
            log_license_event('GRACE_ACTIVE', "Licensing server unreachable; continuing in grace period ({$daysLeft} days left)");
            return [
                'valid' => true,
                'status' => 'active',
                'in_grace_period' => true,
                'grace_days_remaining' => (int)$daysLeft,
                'license' => $lic,
            ];
        }

        // Grace period expired without remote verification
        log_license_event('GRACE_EXCEEDED', "Grace period exceeded without successful licensing server handshake");
        return [
            'valid' => false,
            'status' => 'grace_exceeded',
            'reason' => 'Licensing server re-verification overdue and offline grace period has expired.',
            'license' => $lic,
        ];
    }

    $res = json_decode($rawResponse, true);
    if (!is_array($res) || !isset($res['payload'])) {
        return ['valid' => false, 'status' => 'invalid_response', 'reason' => 'Invalid server response'];
    }

    // Verify cryptographic signature from server
    $payloadJson = json_encode($res['payload'], JSON_UNESCAPED_SLASHES);
    if (!verify_license_signature($payloadJson, $res['signature'] ?? '')) {
        log_license_event('VERIFY_TAMPERED', 'Verification response signature validation failed');
        return ['valid' => false, 'status' => 'tampered', 'reason' => 'Signature validation failed'];
    }

    $remoteStatus = strtolower((string)($res['payload']['status'] ?? 'active'));
    $remoteType = strtolower((string)($res['payload']['license_type'] ?? $licenseType));
    $serverTime = (int)($res['payload']['server_time'] ?? $now);

    // Enforce server-authoritative timestamp against expiration to prevent client clock manipulation
    if (!empty($res['payload']['expires_at']) && strtotime($res['payload']['expires_at']) < $serverTime) {
        $remoteStatus = 'expired';
    }

    $newNextCheck = date('Y-m-d H:i:s', $now + 86400);
    $newLastVerified = date('Y-m-d H:i:s', $now);
    $newExpiresAt = !empty($res['payload']['expires_at']) ? $res['payload']['expires_at'] : null;

    db()->prepare("
        UPDATE system_license 
        SET status = ?, license_type = ?, activation_payload = ?, signature = ?, last_verified_at = ?, next_check_at = ?, expires_at = ?
        WHERE id = ?
    ")->execute([
        $remoteStatus,
        $remoteType,
        $payloadJson,
        $res['signature'],
        $newLastVerified,
        $newNextCheck,
        $newExpiresAt,
        $lic['id']
    ]);

    $lic['status'] = $remoteStatus;
    $lic['license_type'] = $remoteType;
    $lic['expires_at'] = $newExpiresAt;
    $lic['payload'] = $res['payload'];

    if ($remoteStatus !== 'active') {
        log_license_event('STATUS_CHANGED', "License status transitioned to {$remoteStatus}");
        return ['valid' => false, 'status' => $remoteStatus, 'reason' => "License is {$remoteStatus}", 'license' => $lic];
    }

    log_license_event('VERIFY_SUCCESS', "License verified with authority on {$currentDomain}");
    return ['valid' => true, 'status' => 'active', 'license' => $lic];
}

/**
 * Verify whether the active license has entitlement to a specific feature module.
 */
function has_license_feature(string $featureName): bool
{
    $lic = get_local_license();
    if (!$lic || empty($lic['is_signature_valid']) || $lic['status'] !== 'active') {
        return false;
    }
    $entitlements = $lic['payload']['feature_entitlements'] ?? [];
    if (!is_array($entitlements)) {
        return false;
    }
    return !empty($entitlements[$featureName]);
}

/**
 * Execute authoritative remote business calculation (e.g. ERP inventory valuation).
 */
function remote_calculate_inventory_valuation(array $items): array
{
    $lic = get_local_license();
    if (!$lic || empty($lic['is_signature_valid']) || $lic['status'] !== 'active') {
        return ['success' => false, 'error' => 'UNAUTHORIZED', 'message' => 'Active license required for ERP valuation.'];
    }

    $serverUrl = get_licensing_server_url();
    $payloadData = $lic['payload'] ?? [];
    $rawKey = $payloadData['license_key'] ?? '';

    $postData = [
        'action' => 'business_operation',
        'operation' => 'inventory_valuation',
        'license_key' => $rawKey,
        'installation_id' => $lic['installation_id'],
        'items' => $items,
    ];

    $ch = curl_init($serverUrl . '?action=business_operation');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $raw = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($raw === false || $httpCode !== 200) {
        return ['success' => false, 'error' => 'SERVER_ERROR', 'message' => 'Business authority calculation service unavailable.'];
    }

    $res = json_decode($raw, true);
    if (!is_array($res) || empty($res['payload']) || empty($res['signature'])) {
        return ['success' => false, 'error' => 'INVALID_RESPONSE', 'message' => 'Invalid calculation response payload.'];
    }

    $payloadJson = json_encode($res['payload'], JSON_UNESCAPED_SLASHES);
    if (!verify_license_signature($payloadJson, $res['signature'])) {
        log_license_event('TAMPER_DETECTED', 'Business calculation signature failed verification');
        return ['success' => false, 'error' => 'TAMPER_DETECTED', 'message' => 'Calculation signature validation failed.'];
    }

    return $res;
}

/**
 * Validates local integrity of critical bootstrap and licensing source files.
 */
function verify_application_integrity(): array
{
    $criticalFiles = [
        'public/includes/license.php',
        'public/dbconnect.php',
        'public/activate.php',
        'public/license_status.php',
    ];

    $root = dirname(__DIR__, 2);
    $missing = [];
    $present = [];

    foreach ($criticalFiles as $rel) {
        $path = $root . '/' . $rel;
        if (!file_exists($path) || filesize($path) === 0) {
            $missing[] = $rel;
        } else {
            $present[$rel] = hash_file('sha256', $path);
        }
    }

    if (!empty($missing)) {
        log_license_event('INTEGRITY_FAILURE', 'Critical application files missing: ' . implode(', ', $missing));
        return [
            'valid' => false,
            'status' => 'integrity_failure',
            'missing' => $missing,
            'hashes' => $present,
        ];
    }

    return [
        'valid' => true,
        'status' => 'valid',
        'hashes' => $present,
    ];
}

/**
 * Deactivate local installation and inform licensing authority.
 */
function deactivate_license_local(): array
{
    $lic = get_local_license();
    if (!$lic) {
        return ['success' => false, 'message' => 'No active license found to deactivate.'];
    }

    $serverUrl = license_config('LICENSE_SERVER_URL', 'http://localhost:8080/grocery-store/licensing_server/api.php');
    $rawKey = $lic['payload']['license_key'] ?? '';

    if (!empty($rawKey)) {
        $ch = curl_init($serverUrl . '?action=deactivate');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'license_key' => $rawKey,
            'installation_id' => $lic['installation_id'],
        ]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 6);
        curl_exec($ch);
        curl_close($ch);
    }

    db()->exec("DELETE FROM system_license");
    log_license_event('DEACTIVATED', "Installation {$lic['installation_id']} was deactivated by user");

    return ['success' => true, 'message' => 'Installation deactivated successfully.'];
}

/**
 * Detect whether current request expects an API / AJAX JSON response.
 */
function is_ajax_or_api_request(): bool
{
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if (str_contains($uri, '/api/') || str_contains($uri, '/ajax/')) {
        return true;
    }
    $httpAccept = $_SERVER['HTTP_ACCEPT'] ?? '';
    if (str_contains($httpAccept, 'application/json')) {
        return true;
    }
    $requestedWith = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
    if (strtolower($requestedWith) === 'xmlhttprequest') {
        return true;
    }
    return false;
}

/**
 * Central Licensing Gatekeeper.
 * Invoked on every incoming request in public/dbconnect.php.
 */
function enforce_license(): void
{
    // 1. Allow unit testing / migration CLI runs only when explicitly flagged
    if (php_sapi_name() === 'cli' && defined('GROCO_CLI_TEST_MODE')) {
        return;
    }

    // 2. Allow activation screen and license status viewer themselves
    $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
    if (in_array($script, ['activate.php', 'license_status.php'], true)) {
        return;
    }

    // 3. Verify license status
    $result = verify_license_remote();

    if ($result['valid']) {
        return; // Authorized production execution
    }

    // 4. Handle Unauthorized / Inactive / Expired / Revoked states
    $status = $result['status'] ?? 'unactivated';
    $reason = $result['reason'] ?? 'License verification required';

    if (is_ajax_or_api_request()) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'error' => 'LICENSE_RESTRICTED',
            'status' => strtoupper($status),
            'message' => 'Production operation paused: ' . $reason,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }

    // 5. HTML Browser Redirection
    if ($status === 'unactivated') {
        header('Location: ' . url_for('activate.php'));
        exit;
    } else {
        header('Location: ' . url_for('license_status.php?status=' . urlencode($status)));
        exit;
    }
}
