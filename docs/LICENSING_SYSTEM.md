# 🛡️ GroCo Software Licensing & Installation Activation Architecture

## 1. Overview & Threat Model

GroCo includes an enterprise-grade, cryptographically signed software licensing and installation activation subsystem designed to prevent unauthorized deployment and commercial usage when source code is obtained or cloned from version control.

### Realistic Security Boundaries for Self-Hosted PHP
> [!IMPORTANT]
> Because PHP is an interpreted scripting language executed on the customer's server, anyone with physical or shell access to a server and intermediate programming knowledge can technically read, modify, or strip lines of source code. **No self-hosted PHP application can claim to be mathematically impossible to tamper with once raw source code is handed over.**
>
> True enterprise software protection relies on **Defense-in-Depth**:
> 1. **Private GitHub Repository**: Keeping source code access restricted to vetted team members.
> 2. **Remote Authoritative Licensing Server**: Critical licensing status decisions (active, suspended, expired, revoked) are determined remotely on a project-owner-controlled server.
> 3. **Asymmetric Cryptographic Signatures (RSA-2048)**: Activation responses are signed with a private key residing **only** on your licensing authority. The client verifies authenticity with an embedded public key. Tampering with local status values breaks the cryptographic signature.
> 4. **Domain & Machine Binding**: Activations are cryptographically tied to the authorized domain name and unique machine installation ID.
> 5. **Non-Destructive Enforcement**: Inactive installations are cleanly paused with a friendly status screen. Customer orders, database tables, and uploaded assets are **never deleted or destroyed**.

---

## 2. Architecture & Request Flow

```text
               ANY INSTALLATION (Localhost OR Production Server)       LICENSING AUTHORITY
        ┌──────────────────────────────────────────────────────┐    ┌────────────────────────┐
        │                    User Request                      │    │ Project Owner API Host │
        │                         │                            │    └───────────┬────────────┘
        │                         ▼                            │                │
        │               [public/dbconnect.php]                 │                │
        │                         │                            │                │
        │                [enforce_license()]                   │                │
        │                         │                            │                │
        │                         ▼                            │                │
        │            Check Local Cryptographic Cache           │                │
        │                (system_license table)                │                │
        │                         │                            │                │
        │               ┌─────────┴──────────┐                 │                │
        │               ▼                    ▼                 │                │
        │        [Active & Valid]      [Unactivated / Due]     │                │
        │               │                    │                 │                │
        │             ALLOW                  ▼                 │                │
        │                         Periodic HTTPS Handshake     │                │
        │                          (Signed Nonce Payload)      │                │
        │                                    │                 │                │
        │                                    ├────────────────►│ (POST /api.php?action=verify)
        │                                    │                 │   • Validates License Type
        │                                    │                 │   • Validates Domain
        │                                    │                 │   • Validates Status
        │                                    │                 │   • Signs with RSA-2048 Private Key
        │                                    │◄────────────────┤
        │                                    ▼                 │
        │                      Verify RSA-2048 Signature       │
        │                                    │                 │
        │                      ┌─────────────┴─────────────┐   │
        │                      ▼                           ▼   │
        │                [Active / Valid]         [Revoked / Expired]
        │                      │                           │   │
        │                    ALLOW                  Display Branded
        │                                         License Status Screen
        └──────────────────────────────────────────────────────┘
```

---

## 3. Mandatory License Model: Development vs. Production Tiers

In accordance with our **Public GitHub Repository** security model, anyone in the world may clone the repository, but **nobody can run the application without an authorized license from your licensing authority**.

There is **NO automatic localhost bypass** and **NO environment variable that can disable the licensing system**.

### License Tiers:

1. **Development License (`license_type: development`)**:
   - Authorized exclusively for local development loopback domains: `localhost`, `127.0.0.1`, `::1`, `*.test`, `*.local`.
   - Cannot be activated on public or production domains (e.g. `groco.com.bd` will return `DEV_LICENSE_ON_PRODUCTION`).
   - Issued to your internal developers, contractors, or evaluators.

2. **Production License (`license_type: production`)**:
   - Authorized exclusively for designated public hostnames / production domains (e.g. `shop.example.com`).
   - Cannot be activated on `localhost` or local development loopbacks (returns `PROD_LICENSE_ON_LOCALHOST`).
   - Enforces strict installation slot limits and domain binding.

3. **Trial / Evaluation License (`license_type: trial`)**:
   - Time-limited license for customer proof-of-concept deployments.
   - Automatically transitions to `EXPIRED` status upon reaching its expiration timestamp.

---

## 4. Issuing and Managing Licenses via CLI

As the repository owner, you generate licenses using the administrative CLI tool on your licensing server:

```bash
# Issue a Development License for a local developer
php licensing_server/cli_license_tool.php create --customer="Dev Team" --email="dev@groco.com" --type=development --limit=3

# Issue a Production License for a paying commercial customer
php licensing_server/cli_license_tool.php create --customer="Retail Store Ltd" --email="billing@retail.com" --domains="shop.retail.com" --type=production --limit=1

# List all issued licenses
php licensing_server/cli_license_tool.php list

# Revoke a license remotely
php licensing_server/cli_license_tool.php revoke <LICENSE_KEY> --reason="Non-payment"
```

---

## 4. Production Mode & Deployment

In production, GroCo requires an active, cryptographically signed license.

### Step 1: Configure Environment Variables
In `.env` on your production server:
```env
APP_ENV=production
LICENSE_SERVER_URL=https://license.groco.com.bd/api.php
LICENSE_GRACE_PERIOD_DAYS=7
```

### Step 2: First-Time Activation
1. When a user or administrator navigates to the website for the first time, GroCo detects that no activation record exists.
2. The application presents the **License Activation Screen** (`public/activate.php`).
3. The administrator enters:
   - **License Key**: `GRCO-XXXX-XXXX-XXXX-XXXX`
   - **Contact Email**: `admin@clientdomain.com`
   - **Domain**: Automatically detected (e.g. `groco.com.bd`)
4. Upon submission, GroCo contacts your remote licensing authority.
5. If valid, the licensing server registers the installation and returns an RSA-signed activation token.
6. The client stores the verified token in `system_license` and unlocks production access.

---

## 5. Remote Revocation, Suspension & Statuses

The licensing authority can update a license's status at any time:

| Status | Effect on Production Store | Behavior |
| :--- | :--- | :--- |
| **`active`** | Full normal operation | Periodic recheck every 24 hours. |
| **`suspended`** | Access paused | Shows friendly status screen explaining account review. |
| **`expired`** | Access paused | Shows subscription renewal notice with contact email. |
| **`revoked`** | Access permanently paused | Shows license revoked notice. |
| **`grace_exceeded`** | Access paused | Occurs if offline for $> 7$ consecutive days without re-verification. |

> [!NOTE]
> All restricted statuses display a clean, branded status screen (`public/license_status.php`) with store contact details. Zero database records or user files are ever deleted.

---

## 6. Offline Outage Tolerance & Grace Periods

To prevent customer checkout interruptions during temporary licensing server downtime or maintenance:

1. **24-Hour Cache**: Successful handshakes are cached locally. The client only re-verifies once every 24 hours.
2. **Configurable Grace Period (Default: 7 Days)**: If the remote licensing API is unreachable due to a network glitch or server outage, GroCo checks the elapsed time since `last_verified_at`.
   - If within 7 days: GroCo logs `GRACE_ACTIVE` and continues normal operations without interruption.
   - If offline for more than 7 days: GroCo pauses production access until connectivity is restored.

---

## 7. Administrative License Authority CLI

The licensing authority includes a CLI management utility at [`licensing_server/cli_license_tool.php`](file:///c:/xampp/htdocs/grocery-store/licensing_server/cli_license_tool.php):

### Generating a New License:
```bash
php licensing_server/cli_license_tool.php create \
    --customer="Metro Grocers Ltd" \
    --email="billing@metrogrocers.com" \
    --domains="metrogrocers.com,www.metrogrocers.com" \
    --limit=1 \
    --expires="2027-12-31" \
    --notes="Annual Enterprise Plan"
```

### Listing All Issued Licenses:
```bash
php licensing_server/cli_license_tool.php list
```

### Revoking a License:
```bash
php licensing_server/cli_license_tool.php revoke GRCO-XXXX-XXXX-XXXX-XXXX --reason="Non-payment"
```

### Suspending a License:
```bash
php licensing_server/cli_license_tool.php suspend GRCO-XXXX-XXXX-XXXX-XXXX --reason="Account audit"
```

### Reactivating a License:
```bash
php licensing_server/cli_license_tool.php reactivate GRCO-XXXX-XXXX-XXXX-XXXX
```

### Exporting the Public Verification Key:
```bash
php licensing_server/cli_license_tool.php public-key
```

---

## 8. Admin License Dashboard

Store administrators with `settings.manage` permissions can manage their license directly from the GroCo Admin Portal under:
`Admin Panel -> Settings -> Software License` (`admin/license/index.php`).

Features:
- Current License Mask (`GRCO-••••-••••-••••-XXXX`)
- Installation Node ID & Bound Domain
- Last Verified timestamp & next scheduled check
- "Re-verify Now" manual check button
- "Deactivate Installation" button (frees up the license slot for server migration)
- Audit trail log table of all licensing events

---

## 9. GitHub Repository Security Guidelines

To prevent unauthorized access to your GroCo source code:

1. **Keep the Repository PRIVATE**:
   - Go to `Repository Settings -> Danger Zone -> Change repository visibility -> Make private`.
2. **Implement Principle of Least Privilege**:
   - Only invite verified developers as collaborators.
   - Assign `Read` or `Triage` roles to contractors who do not require write access.
3. **Protect the Default Branch (`main`)**:
   - Enable Branch Protection Rules: Require pull request reviews before merging.
   - Require status checks to pass before merging.
4. **Never Commit Secrets**:
   - Ensure `.env` is listed in `.gitignore`.
   - Never commit `licensing_server/data/license_private.pem` (the master RSA signing key).
   - Never commit production database backups containing customer records.
5. **Enable Two-Factor Authentication (2FA)**:
   - Require 2FA for all GitHub organization members and collaborators.
