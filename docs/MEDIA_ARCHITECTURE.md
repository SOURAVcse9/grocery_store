# GroCo Grocery Store — Modern Media & Image Architecture

**Document Version:** 1.0.0  
**Target System:** GroCo Grocery Store  
**Author:** GroCo Architecture & Modernization Engineering  
**Service Class:** `MediaService` (`public/includes/MediaService.php`)

---

## 1. Overview & Objectives

The GroCo media subsystem provides a unified, secure, high-performance image ingestion and delivery pipeline. It supports cloud-native CDN delivery via Cloudinary with dynamic WebP/AVIF format optimization and responsive srcset generation while maintaining a zero-dependency, secure local filesystem fallback.

---

## 2. Asset Classification Model

To maintain strict security and compliance, files are categorized prior to ingestion:

| Classification | Examples | Storage Engine | Public Access |
| :--- | :--- | :--- | :--- |
| **Public Storefront Media** | Product photos, Category tiles, Brand logos, Promotional hero banners, Customer review photos | **Cloudinary CDN / Public Uploads** | Allowed (via CDN / WebP/AVIF) |
| **User Identity Media** | Customer avatars, staff profile pictures | **Cloudinary CDN / Public Uploads** | Allowed |
| **Sensitive Documents** | Financial invoices, thermal receipt templates, tax reports | **Protected Local Storage (`storage/`)** | Authenticated session only |
| **System & Authority Secrets** | Licensing keys (`*.key`, `*.pem`), SQLite authority DBs | **Excluded (`licensing_server/data/`)** | Zero HTTP access (`Require all denied`) |
| **Backups & Database Dumps** | Automated `.sql` snapshots | **Encrypted Local Storage (`storage/backups/`)** | Zero HTTP access |

---

## 3. Folder Hierarchy & Cloudinary Structure

All public assets are systematically organized into distinct folder namespaces:

```text
groco/
├── products/      # Catalog imagery, primary photos, thumbnail galleries
├── categories/    # Category hierarchy cards and icons
├── brands/        # Manufacturer and brand logos
├── banners/       # Homepage promotional carousels & deal banners
├── reviews/       # Customer verified delivered review photo uploads
└── avatars/       # Customer & staff profile icons
```

---

## 4. Ingestion & Server-Side Security Model

Every incoming file undergoes strict multi-point verification before persistent write:

```text
Upload Request ($_FILES)
       │
       ▼
1. Parameter Validation (Check error codes, size limit <= 5MB)
       │
       ▼
2. MIME Type Verification (finfo_file against ALLOWED_MIME_TYPES)
       │
       ▼
3. Binary Header Inspection (getimagesize to reject polyglots)
       │
       ▼
4. Credential & Driver Check
      ├── [Cloudinary Configured] ──► SHA-1 Signed API Upload ──► Cloudinary CDN URL
      └── [Local Fallback]         ──► Cryptographic Rename (bin2hex) ──► public/uploads/.htaccess
```

### Security Safeguards:
- **No Client-Side Credential Exposure**: `CLOUDINARY_API_SECRET` and API Keys are strictly handled server-side.
- **Signed Uploads**: All REST payloads use timestamped SHA-1 signatures.
- **Local Fallback Hardening**: The local upload directory is governed by `public/uploads/.htaccess` with `Options -Indexes -ExecCGI` and `php_flag engine off` to prevent script execution.

---

## 5. Modern Image Delivery & Responsive Srcsets

The `MediaService` generates responsive markup and auto-negotiates modern formats:

```html
<!-- Example Output from MediaService::renderResponsiveImage() -->
<img 
  src="https://res.cloudinary.com/groco/image/upload/w_600,f_auto,q_auto/groco/products/organic_honey.jpg" 
  srcset="https://res.cloudinary.com/groco/image/upload/w_300,c_fill,f_auto,q_auto/groco/products/organic_honey.jpg 300w,
          https://res.cloudinary.com/groco/image/upload/w_600,c_fill,f_auto,q_auto/groco/products/organic_honey.jpg 600w,
          https://res.cloudinary.com/groco/image/upload/w_900,c_fill,f_auto,q_auto/groco/products/organic_honey.jpg 900w"
  sizes="(max-width: 640px) 100vw, (max-width: 1024px) 50vw, 33vw"
  alt="Organic Honey — buy fresh groceries online"
  width="600"
  height="600"
  loading="lazy"
  decoding="async"
>
```

---

## 6. Migration & Deletion Lifecycle

- **Soft Migration**: Existing relative database paths (e.g. `uploads/products/rice.webp` or `dada rice.webp`) are automatically detected and delivered seamlessly via local fallback without requiring breaking database changes.
- **Deletion**: When a product or banner is removed in the admin portal, `MediaService::delete()` purges the asset from Cloudinary (via Admin destroy API) or unlinks the file from disk with `basename()` path traversal defense.
