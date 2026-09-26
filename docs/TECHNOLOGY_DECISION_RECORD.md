# GroCo Grocery Store — Technology Decision Records (TDR)

**Document Version:** 1.0.0  
**Project:** GroCo Grocery Store Modernization  
**Decision Scope:** Technology Additions, Integrations, and Architectural Rejections

---

## 1. Approved Technologies

### TDR-01: Cloudinary Media Integration (`MediaService`)
- **Status:** **APPROVED & IMPLEMENTED**
- **Problem Solved:** High local server disk usage, slow mobile image loading, manual image resizing.
- **Benefit:** Automatic WebP/AVIF format conversion, responsive `srcset`, global CDN caching, dynamic cropping.
- **Fallback:** Hardened local filesystem storage governed by `public/uploads/.htaccess`.

### TDR-02: Multi-Driver Caching (`CacheService` with Redis + File Fallback)
- **Status:** **APPROVED & IMPLEMENTED**
- **Problem Solved:** Repeated database hits for static category trees, brands, settings, and navigation.
- **Benefit:** Sub-millisecond read latencies for catalog browsing; tag-based invalidation on admin edits.
- **Fallback:** Automatic in-memory and disk file caching when Redis is unconfigured or temporarily unavailable.

### TDR-03: Versioned RESTful JSON API (`/api/v1/`)
- **Status:** **APPROVED & IMPLEMENTED**
- **Problem Solved:** Fragile, unversioned AJAX endpoints scattered across public folders.
- **Benefit:** Clean JSON envelope, request ID tracking, standardized rate limiting, OpenAPI 3.0 documentation.

### TDR-04: Multi-Provider Transactional Email (`EmailService`)
- **Status:** **APPROVED & IMPLEMENTED**
- **Problem Solved:** Fragility of single SMTP server configuration and lack of modern API provider support.
- **Benefit:** Environment-driven switching between SMTP, Resend, and local test logs with responsive HTML templates.

### TDR-05: Structured JSON Observability (`LoggerService`)
- **Status:** **APPROVED & IMPLEMENTED**
- **Problem Solved:** Difficult troubleshooting in plain-text error logs.
- **Benefit:** Machine-parseable JSON lines with automatic credential redaction, request IDs, and duration timers.

---

## 2. Technologies Evaluated & Rejected (With Justification)

### TDR-06: Monolithic Framework Migration (Laravel / Symfony / NestJS)
- **Decision:** **REJECTED**
- **Justification:** Full framework migration would require rewriting working database logic, risks introducing business logic regressions in POS and cashier shifts, increases server memory consumption, and violates the preservation mandate.

### TDR-07: External Search Engine Clusters (Elasticsearch / OpenSearch / Meilisearch)
- **Decision:** **REJECTED FOR CURRENT SCALE**
- **Justification:** Current product inventory (under 50,000 SKUs) achieves sub-5ms search speeds using compound B-Tree indexes and MySQL FULLTEXT. Adding an external daemon introduces maintenance complexity, memory footprint, and synchronization failure risks without measurable user-facing benefit at this scale.

### TDR-08: Database Engine Migration (PostgreSQL / MongoDB)
- **Decision:** **REJECTED**
- **Justification:** MySQL/MariaDB InnoDB engine already guarantees ACID compliance, foreign key integrity, and row-level locking for POS and checkout transactions.
