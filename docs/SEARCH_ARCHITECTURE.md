# GroCo Grocery Store — Modern Search Architecture

**Document Version:** 1.0.0  
**Target System:** GroCo Grocery Store  
**Service Class:** `SearchService` (`public/includes/SearchService.php`)

---

## 1. Engine Evaluation & Architectural Decision

### Evaluation Matrix:
- **Meilisearch / Typesense**: Excellent performance for millions of documents, but requires running independent background daemon processes, memory overhead, and separate syncing workers.
- **MySQL FULLTEXT + Indexed Faceting**: Zero extra infrastructure dependencies, sub-5ms response times on typical retail catalogs (1,000 to 50,000 items), ACID transaction safety, and native PDO parameterization.

### Architectural Decision:
We implement **MySQL FULLTEXT with B-Tree Compound Indexes** and phonetic normalization via `SearchService`, with pluggable adapter capability for external search clusters when catalog size exceeds 250,000 SKUs.

---

## 2. Autocomplete & Faceted Filtering Pipeline

```text
User Typing (e.g. "org mi")
         │
         ▼
Debounced Frontend Dispatch (300ms)
         │
         ▼
GET /api/v1/search/autocomplete?q=org%20mi
         │
         ├──► L1 Cache Check (autocomplete_<md5>) ──► Return Instant JSON (sub-1ms)
         │
         └──► [Cache Miss] ──► Query Active Catalog (name, sku, price, stock)
                                └── Cache for 300s (TTL_SHORT)
```

---

## 3. Relevance Scoring Rules

1. **Title Exact Match**: 100 points
2. **SKU / Barcode Match**: 80 points
3. **Category / Brand Match**: 50 points
4. **Description Substring**: 20 points
5. **Rating Multiplier**: Products with higher star ratings and delivered review counts receive elevated priority in default sort orders.
