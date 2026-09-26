# Licensing System Specification & Architecture

## 1. Overview & Security Model

The GroCo Grocery Store enterprise software distribution utilizes a dual-tier cryptographic licensing system located under `/public/includes/license.php` and backoffice module `/admin/license/`.

### Core Licensing Objectives:
1. **Domain & Hostname Binding**: Prevents unauthorized duplication of the codebase across non-whitelisted server domains or IPs.
2. **Cryptographic Validation**: Uses **RSA-2048 Asymmetric Key Signing** (SHA-256 with RSA encryption) or fallback HMAC-SHA256 signature verification to prevent client-side license tampering.
3. **Hardware / Machine Fingerprinting**: Incorporates server CPU ID, MAC address hash, and web root absolute path hash into the client machine identifier.
4. **Grace Period / Offline Tolerance**: Allows continuous operation during temporary licensing server outages (configurable default: 7-day offline grace period).

---

## 2. Licensing Cryptographic Schema

```mermaid
flowchart LR
    subgraph Licensing Server (Remote Authority)
        SK[Private Key RSA-2048]
        Gen[License Generator Engine]
        Gen -->|Signs Payload with Private Key| Sig[Cryptographic Signature]
    end

    subgraph Client Instance (GroCo Application)
        PK[Embedded Public Key RSA-2048]
        LicFile[(license.key File)]
        Verifier[license.php Validator]
        
        LicFile --> Verifier
        PK --> Verifier
        Verifier -->|Validates Signature & Domain Hash| State{License State}
        State -->|Valid| OK[Allow Full Access]
        State -->|Expired / Invalid| Lock[Lock Application to License Page]
    end
```

---

## 3. License Payload Structure

The license file (`config/license.key` or database `settings.license_key`) contains a base64-encoded JSON structure:

```json
{
  "license_id": "LIC-GROCO-2026-9842X",
  "client_name": "GroCo Retail Enterprise Ltd.",
  "client_email": "admin@groco.site.je",
  "domain": "groco.site.je",
  "allowed_domains": ["groco.site.je", "www.groco.site.je", "localhost", "127.0.0.1"],
  "machine_fingerprint": "a3f892bc901e88410294dce8419",
  "tier": "enterprise_pos",
  "max_products": -1,
  "max_cashier_registers": 10,
  "features": {
    "pos_enabled": true,
    "inventory_advanced": true,
    "multi_branch": false,
    "analytics_export": true,
    "sms_integration": true
  },
  "issued_at": "2026-01-01T00:00:00Z",
  "expires_at": "2027-01-01T23:59:59Z",
  "grace_days": 14,
  "signature": "MEQCIG48x..."
}
```

---

## 4. Verification Algorithm in `public/includes/license.php`

```php
function verify_system_license(): array {
    // 1. Read license string from config file or DB settings table
    $license_raw = get_license_data();
    if (empty($license_raw)) {
        return ['valid' => false, 'code' => 'LICENSE_MISSING', 'message' => 'No license key installed.'];
    }

    // 2. Decode payload & extract signature
    $payload = json_decode(base64_decode($license_raw['data']), true);
    $signature = base64_decode($license_raw['signature']);

    // 3. Cryptographic RSA Public Key verification
    $public_key = file_get_contents(__DIR__ . '/keys/license_public.pem');
    $data_to_verify = json_encode($payload, JSON_UNESCAPED_SLASHES);
    $is_valid_sig = openssl_verify($data_to_verify, $signature, $public_key, OPENSSL_ALGO_SHA256);

    if ($is_valid_sig !== 1) {
        return ['valid' => false, 'code' => 'SIGNATURE_TAMPERED', 'message' => 'Cryptographic signature is invalid.'];
    }

    // 4. Domain & Hostname Match Check
    $current_host = strtolower($_SERVER['HTTP_HOST'] ?? 'localhost');
    // Strip port numbers if present
    $current_host = explode(':', $current_host)[0];

    $allowed = array_map('strtolower', $payload['allowed_domains'] ?? []);
    if (!in_array($current_host, $allowed, true)) {
        return ['valid' => false, 'code' => 'DOMAIN_MISMATCH', 'message' => "License is not valid for domain {$current_host}."];
    }

    // 5. Expiration Date Check
    $expiry_timestamp = strtotime($payload['expires_at']);
    if (time() > $expiry_timestamp) {
        $grace_timestamp = $expiry_timestamp + (($payload['grace_days'] ?? 0) * 86400);
        if (time() > $grace_timestamp) {
            return ['valid' => false, 'code' => 'LICENSE_EXPIRED', 'message' => 'License expired and grace period ended.'];
        }
        return ['valid' => true, 'warning' => 'GRACE_PERIOD', 'message' => 'License expired, running in grace period.'];
    }

    return ['valid' => true, 'payload' => $payload];
}
```

---

## 5. Enforcement & Lockout Behavior

- **Global Guard**: Checked at the header of `public/includes/auth.php` and `admin/includes/auth.php`.
- **Lockout Action**:
  - Storefront public pages display maintenance mode or friendly "Service Notice".
  - Admin users attempting login or dashboard access are strictly redirected to `/admin/license/index.php`.
  - The `/admin/license/` route permits Super Admins to upload a new license file or enter a new activation key without needing database shell access.

---

## 6. Recommendations for Modernized Architecture
1. **Separation of Concerns**: Keep licensing decoupled from business domain models via an application middleware layer.
2. **Cloud Heartbeat**: Optional non-blocking periodic ping (every 24h) to license server for remote revocation or remote feature toggling.
3. **Environment Separation**: Automatically detect local development environments (`APP_ENV=local` / `localhost`) to bypass strict RSA validation during automated unit tests.
