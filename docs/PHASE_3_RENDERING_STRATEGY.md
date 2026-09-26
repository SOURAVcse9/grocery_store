# GroCo Grocery Store — Rendering & Hydration Strategy

## 1. Overview

Next.js 14 App Router enables granular hybrid rendering strategies per route. This document outlines the rendering method for every storefront view to balance instantaneous response times with real-time stock accuracy.

---

## 2. Route Rendering Matrix

| Route Path | View / Feature | Strategy | Cache / Revalidation | Reason |
| :--- | :--- | :--- | :--- | :--- |
| `/` | Homepage & Hero | **ISR** | `revalidate: 60s` | High traffic, static category pills, cached featured grid |
| `/products` | Catalog & Facets | **SSR / ISR** | `revalidate: 60s` | SEO indexable, filters applied on client with URL sync |
| `/products/[slug]` | Product Details | **ISR** | `revalidate: 30s` | Rich Schema.org JSON-LD, fast load, responsive gallery |
| `/cart` | Shopping Cart | **Client (CSR)** | `cache: no-store` | Highly dynamic per session, quantity mutations |
| `/checkout` | Order Placement | **Client (CSR)** | `cache: no-store` | Secure session, real-time stock reservation, payment |
| `/account` | Customer Profile | **Client (CSR)** | `cache: no-store` | Strict authentication check, user profile data |
| `/account/orders` | Order History | **Client (CSR)** | `cache: no-store` | Customer-isolated order records |
| `/account/orders/[id]`| Order Detail | **Client (CSR)** | `cache: no-store` | IDOR-protected specific order line items |
| `/login` & `/register` | Authentication | **Static (SSG)** | Pre-rendered | Fast form render with client-side submission |

---

## 3. Hydration & Performance Optimization

- **Selective Server Component Bundling**: Static page shells (Header, Footer, Product Grid structure) are generated on the server with zero client JS overhead.
- **Client Boundary Isolation**: Interactive widgets (`Header` search autocomplete dropdown, cart quantity controls, image gallery thumbnail switcher) are marked `'use client'` to minimize JavaScript payload sent over the wire.
- **On-Demand Cache Invalidation**: When an admin updates product pricing or stock via Admin ERP or POS, `EventDispatcher` and Next.js revalidation tag triggers purge the corresponding product ISR cache automatically.
