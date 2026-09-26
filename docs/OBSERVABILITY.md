# GroCo Grocery Store — Observability & Logging Architecture

**Document Version:** 1.0.0  
**Target System:** GroCo Grocery Store  
**Service Class:** `LoggerService` (`public/includes/LoggerService.php`)

---

## 1. Structured JSON Logging

All system events, access logs, and application exceptions are recorded in machine-readable JSON Lines (`.log`) format inside `storage/logs/`:

```json
{
  "timestamp": "2026-09-26T22:30:15+06:00",
  "level": "INFO",
  "request_id": "req_84f9b28a91c0e3a47b6a",
  "duration_ms": 14.85,
  "route": "/checkout.php",
  "ip": "192.168.1.100",
  "user_id": 194,
  "admin_id": null,
  "message": "Order #ORD-20260926-9921 placed successfully",
  "context": {
    "order_id": 482,
    "payment_method": "cod",
    "total": 1450.00
  }
}
```

---

## 2. Automatic Secret Redaction Policy

The `LoggerService` scans every context payload and strictly redacts credentials before writing to disk:
- Passwords (`password`, `password_confirmation`)
- Security Tokens (`token`, `csrf_token`, `auth`, `cookie`)
- API Secrets (`api_key`, `secret`, `private_key`)
- Payment Card Data (`card_number`, `cvv`)

---

## 3. Log Rotation & SIEM Ingestion

- Logs are split daily (`app_YYYY-MM-DD.log`).
- Critical security events are simultaneously written to `storage/logs/security.log`.
- Format is 100% compatible with Elasticsearch, Fluentd, Grafana Loki, and Logstash.
