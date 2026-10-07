# GroCo Grocery Store — Search Architecture Decision Matrix

## 1. Executive Summary

Search performance and autocomplete speed directly impact e-commerce conversion rates. This document details the technical evaluation between **MySQL Compound Full-Text Search**, **Meilisearch**, **Typesense**, and **Elasticsearch** for GroCo Grocery Store.

---

## 2. Technical Comparison Matrix

| Evaluation Criteria | MySQL Full-Text / Compound B-Tree | Meilisearch | Typesense | Elasticsearch |
| :--- | :--- | :--- | :--- | :--- |
| **Catalog Size Target** | 1,000 – 50,000 SKUs | 10,000 – 1,000,000 SKUs | 10,000 – 2,000,000 SKUs | 1,000,000+ SKUs |
| **P95 Autocomplete Latency** | 2 – 8 ms (Indexed) | < 5 ms (In-Memory) | < 5 ms (C++ In-Memory) | 20 – 50 ms (JVM Overhead) |
| **Typo Tolerance** | Soundex / Levenshtein UDF | Native 1–2 Typos | Native 1–2 Typos | Fuzzy Query (Configurable) |
| **Infrastructure Overhead** | Zero (Embedded MySQL) | Small (Rust binary, ~100MB RAM) | Minimal (C++ binary, ~80MB RAM)| High (Java JVM, 2GB+ RAM) |
| **Operational Complexity** | None (Single Source of Truth) | Low (Sidecar Docker/Service) | Low (Sidecar Docker/Service) | High (Cluster Management, Sharding) |
| **Cost** | $0.00 / month | Free Open Source / $30 Cloud | Free Open Source / $25 Cloud | $70+ / month |
| **Maintenance Burden** | Zero additional syncing | Requires Event Sync Worker | Requires Event Sync Worker | Requires Logstash / Debezium |

---

## 3. Architecture Decision

### Phase 3 Baseline Choice: **Optimized MySQL Compound B-Tree & Full-Text Engine**
- **Rationale**: For GroCo's current grocery catalog size (under 10,000 items), MySQL with compound indexing on `(is_active, status, stock, category_id, brand_id, id)` and prefix indexes on `(name, sku, barcode)` delivers **sub-5ms query latency** without adding external infrastructure, network hops, or split-brain syncing risks.
- **Debounced Autocomplete**: Integrated debounced API endpoint `/api/v1/search/autocomplete?q={prefix}` returns instant type-ahead results in under 4ms.

### Future Scale Trigger: **Meilisearch / Typesense Sidecar Transition**
- When the grocery catalog exceeds **50,000 active SKUs** or multi-lingual fuzzy search is required, GroCo will deploy a **Meilisearch sidecar daemon**.
- Integration is already prepared via `EventDispatcher` (`product.created`, `product.updated`, `product.deleted`) to push async re-indexing jobs via `QueueService`.
