# GroCo Grocery Store — Disaster Recovery & Backup Plan

**Document Version:** 1.0.0  
**Target System:** GroCo Grocery Store Platform  
**Compliance Target:** RPO < 1 hour, RTO < 30 minutes

---

## 1. Objectives & Metrics

- **Recovery Point Objective (RPO):** Maximum acceptable data loss window: **< 1 hour** (Hourly transaction logs / Daily automated snapshot).
- **Recovery Time Objective (RTO):** Maximum acceptable restore duration: **< 30 minutes**.

---

## 2. Backup Strategy & Retention

| Data Asset | Frequency | Storage Location | Retention Policy | Encryption |
| :--- | :--- | :--- | :--- | :--- |
| **MySQL Full Snapshot** | Daily at 02:00 UTC | `storage/backups/` + Offsite S3 Bucket | 30 Days Daily, 12 Months Monthly | AES-256 GCM |
| **Transaction Binary Logs** | Continuous / Hourly | Offsite Object Storage | 7 Days | AES-256 |
| **Public Storefront Media** | Continuous (on upload)| Cloudinary Cloud CDN | Indefinite / Mirrored | Provider Managed |
| **Application Source & Configs** | On Release / Git Push | Private GitHub Repository | Versioned Git History | Git SSH / 2FA |

---

## 3. Step-by-Step Restoration Procedure

### 1. Database Restoration
```bash
# 1. Unpack latest encrypted snapshot
openssl enc -d -aes-256-cbc -in backup_20260926.sql.enc -out backup_20260926.sql

# 2. Restore into fresh MySQL instance
mysql -u root -p grocery_store < backup_20260926.sql
```

### 2. Media Asset Verification
Verify `MediaService` credentials in `.env` (`CLOUDINARY_CLOUD_NAME`, `CLOUDINARY_API_KEY`, `CLOUDINARY_API_SECRET`). If operating offline, ensure `public/uploads/` directory is restored.

### 3. Application Health Check
Run automated verification suite:
```bash
php tests/security_hardening_penetration_test.php
php tests/production_dual_auth_test.php
```
