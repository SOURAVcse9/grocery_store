# GROCO ENTERPRISE — POS V2 TERMINAL IMAGE RESOLUTION & RESPONSIVE LAYOUT REPORT

**Document ID:** `docs/POS_IMAGE_AND_LAYOUT_FIXES.md`  
**Date:** October 15, 2026  
**Target File:** `admin/pos/index.php`, `admin/assets/js/pos.js`  
**Status:** **FULLY IMPLEMENTED & VERIFIED**

---

## 1. Overview & Problem Diagnosis

Following the deployment of the POS V2 terminal interface, an audit of the cashier experience on various screen sizes and input devices revealed visual and ergonomic issues:

### A. Product Image Presentation & Broken Icons
1. **Aggressive Cropping & Distortion:** Image elements in product cells and cart rows used `object-fit: cover`. For grocery packaging (bottles, cereal boxes, bags of flour, fresh produce), `cover` cropped off brand logos, labels, and net weight indicators.
2. **Missing Media Files in Seed Data:** The database contained 24 active products. 5 products had real uploaded image files, while 19 products contained legacy demo filenames (e.g. `products/broccoli.jpg`, `products/milk.jpg`) that were not present on disk.
3. **Defective Cart Fallback Path:** In `admin/pos/index.php`, the cart item JavaScript template contained:
   ```javascript
   const fallbackImg = '<?= BASE_URL ?>/../admin/assets/images/placeholder.png';
   ```
   This path did not exist on disk, resulting in browser broken image icons whenever an item with a missing upload was added to the cart.
4. **Lack of Dynamic `onerror` Traps:** Without client-side `onerror` fallback handlers, any temporary network glitch or missing thumbnail left empty broken image boxes.

### B. Layout Clipping & Rigid Viewport Scaling
1. **Hardcoded Inline Grid Ratio:** The main terminal was styled with `style="display:grid; grid-template-columns: 1.55fr 1fr; height: 600px;"`. On standard supermarket touchscreen monitors (1280x720 or 1366x768) and iPads, this ratio squashed catalog cards and broke layout symmetry.
2. **Cart Footer Clipping:** The cart container was restricted to a fixed `height: 600px` with `justify-content: space-between`. Inside it, `#posActiveCartList` was hardcoded to `max-height: 190px`. When cashiers added 4 or more items, the calculations, discount chips, total payable, and "Pay & Print (F6)" button were pushed down and clipped off the bottom edge.
3. **Cramped Touch Targets:** Quantity increment/decrement buttons were 26x26px, making them prone to miss-taps on capacitive touch screens.
4. **Category Tabs Wrapping:** The retail departmental pills lacked flex-shrink prevention and touch-optimized scroll momentum.

---

## 2. Engineering Architecture & Fixes

### 1. Two-Tier Resilient Image System

| Level | Implementation | Behavior |
|---|---|---|
| **Server-Side** | `image_url($p['thumbnail'], 'products')` | Checks uploaded storage paths, falls back gracefully to `public/assets/images/products/placeholder.svg`. |
| **Client-Side (Grid)** | `onerror="this.onerror=null;this.src='<?= e(image_url('', 'products')) ?>';"` | Instantly swaps broken URLs to local SVG placeholder without layout shift. |
| **Client-Side (Cart)** | `fallbackImg = window.POS_PLACEHOLDER_IMG; onerror="this.onerror=null;this.src='${fallbackImg}';"` | Passes globally configured placeholder to all dynamically rendered cart rows. |
| **Client-Side (Autocomplete)** | `pImg = p.image \|\| window.POS_PLACEHOLDER_IMG; onerror="this.onerror=null;this.src=window.POS_PLACEHOLDER_IMG;"` | Renders clean 30x30 product thumbnails in real-time search dropdown. |

#### Aspect Ratio & Object-Fit Standardization:
- Switched from `object-fit: cover` to `object-fit: contain`.
- Wrapped images in dedicated container boxes:
  - Product grid: `.pos-product-img-box` (76px height, neutral white background, subtle border, flexbox centered).
  - Cart row: 38x38px box with 2px internal padding and rounded corners.
- Preserves full product packaging, brand identity, and barcodes without distortion.

---

### 2. Enterprise Responsive Layout Structure (`.pos-terminal-layout`)

```
+-------------------------------------------------------------------------------+
| TOPBAR & NAVIGATION (Terminal, Camera Scan, Drawer, Hold, QR Labels)          |
+-------------------------------------------------------------------------------+
| .pos-terminal-layout                                                          |
| +-----------------------------------------+ +-------------------------------+ |
| | LEFT: Product Selector (Catalog Grid)   | | RIGHT: Sticky Cart Panel      | |
| | - Department Tabs (Produce, Dairy, etc.)| | - Customer & Loyalty Widget   | |
| | - Live Search & Scanner (F1)            | | - #posActiveCartList          | |
| | - Category & Brand Selectors            | |   (Dynamic flex-grow scroll)  | |
| | - #posCatalogGrid                       | | - .pos-cart-footer            | |
| |   (minmax(130px, 1fr) auto-fill)        | |   * Presets & Overrides       | |
| |                                         | |   * Grand Total Due           | |
| |                                         | |   * Hold (F3) & Pay (F6)      | |
| +-----------------------------------------+ +-------------------------------+ |
+-------------------------------------------------------------------------------+
```

#### Media Query Breakpoint Matrix:
- **Desktop (>= 1201px):**
  - Grid: `grid-template-columns: minmax(0, 1.45fr) minmax(360px, 1fr);`
  - Gap: `16px`
  - Cart Panel: `position: sticky; top: 75px; max-height: calc(100vh - 110px);`
- **Medium Screen / Laptop (992px – 1200px):**
  - Grid: `grid-template-columns: minmax(0, 1.25fr) minmax(340px, 1fr);`
  - Gap: `12px`
- **Tablet / Mobile (<= 991px):**
  - Grid: `grid-template-columns: 1fr;` (stacked layout)
  - Cart Panel: `position: static; max-height: none;`
  - Cart List: `max-height: 340px;`

---

### 3. Touch Target & Ergonomic Enhancements
- **Steppers:** Quantity buttons enlarged to 28x28px (`.pos-qty-btn`) with bold typography, subtle borders, and `touch-action: manipulation;`. Tap feedback uses `transform: scale(0.92)`.
- **Department Tabs:** Added `-webkit-overflow-scrolling: touch; scrollbar-width: thin;` and `flex-shrink: 0;` on `.pos-dept-btn` to guarantee tabs never squash or wrap into uneven broken lines.
- **Cart Footer Isolation:** Wrapped discount presets, manual overrides, total due, and payment triggers in `.pos-cart-footer` with `flex-shrink: 0; margin-top: auto;`. The cart list scrolls independently, ensuring action buttons never drop below the fold.

---

## 3. Verification & Test Evidence

1. **CLI Render Test (`scratch/test_pos_index.php`):**
   - Successfully rendered 86,614 bytes of valid HTML.
   - Verified existence of all modern classes:
     - `pos-terminal-layout`: PASS
     - `pos-cart-panel`: PASS
     - `pos-product-img-box`: PASS
     - `window.POS_PLACEHOLDER_IMG`: PASS
     - `pos-dept-btn`: PASS
     - `posActiveCartList`: PASS
     - `pos-cart-footer`: PASS

2. **Automated Regression Suite (`tests/pos_v2_regression_test.php`):**
   - Test #10: `image_url() resolves valid existing SVG placeholder asset for missing/empty product images` — **PASS**.
   - Test #11: `POS terminal has responsive layout, contain-fit images, and sticky non-clipping cart` — **PASS**.
