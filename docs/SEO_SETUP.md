# GroCo Grocery Store — Production SEO & Indexing Infrastructure Guide

This document provides step-by-step instructions for verifying and maintaining the search engine optimization (SEO), Google Search Console configuration, XML sitemaps, and structured data for **GroCo Grocery Store** (`https://groco.site.je`).

---

## 1. Google Search Console Setup & Domain Verification

To enable Google to index your public grocery store and monitor crawl performance:

1. Visit [Google Search Console](https://search.google.com/search-console).
2. Click **Add Property**.
3. Choose **URL prefix** and enter:
   ```text
   https://groco.site.je
   ```
4. **Verification Methods**:
   * **Method A (HTML Tag - Recommended)**:
     Copy the meta tag provided by Google (e.g. `<meta name="google-site-verification" content="..." />`) and add it to `public/components/meta-tags.php`.
   * **Method B (HTML File Upload)**:
     Download the Google verification file (e.g. `google1234567890abcdef.html`) and place it inside the `public/` directory.
   * **Method C (DNS TXT Record)**:
     Add the DNS TXT verification record at your domain provider / DNS registrar for `groco.site.je`.
5. Click **Verify**.

---

## 2. Sitemap Submission

Once verified in Google Search Console:

1. In the left navigation menu, click **Sitemaps**.
2. Under "Add a new sitemap", enter:
   ```text
   sitemap.xml
   ```
3. Click **Submit**.
4. Verify the status changes to **Success**.

> [!NOTE]
> The dynamic sitemap endpoint is accessible at `https://groco.site.je/sitemap.xml` (or `https://groco.site.je/sitemap.php`). It dynamically compiles all active categories, active brands, and in-stock products with Google Image search extensions.

---

## 3. URL Inspection & Requesting Indexing

To index specific pages immediately after launch:

1. In Google Search Console, paste any public URL into the top search bar (e.g. `https://groco.site.je/` or `https://groco.site.je/products.php?category=vegetables` or `https://groco.site.je/product.php?slug=fresh-organic-tomato`).
2. Click **Test Live URL**.
3. Confirm that:
   * **Page can be indexed**: "URL is available to Google".
   * **Product Rich Results**: "Valid Product detected".
   * **Breadcrumbs**: "Valid Breadcrumbs detected".
4. Click **Request Indexing**.

---

## 4. Product Structured Data (Schema.org JSON-LD) Validation

All product pages render Schema.org `Product`, `Offer`, `BreadcrumbList`, and `AggregateRating` metadata directly in server-rendered HTML.

### Validation Tools:
* **Google Rich Results Test**: [https://search.google.com/test/rich-results](https://search.google.com/test/rich-results)
* **Schema Markup Validator**: [https://validator.schema.org](https://validator.schema.org)

### Fields Emitted Automatically for Products:
* `@type`: `Product`
* `name`: Product Name
* `image`: Absolute URL(s) to product gallery and thumbnail
* `description`: Sanitized description text
* `sku`: Store SKU or `GROCO-PRD-{id}`
* `brand`: Brand Name
* `category`: Product Category
* `offers`:
  * `priceCurrency`: `BDT`
  * `price`: Regular or Discount price
  * `availability`: `https://schema.org/InStock` or `OutOfStock`
  * `itemCondition`: `https://schema.org/NewCondition`
  * `seller`: GroCo Grocery Store
* `aggregateRating`: Emitted only when real approved reviews exist in the database (average rating and review count).
* `review`: Real customer reviews approved in database.

---

## 5. Robots.txt & Crawling Rules

The production `robots.txt` is located at `https://groco.site.je/robots.txt`:

* **Allowed**:
  * Homepage (`/`, `/index.php`)
  * Catalog & Categories (`/products.php`, `/categories.php`, `/offers.php`)
  * Product details (`/product.php`)
  * Public Static Pages (`/about.php`, `/contact.php`, `/faq.php`, `/privacy.php`, `/terms.php`)
  * Assets & Uploads (`/assets/`, `/uploads/`)
* **Disallowed (Protected from Index Bloat & Security)**:
  * `/admin/` (Administrative dashboard)
  * `/storage/`, `/database/`, `/tests/`, `/licensing_server/`
  * Private session endpoints: `/account.php`, `/cart.php`, `/checkout.php`, `/orders.php`, `/wishlist.php`
  * Authentication endpoints: `/login.php`, `/register.php`, `/forgot-password.php`, `/reset-password.php`
* **Internal Search Results**:
  * Internal search queries (`/products.php?q=...` or `/search.php`) automatically receive `<meta name="robots" content="noindex, follow">` to prevent thin/duplicate content index bloat.

---

## 6. Maintenance Workflow for New Products & Categories

When adding new products or categories via the Admin Panel:

1. **Product Name & Slug**: Ensure a unique, descriptive slug is saved (e.g. `fresh-deshi-onion-1kg`).
2. **Category Assignment**: Assign the product to its correct parent category.
3. **Product Images**: Upload clean, high-resolution product images. The system automatically creates descriptive `alt` tags and Google Image sitemap tags.
4. **Automatic Sitemap Refresh**: The XML sitemap (`/sitemap.php` and `/sitemap.xml`) automatically reflects new in-stock products on the next search engine crawl.
5. **Fast-Track Indexing**: For high-priority new arrivals, paste the product URL into Google Search Console's URL Inspection tool and click "Request Indexing".

---

## 7. Troubleshooting Common Search Engine Indexing Issues

| Issue in Search Console | Root Cause | Solution |
| :--- | :--- | :--- |
| **"Duplicate without user-selected canonical"** | Search engine found parameters (e.g. `?sort=price_asc&rating=5`). | GroCo's canonical system automatically specifies clean canonical URLs on all product/category pages. |
| **"Excluded by 'noindex' tag"** | Seen on search query pages, cart, login, or checkout. | This is intentional by design to protect private customer data and avoid search index bloat. |
| **"Blocked by robots.txt"** | URL matches administrative or checkout disallow rules. | Verify the URL is not a private/admin route. Public storefront routes are fully allowed. |
| **"Page with redirect"** | Requesting HTTP instead of HTTPS or `/public/` subfolder. | Always inspect using canonical domain `https://groco.site.je/...`. |
