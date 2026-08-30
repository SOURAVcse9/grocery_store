# 🛡️ GroCo Software Licensing & Maximum Protection Architecture

## 1. Overview & Business Model

GroCo is sold as **licensed commercial software**. The source code repository is intentionally **PUBLIC on GitHub**, allowing anyone to clone the code, but **no installation can legitimately operate without an authorized license issued by your central licensing authority**.

```text
       PUBLIC GITHUB REPOSITORY (Anyone may git clone)
                          │
                          ▼
             FRESH CLONE ON ANY SERVER
                          │
                          ▼
                 NO VALID LICENSE
                          │
                          ▼
                 ❌ STRICTLY BLOCKED
             (Redirects to activate.php)
```

---

## 2. 1-Year Commercial Subscription Lifecycle

When a merchant purchases a 1-year commercial license:
1. **License Key**: `GRCO-XXXX-XXXX-XXXX-XXXX`
2. **Authorized Domain**: e.g., `shop.example.com`
3. **Expiration Date**: 1 year from issue date (e.g., `2027-08-30`)
4. **Node Installation ID**: Unique cryptographic hash computed for the server environment.

```text
VALID 1-YEAR LICENSE
       │
       ▼
AUTHORIZED DOMAIN (shop.example.com)
       │
       ▼
AUTHORIZED INSTALLATION (inst_xxxx)
       │
       ▼
REMOTE HTTPS VERIFICATION
       │
       ▼
ACTIVE & CRYPTOGRAPHICALLY SIGNED
       │
       ▼
✅ STORE OPERATES FULLY


1 YEAR PASSES (Expiry Reached)
       │
       ▼
REMOTE AUTHORITY SENDS 'EXPIRED'
       │
       ▼
❌ APPLICATION BLOCKED (license_status.php?status=expired)
       │
       ▼
MERCHANT RENEWS SUBSCRIPTION
       │
       ▼
AUTHORITY UPDATES EXPIRATION (e.g., 2028-08-30)
       │
       ▼
NEXT REQUEST AUTOMATICALLY SYNCS & RESTORES ACCESS
       │
       ▼
✅ STORE OPERATES FULLY
```

---

## 3. Defense-in-Depth Architecture

### A. Immediate Remote Verification (No Stale 24-Hour Cache)
Every application request reaching the licensing gatekeeper connects to your central licensing authority.
- **Revocation**: Takes effect immediately on the next request.
- **Reactivation**: Restores store operation immediately on the next request.
- **Renewal**: Instantly applies new subscription terms without requiring code changes or reinstallations.

### B. Outage Tolerance & Grace Period (7 Days)
If your central licensing server experiences temporary network downtime or maintenance:
- If the store held a valid active license within the last 7 days, it enters `GRACE_ACTIVE` mode (zero unexpected customer checkout downtime).
- If offline for $> 7$ consecutive days, access is safely paused until connectivity is restored.
- **Strict Rule**: Remote responses of `revoked`, `suspended`, `expired`, or `domain_mismatch` **never** enter grace period.

### C. RSA-2048 Asymmetric Cryptography
- The **Private Signing Key** (`license_private.pem`) resides **strictly on your licensing authority** and is NEVER committed to git or shipped to customers.
- The client installation contains only the public verification key.
- Direct database tampering (e.g. `UPDATE system_license SET status='active'`) immediately fails RSA signature validation and triggers `TAMPER_DETECTED` containment.

### D. Node & Domain Binding
- Every installation receives a deterministic `installation_id` based on hardware and filesystem parameters.
- Two installations sharing a MySQL database cannot share licenses.
- Copying a database dump to a new server assigns a new node ID; the licensing authority rejects unactivated nodes once the license slot limit is reached.

### E. Server-Authoritative Feature Entitlements
- Entitlements for modules (`pos`, `erp`, `advanced_reports`, `multi_branch`, `inventory_valuation`) are signed in the remote payload.
- Modules check `has_license_feature('module_name')` before permitting access.

### F. Remote Authoritative Business Calculations
- Critical business operations (such as ERP inventory valuation using FIFO and wholesale margin analytics) are computed on the central licensing authority and return cryptographically signed calculations.

### G. Application File Integrity
- `verify_application_integrity()` verifies SHA-256 integrity of critical core files (`public/includes/license.php`, `public/dbconnect.php`, `public/activate.php`, `public/license_status.php`).

---

## 4. Administrative Authority CLI Tool

Run commands on your licensing server using [`licensing_server/cli_license_tool.php`](file:///c:/xampp/htdocs/grocery-store/licensing_server/cli_license_tool.php):

```bash
# 1. Issue a 1-Year Commercial Production License
php licensing_server/cli_license_tool.php create \
    --customer="Acme Supermarket" \
    --email="owner@acme.com" \
    --domains="shop.acme.com,www.shop.acme.com" \
    --limit=1 \
    --expires="2027-08-30" \
    --type=production

# 2. Issue a Local Development License for Developers / Testing
php licensing_server/cli_license_tool.php create \
    --customer="Core Dev Team" \
    --email="dev@groco.com.bd" \
    --domains="localhost" \
    --limit=2 \
    --type=development

# 3. Renew a Commercial License for Another Year (+365 days)
php licensing_server/cli_license_tool.php renew GRCO-XXXX-XXXX-XXXX-XXXX --days=365

# 4. Revoke a Compromised or Defaulted License
php licensing_server/cli_license_tool.php revoke GRCO-XXXX-XXXX-XXXX-XXXX --reason="Chargeback / Breach of Terms"

# 5. Reactivate a Suspended or Revoked License
php licensing_server/cli_license_tool.php reactivate GRCO-XXXX-XXXX-XXXX-XXXX

# 6. List All Issued Licenses and Activation Slots
php licensing_server/cli_license_tool.php list

# 7. Inspect Full Signed Metadata for a License
php licensing_server/cli_license_tool.php inspect GRCO-XXXX-XXXX-XXXX-XXXX
```

---

## 5. Security Realities of Self-Hosted PHP

> [!IMPORTANT]
> Because PHP source code executes on the customer's server, anyone with physical root access or source access can edit PHP files. No raw self-hosted PHP script can claim to be mathematically impossible to modify.
>
> GroCo achieves maximum real-world protection through **Defense-in-Depth**:
> 1. Remote authority verification is the source of truth.
> 2. Database manipulation is defeated by asymmetric RSA-2048 signatures.
> 3. Database duplication is defeated by installation node identity binding.
> 4. Clock tampering is defeated by server-authoritative timestamps.
> 5. Critical business analytics require valid remote authority execution.
> 6. License failure is strictly non-destructive (customer database records and files are never destroyed).
