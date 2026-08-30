<?php
/**
 * licensing_server/license_server.php
 * 
 * Authoritative Licensing Server Engine for GroCo Platform.
 * Generates cryptographically secure license keys, signs activation payloads
 * using an RSA-2048 private key, enforces domain bindings and activation limits,
 * and handles remote revocation/suspension.
 */

declare(strict_types=1);

namespace GroCo\Licensing;

use PDO;
use Exception;
use DateTimeImmutable;

class LicenseServer
{
    private PDO $pdo;
    private string $storageDir;
    private string $privateKeyPath;
    private string $publicKeyPath;

    public function __construct(?string $dbPath = null)
    {
        $this->storageDir = __DIR__ . '/data';
        if (!is_dir($this->storageDir)) {
            mkdir($this->storageDir, 0750, true);
        }

        $this->privateKeyPath = $this->storageDir . '/license_private.pem';
        $this->publicKeyPath = $this->storageDir . '/license_public.pem';

        $this->ensureKeypairExists();

        $sqliteFile = $dbPath ?: ($this->storageDir . '/licensing.sqlite');
        $this->pdo = new PDO('sqlite:' . $sqliteFile, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $this->initSchema();
    }

    /**
     * Ensure RSA keypair exists; if not, generate a 2048-bit RSA keypair.
     */
    private function ensureKeypairExists(): void
    {
        if (file_exists($this->privateKeyPath) 
            && file_exists($this->publicKeyPath) 
            && (int)@filesize($this->privateKeyPath) > 500 
            && (int)@filesize($this->publicKeyPath) > 100
        ) {
            return;
        }

        $config = [
            "digest_alg" => "sha256",
            "private_key_bits" => 2048,
            "private_key_type" => OPENSSL_KEYTYPE_RSA,
        ];

        $cnfCandidates = [
            getenv('OPENSSL_CONF'),
            'C:/xampp/apache/conf/openssl.cnf',
            'C:/xampp/php/extras/ssl/openssl.cnf',
            'C:/xampp/php/extras/openssl/openssl.cnf',
            '/etc/ssl/openssl.cnf',
            '/usr/lib/ssl/openssl.cnf',
        ];
        foreach ($cnfCandidates as $cnf) {
            if ($cnf && file_exists($cnf)) {
                $config['config'] = $cnf;
                break;
            }
        }

        $res = openssl_pkey_new($config);
        if (!$res) {
            throw new Exception("OpenSSL keypair generation failed: " . openssl_error_string());
        }

        $exported = openssl_pkey_export($res, $privateKey, null, $config);
        if (!$exported) {
            throw new Exception("OpenSSL key export failed: " . openssl_error_string());
        }

        $keyDetails = openssl_pkey_get_details($res);
        $publicKey = $keyDetails['key'];

        file_put_contents($this->privateKeyPath, $privateKey);
        file_put_contents($this->publicKeyPath, $publicKey);
        chmod($this->privateKeyPath, 0600);
    }

    public function getPublicKey(): string
    {
        return (string)file_get_contents($this->publicKeyPath);
    }

    private function initSchema(): void
    {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS licenses (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                license_key TEXT UNIQUE NOT NULL,
                customer_name TEXT NOT NULL,
                customer_email TEXT NOT NULL,
                product TEXT NOT NULL DEFAULT 'GroCo Grocery Store',
                license_type TEXT NOT NULL DEFAULT 'production',
                status TEXT NOT NULL DEFAULT 'active',
                activation_limit INTEGER NOT NULL DEFAULT 1,
                activation_count INTEGER NOT NULL DEFAULT 0,
                allowed_domains TEXT NOT NULL,
                created_at TEXT NOT NULL,
                expires_at TEXT NULL,
                last_verified_at TEXT NULL,
                revoked_at TEXT NULL,
                notes TEXT NULL
            );

            CREATE TABLE IF NOT EXISTS installations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                license_id INTEGER NOT NULL,
                installation_id TEXT NOT NULL,
                domain TEXT NOT NULL,
                ip_address TEXT NULL,
                status TEXT NOT NULL DEFAULT 'active',
                activated_at TEXT NOT NULL,
                last_verified_at TEXT NOT NULL,
                FOREIGN KEY (license_id) REFERENCES licenses(id) ON DELETE CASCADE,
                UNIQUE(license_id, installation_id)
            );
        ");

        try {
            $this->pdo->exec("ALTER TABLE licenses ADD COLUMN license_type TEXT NOT NULL DEFAULT 'production'");
        } catch (\Throwable $e) {
            // Already exists
        }
    }

    /**
     * Generate a cryptographically strong license key formatted: GRCO-XXXX-XXXX-XXXX-XXXX
     */
    public function generateLicenseKey(): string
    {
        $segments = [];
        for ($i = 0; $i < 4; $i++) {
            $bytes = random_bytes(2);
            $segments[] = strtoupper(bin2hex($bytes));
        }
        return 'GRCO-' . implode('-', $segments);
    }

    /**
     * Check if a domain is a local loopback or local development host.
     */
    public function isLocalDomain(string $domain): bool
    {
        $d = $this->normalizeDomain($domain);
        if (in_array($d, ['localhost', '127.0.0.1', '::1'], true)) {
            return true;
        }
        if (str_ends_with($d, '.test') || str_ends_with($d, '.local')) {
            return true;
        }
        return false;
    }

    /**
     * Create a new license record on the licensing server.
     */
    public function createLicense(
        string $customerName,
        string $customerEmail,
        array $allowedDomains = [],
        int $activationLimit = 1,
        ?string $expiresAt = null,
        string $notes = '',
        string $licenseType = 'production'
    ): array {
        $licenseKey = $this->generateLicenseKey();
        $licenseType = in_array(strtolower($licenseType), ['development', 'trial', 'production'], true)
            ? strtolower($licenseType)
            : 'production';

        if ($licenseType === 'development' && (empty($allowedDomains) || in_array('*', $allowedDomains, true))) {
            $allowedDomains = ['localhost', '127.0.0.1', '::1', '*.test', '*.local'];
        }

        $normalizedDomains = array_values(array_unique(array_map([$this, 'normalizeDomain'], $allowedDomains)));

        $stmt = $this->pdo->prepare("
            INSERT INTO licenses (
                license_key, customer_name, customer_email, product, license_type, status,
                activation_limit, activation_count, allowed_domains, created_at, expires_at, notes
            ) VALUES (?, ?, ?, 'GroCo Grocery Store', ?, 'active', ?, 0, ?, ?, ?, ?)
        ");

        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');
        $stmt->execute([
            $licenseKey,
            $customerName,
            $customerEmail,
            $licenseType,
            $activationLimit,
            json_encode($normalizedDomains),
            $now,
            $expiresAt,
            $notes,
        ]);

        return $this->getLicense($licenseKey);
    }

    public function getLicense(string $licenseKey): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM licenses WHERE license_key = ?");
        $stmt->execute([$licenseKey]);
        $row = $stmt->fetch();
        if ($row) {
            $row['allowed_domains'] = json_decode($row['allowed_domains'] ?: '[]', true) ?: [];
        }
        return $row ?: null;
    }

    public function listLicenses(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM licenses ORDER BY id DESC");
        $rows = $stmt->fetchAll();
        foreach ($rows as &$r) {
            $r['allowed_domains'] = json_decode($r['allowed_domains'] ?: '[]', true) ?: [];
        }
        return $rows;
    }

    public function updateLicenseStatus(string $licenseKey, string $status, ?string $reason = null): bool
    {
        $validStatuses = ['active', 'suspended', 'expired', 'revoked', 'pending'];
        if (!in_array($status, $validStatuses, true)) {
            throw new Exception("Invalid license status: {$status}");
        }

        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');
        $revokedAt = ($status === 'revoked') ? $now : null;

        $stmt = $this->pdo->prepare("
            UPDATE licenses 
            SET status = ?, revoked_at = COALESCE(?, revoked_at), notes = COALESCE(? || ' | ' || notes, notes)
            WHERE license_key = ?
        ");
        return $stmt->execute([$status, $revokedAt, $reason, $licenseKey]);
    }

    /**
     * Activate a GroCo installation.
     */
    public function activate(
        string $licenseKey,
        string $domain,
        string $installationId,
        ?string $clientIp = null,
        ?string $nonce = null
    ): array {
        $license = $this->getLicense($licenseKey);
        if (!$license) {
            return [
                'success' => false,
                'error' => 'INVALID_LICENSE',
                'message' => 'The provided license key was not found on the licensing server.',
            ];
        }

        if ($license['status'] !== 'active') {
            return [
                'success' => false,
                'error' => strtoupper($license['status']),
                'message' => "License is currently {$license['status']}.",
            ];
        }

        if ($license['expires_at'] && strtotime($license['expires_at']) < time()) {
            $this->updateLicenseStatus($licenseKey, 'expired', 'Expired on check');
            return [
                'success' => false,
                'error' => 'EXPIRED',
                'message' => 'License has expired.',
            ];
        }

        $normalizedDomain = $this->normalizeDomain($domain);
        $licenseType = $license['license_type'] ?? 'production';

        // Check if attempting to use production license on localhost without explicit permission
        if ($licenseType === 'production' && $this->isLocalDomain($normalizedDomain)) {
            if (!in_array('localhost', $license['allowed_domains'], true) && !in_array('127.0.0.1', $license['allowed_domains'], true)) {
                return [
                    'success' => false,
                    'error' => 'PROD_LICENSE_ON_LOCALHOST',
                    'message' => 'Production licenses cannot be activated on localhost or local development environments.',
                ];
            }
        }

        // Check if attempting to use development license on non-local domain
        if ($licenseType === 'development' && !$this->isLocalDomain($normalizedDomain)) {
            return [
                'success' => false,
                'error' => 'DEV_LICENSE_ON_PRODUCTION',
                'message' => "Development licenses can only be activated on local development hosts (localhost, 127.0.0.1, *.test). Domain '{$normalizedDomain}' is not a local host.",
            ];
        }

        if (!$this->isDomainAllowed($normalizedDomain, $license['allowed_domains'])) {
            return [
                'success' => false,
                'error' => 'DOMAIN_MISMATCH',
                'message' => "This license is not authorized for domain '{$normalizedDomain}'.",
            ];
        }

        // Check if installation already exists
        $stmtInst = $this->pdo->prepare("SELECT * FROM installations WHERE license_id = ? AND installation_id = ?");
        $stmtInst->execute([$license['id'], $installationId]);
        $existingInst = $stmtInst->fetch();

        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');

        if (!$existingInst) {
            // Check activation limit
            if ($license['activation_count'] >= $license['activation_limit']) {
                return [
                    'success' => false,
                    'error' => 'ACTIVATION_LIMIT_EXCEEDED',
                    'message' => "Activation limit of {$license['activation_limit']} installation(s) reached for this license.",
                ];
            }

            // Record new installation
            $stmtInsert = $this->pdo->prepare("
                INSERT INTO installations (license_id, installation_id, domain, ip_address, status, activated_at, last_verified_at)
                VALUES (?, ?, ?, ?, 'active', ?, ?)
            ");
            $stmtInsert->execute([$license['id'], $installationId, $normalizedDomain, $clientIp, $now, $now]);

            // Increment activation count
            $this->pdo->prepare("UPDATE licenses SET activation_count = activation_count + 1 WHERE id = ?")
                ->execute([$license['id']]);
        } else {
            // Update existing installation record
            $stmtUpdate = $this->pdo->prepare("
                UPDATE installations 
                SET domain = ?, ip_address = ?, status = 'active', last_verified_at = ?
                WHERE id = ?
            ");
            $stmtUpdate->execute([$normalizedDomain, $clientIp, $now, $existingInst['id']]);
        }

        // Update license last verified
        $this->pdo->prepare("UPDATE licenses SET last_verified_at = ? WHERE id = ?")->execute([$now, $license['id']]);

        // Build signed payload
        $payload = [
            'license_key' => $licenseKey,
            'customer_name' => $license['customer_name'],
            'customer_email' => $license['customer_email'],
            'license_type' => $licenseType,
            'installation_id' => $installationId,
            'domain' => $normalizedDomain,
            'status' => 'active',
            'expires_at' => $license['expires_at'],
            'verified_at' => $now,
            'nonce' => $nonce ?: bin2hex(random_bytes(16)),
        ];

        $signature = $this->signPayload($payload);

        return [
            'success' => true,
            'message' => 'Installation activated successfully.',
            'payload' => $payload,
            'signature' => $signature,
        ];
    }

    /**
     * Periodic re-verification of an activated installation.
     */
    public function verify(
        string $licenseKey,
        string $installationId,
        string $domain,
        ?string $clientIp = null,
        ?string $nonce = null
    ): array {
        $license = $this->getLicense($licenseKey);
        if (!$license) {
            return [
                'success' => false,
                'status' => 'revoked',
                'error' => 'INVALID_LICENSE',
                'message' => 'License not found.',
            ];
        }

        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');

        if ($license['expires_at'] && strtotime($license['expires_at']) < time()) {
            $this->updateLicenseStatus($licenseKey, 'expired', 'Expired on verify');
            $license['status'] = 'expired';
        }

        $normalizedDomain = $this->normalizeDomain($domain);
        $licenseType = $license['license_type'] ?? 'production';

        if ($licenseType === 'production' && $this->isLocalDomain($normalizedDomain)) {
            if (!in_array('localhost', $license['allowed_domains'], true) && !in_array('127.0.0.1', $license['allowed_domains'], true)) {
                return [
                    'success' => false,
                    'status' => 'suspended',
                    'error' => 'PROD_LICENSE_ON_LOCALHOST',
                    'message' => 'Production license cannot be used on local development environment.',
                ];
            }
        }

        if ($licenseType === 'development' && !$this->isLocalDomain($normalizedDomain)) {
            return [
                'success' => false,
                'status' => 'suspended',
                'error' => 'DEV_LICENSE_ON_PRODUCTION',
                'message' => "Development license cannot be used on external domain '{$normalizedDomain}'.",
            ];
        }

        if (!$this->isDomainAllowed($normalizedDomain, $license['allowed_domains'])) {
            return [
                'success' => false,
                'status' => 'suspended',
                'error' => 'DOMAIN_MISMATCH',
                'message' => "Domain '{$normalizedDomain}' is not permitted by this license.",
            ];
        }

        // Check installation status
        $stmtInst = $this->pdo->prepare("SELECT * FROM installations WHERE license_id = ? AND installation_id = ?");
        $stmtInst->execute([$license['id'], $installationId]);
        $inst = $stmtInst->fetch();

        if (!$inst || $inst['status'] !== 'active') {
            return [
                'success' => false,
                'status' => 'revoked',
                'error' => 'INSTALLATION_NOT_FOUND',
                'message' => 'Installation is not registered or has been revoked.',
            ];
        }

        // Update verification timestamp
        $this->pdo->prepare("UPDATE installations SET last_verified_at = ?, ip_address = ? WHERE id = ?")
            ->execute([$now, $clientIp, $inst['id']]);
        $this->pdo->prepare("UPDATE licenses SET last_verified_at = ? WHERE id = ?")
            ->execute([$now, $license['id']]);

        $payload = [
            'license_key' => $licenseKey,
            'customer_name' => $license['customer_name'],
            'customer_email' => $license['customer_email'],
            'license_type' => $licenseType,
            'installation_id' => $installationId,
            'domain' => $normalizedDomain,
            'status' => $license['status'],
            'expires_at' => $license['expires_at'],
            'verified_at' => $now,
            'nonce' => $nonce ?: bin2hex(random_bytes(16)),
        ];

        $signature = $this->signPayload($payload);

        return [
            'success' => ($license['status'] === 'active'),
            'status' => $license['status'],
            'payload' => $payload,
            'signature' => $signature,
        ];
    }

    /**
     * Deactivate a specific installation to free up activation slot.
     */
    public function deactivate(string $licenseKey, string $installationId): array
    {
        $license = $this->getLicense($licenseKey);
        if (!$license) {
            return ['success' => false, 'error' => 'INVALID_LICENSE'];
        }

        $stmt = $this->pdo->prepare("DELETE FROM installations WHERE license_id = ? AND installation_id = ?");
        $stmt->execute([$license['id'], $installationId]);
        $deleted = $stmt->rowCount();

        if ($deleted > 0) {
            $this->pdo->prepare("UPDATE licenses SET activation_count = MAX(0, activation_count - 1) WHERE id = ?")
                ->execute([$license['id']]);
            return ['success' => true, 'message' => 'Installation deactivated successfully.'];
        }

        return ['success' => false, 'error' => 'INSTALLATION_NOT_FOUND'];
    }

    public function signPayload(array $payload): string
    {
        $canonicalJson = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $privateKey = openssl_pkey_get_private(file_get_contents($this->privateKeyPath));
        if (!$privateKey) {
            throw new Exception("Unable to load private signing key.");
        }

        openssl_sign($canonicalJson, $signature, $privateKey, OPENSSL_ALGO_SHA256);
        return base64_encode($signature);
    }

    public function normalizeDomain(string $domain): string
    {
        $d = trim(strtolower($domain));
        // Remove scheme if present
        $d = preg_replace('#^https?://#i', '', $d);
        // Remove path/query
        $parts = explode('/', $d);
        $hostWithPort = $parts[0];
        // Remove port
        $hostParts = explode(':', $hostWithPort);
        $host = $hostParts[0];
        // Remove leading 'www.'
        return preg_replace('/^www\./i', '', $host);
    }

    public function isDomainAllowed(string $domain, array $allowedDomains): bool
    {
        // Wildcard wildcard '*' or empty array means any domain
        if (empty($allowedDomains) || in_array('*', $allowedDomains, true)) {
            return true;
        }

        $normalized = $this->normalizeDomain($domain);
        foreach ($allowedDomains as $allowed) {
            $allowedNorm = $this->normalizeDomain($allowed);
            if ($normalized === $allowedNorm) {
                return true;
            }
            // Support subdomain wildcard: *.example.com
            if (str_starts_with($allowed, '*.') && str_ends_with($normalized, substr($allowedNorm, 1))) {
                return true;
            }
        }
        return false;
    }
}
