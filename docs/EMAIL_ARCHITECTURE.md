# GroCo Grocery Store — Modern Email & Notification Architecture

**Document Version:** 1.0.0  
**Target System:** GroCo Grocery Store  
**Service Class:** `EmailService` (`public/includes/EmailService.php`)

---

## 1. Multi-Provider Architecture

The email subsystem supports flexible transactional email delivery through pluggable transport adapters configured entirely via environment variables:

```text
Email Request (to, subject, template)
         │
         ▼
EmailService::send()
         │
         ├─── [RESEND_API_KEY set] ──► REST API (https://api.resend.com/emails) ──► Fast Delivery
         │
         ├─── [SMTP Configured]   ──► PHPMailer TLS Connection (Port 587/465)   ──► Traditional SMTP
         │
         └─── [Dev / Test Mode]   ──► Local Append to storage/logs/mail.log      ──► Zero Outbound Leakage
```

---

## 2. Transactional Workflows

1. **Password Reset (Customer & Staff)**: Secure SHA-256 tokens with 1-hour expiration.
2. **Order Confirmation & Digital Receipt**: Itemized list, subtotal, zero-VAT verification, and order tracking link.
3. **Delivery Dispatch**: Driver contact information and estimated arrival notifications.
4. **Low Inventory & Expiry Alerts**: Automated administrative notifications when inventory drops below threshold.

---

## 3. Template Standards

All emails utilize responsive, table-based HTML layouts tested across Apple Mail, Gmail, Outlook (Mobile/Desktop), and Yahoo Mail with automatic plain-text fallbacks.
