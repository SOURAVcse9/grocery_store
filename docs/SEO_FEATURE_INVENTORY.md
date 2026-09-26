# Search Engine Optimization (SEO) Feature Inventory

## 1. Technical SEO Strategy Overview

The GroCo Grocery Store public platform is architected for maximum search visibility across Google, Bing, and social graph crawlers (Open Graph / Twitter Cards). It features automated server-side meta generation, structured JSON-LD data graphs, XML sitemaps with image indexing, and dynamic canonicalization.

---

## 2. Core SEO Modules & Capabilities

### 2.1 Metadata Engine (`public/includes/seo.php`)
Every public route dynamically generates targeted metadata:
- **Title Tag Formulation**:
  - Homepage: `GroCo - Online Grocery Shopping in Bangladesh | Fast Home Delivery`
  - Category: `{Category Name} - Fresh Groceries Online | GroCo`
  - Product: `{Product Name} - Price: ৳{Price} | Buy Online | GroCo`
- **Meta Description**:
  - Truncates product description to optimal length (155–160 chars).
  - Automatically appends price, availability, and unit.
- **Canonical URLs**:
  - Clean canonical URLs stripping tracking query parameters (e.g. `?utm_source=...`, `?ref=...`).
  - Strict protocol matching (`https://groco.site.je/...`).

---

### 2.2 Structured Data / Schema.org JSON-LD

#### A. Organization & WebSite Graph (Sitewide)
```json
{
  "@context": "https://schema.org",
  "@graph": [
    {
      "@type": "Organization",
      "@id": "https://groco.site.je/#organization",
      "name": "GroCo Grocery Store",
      "url": "https://groco.site.je",
      "logo": "https://groco.site.je/assets/images/logo.png",
      "contactPoint": {
        "@type": "ContactPoint",
        "telephone": "+8801700000000",
        "contactType": "customer service",
        "areaServed": "BD",
        "availableLanguage": ["English", "Bengali"]
      }
    },
    {
      "@type": "WebSite",
      "@id": "https://groco.site.je/#website",
      "url": "https://groco.site.je",
      "name": "GroCo",
      "potentialAction": {
        "@type": "SearchAction",
        "target": "https://groco.site.je/search.php?q={search_term_string}",
        "query-input": "required name=search_term_string"
      }
    }
  ]
}
```

#### B. Product Schema (`public/product-details.php`)
```json
{
  "@context": "https://schema.org/",
  "@type": "Product",
  "name": "Aarong Dairy Pure Ghee 400g",
  "image": [
    "https://groco.site.je/uploads/products/aarong_ghee_400g.webp"
  ],
  "description": "Premium pure cow milk ghee by Aarong Dairy.",
  "sku": "GRO-GHEE-001",
  "brand": {
    "@type": "Brand",
    "name": "Aarong"
  },
  "offers": {
    "@type": "Offer",
    "url": "https://groco.site.je/product-details.php?id=12",
    "priceCurrency": "BDT",
    "price": "540.00",
    "itemCondition": "https://schema.org/NewCondition",
    "availability": "https://schema.org/InStock",
    "seller": {
      "@type": "Organization",
      "name": "GroCo Grocery Store"
    }
  },
  "aggregateRating": {
    "@type": "AggregateRating",
    "ratingValue": "4.8",
    "reviewCount": "24"
  }
}
```

#### C. BreadcrumbList Schema
Enables Google rich breadcrumbs in search engine results pages (SERPs):
`Home > Dairy & Eggs > Ghee & Butter > Aarong Dairy Pure Ghee`

---

### 2.3 XML Sitemap Engine (`public/sitemap.xml` / `public/sitemap.php`)
- **Automated Generation**: Queries active categories, brands, and products.
- **Image Extensions**: Includes `<image:image>` tags with caption and title for Google Image Search indexing.
- **Priority Matrix**:
  - Homepage: `1.0` (daily)
  - Categories: `0.8` (weekly)
  - Products: `0.7` (daily)
  - Static Pages (About, Contact): `0.5` (monthly)

---

### 2.4 Robots.txt Configuration
```txt
User-agent: *
Allow: /
Disallow: /admin/
Disallow: /public/api/
Disallow: /checkout.php
Disallow: /cart.php
Disallow: /account/
Disallow: /login.php
Disallow: /register.php

Sitemap: https://groco.site.je/sitemap.xml
```

---

## 3. SEO Checklist for Modernized Stack
1. **Server-Side Rendering (SSR) / Static Site Generation (SSG)**: Ensure meta tags and initial content are fully rendered in the HTML stream before hydration.
2. **Core Web Vitals Optimization**:
   - LCP (Largest Contentful Paint) $< 2.5s$.
   - CLS (Cumulative Layout Shift) $< 0.1$.
   - INP (Interaction to Next Paint) $< 200ms$.
3. **Automated Slug Generation**: Support both `id` and human-readable SEO slugs (`/product/aarong-dairy-pure-ghee-400g-12`).
