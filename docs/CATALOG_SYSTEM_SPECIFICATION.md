# GroCo Grocery Store — Product Catalog System Specification

This document provides a comprehensive database and operational mapping of the product catalog, categories hierarchy, brand registry, pricing mechanisms, inventory thresholds, gallery media, and SEO attributes.

---

## 1. Product Data Schema & Attribute Mapping

| Field / Column | Data Type | Database Table | Description & Usage |
| :--- | :--- | :--- | :--- |
| `id` | `INT(11) AUTO_INCREMENT` | `products` | Primary Key uniquely identifying product. |
| `name` | `VARCHAR(255)` | `products` | Full commercial product name (e.g. *"Fresh Organic Deshi Onion 1kg"*). |
| `slug` | `VARCHAR(255) UNIQUE` | `products` | URL-safe identifier for SEO routes (`product.php?slug=...`). |
| `category_id` | `INT(11) NULL` | `products` | Foreign Key referencing `categories.id`. |
| `brand_id` | `INT(11) NULL` | `products` | Foreign Key referencing `brands.id`. |
| `sku` | `VARCHAR(50) UNIQUE` | `products` | Stock Keeping Unit code (e.g. `GROCO-VEG-001`). Used for barcode search and inventory tracking. |
| `barcode` | `VARCHAR(100) NULL` | `products` | UPC / EAN-13 physical product barcode scanned at POS register. |
| `cost_price` | `DECIMAL(10,2)` | `products` | Wholesale procurement/acquisition cost per unit. Used for profit calculation and inventory valuation. |
| `price` | `DECIMAL(10,2)` | `products` | Regular retail selling price in Bangladeshi Taka (BDT ৳). |
| `discount_price` | `DECIMAL(10,2) NULL` | `products` | Promotional sale price. If set and `< price`, system computes discount percentage. |
| `unit` | `VARCHAR(50)` | `products` | Measurement unit (e.g. `1 kg`, `500 gm`, `1 L`, `12 pcs`, `1 Pack`, `500 ml`). |
| `weight` | `DECIMAL(8,2) NULL` | `products` | Physical weight in kilograms used for shipping calculation. |
| `stock` | `INT(11) DEFAULT 0` | `products` | Live available physical inventory quantity. Auto-decremented on checkout / POS sale. |
| `min_stock` | `INT(11) DEFAULT 5` | `products` | Re-order alert threshold. If `stock <= min_stock`, item triggers Low Stock warning. |
| `thumbnail` | `VARCHAR(255) NULL` | `products` | Relative path to main primary product image in `public/uploads/products/`. |
| `short_description` | `VARCHAR(500) NULL` | `products` | Brief highlight summary displayed on cards and search snippets. |
| `description` | `TEXT NULL` | `products` | Full rich HTML product details, ingredients, nutritional facts, and usage instructions. |
| `is_featured` | `TINYINT(1) DEFAULT 0` | `products` | Flag (1/0) indicating featured placement on homepage. |
| `is_trending` | `TINYINT(1) DEFAULT 0` | `products` | Flag (1/0) indicating trending/popular badge. |
| `is_flash_sale` | `TINYINT(1) DEFAULT 0` | `products` | Flag (1/0) indicating inclusion in timed flash sales section. |
| `is_active` | `TINYINT(1) DEFAULT 1` | `products` | Master active visibility toggle. Inactive products are hidden from storefront. |
| `status` | `VARCHAR(50) DEFAULT 'active'` | `products` | Product lifecycle status (`active`, `draft`, `archived`). |
| `avg_rating` | `DECIMAL(3,2) DEFAULT 0.00`| `products` | Cached average star rating (1.00 to 5.00) computed from approved `product_reviews`. |
| `review_count` | `INT(11) DEFAULT 0` | `products` | Total approved customer review count. |
| `meta_title` | `VARCHAR(255) NULL` | `products` | Custom SEO page title for Google indexing. |
| `meta_description`| `TEXT NULL` | `products` | Custom SEO meta description tag. |
| `created_at` | `TIMESTAMP` | `products` | Record creation timestamp. |
| `updated_at` | `TIMESTAMP` | `products` | Last modification timestamp. |
| `deleted_at` | `TIMESTAMP NULL` | `products` | Soft deletion timestamp. |

---

## 2. Product Gallery Images (`product_images`)

* **Table:** `product_images`
* **Columns:**
  * `id` (`INT(11) AUTO_INCREMENT`)
  * `product_id` (`INT(11)` &rarr; `products.id`)
  * `image_url` (`VARCHAR(255)` &mdash; image filename in `public/uploads/products/`)
  * `sort_order` (`INT(11) DEFAULT 0` &mdash; display order in thumbnail carousel)
  * `created_at` (`TIMESTAMP`)

---

## 3. Categories Hierarchy (`categories`)

* **Table:** `categories`
* **Columns:**
  * `id` (`INT(11) AUTO_INCREMENT`)
  * `parent_id` (`INT(11) NULL` &rarr; `categories.id` &mdash; defines parent/child subcategory trees)
  * `name` (`VARCHAR(100)` &mdash; e.g. *"Fruits & Vegetables"*, *"Dairy & Eggs"*, *"Cooking Essentials"*)
  * `slug` (`VARCHAR(150) UNIQUE` &mdash; e.g. `fruits-vegetables`)
  * `image` (`VARCHAR(255) NULL` &mdash; category thumbnail in `public/uploads/categories/`)
  * `icon` (`VARCHAR(100) NULL` &mdash; FontAwesome / SVG icon class)
  * `description` (`TEXT NULL`)
  * `is_active` (`TINYINT(1) DEFAULT 1`)
  * `created_at`, `updated_at`

---

## 4. Brand Registry (`brands`)

* **Table:** `brands`
* **Columns:**
  * `id` (`INT(11) AUTO_INCREMENT`)
  * `name` (`VARCHAR(100)` &mdash; e.g. *"Pran"*, *"Square"*, *"Teer"*, *"Fresh"*, *"Nestle"*, *"Aarong"*)
  * `slug` (`VARCHAR(150) UNIQUE`)
  * `logo` (`VARCHAR(255) NULL` &mdash; logo image in `public/uploads/brands/`)
  * `description` (`TEXT NULL`)
  * `is_active` (`TINYINT(1) DEFAULT 1`)
  * `created_at`, `updated_at`

---

## 5. Pricing & Discount Calculations

* **Regular Price:** Represented by `products.price`.
* **Sale / Discount Price:** Represented by `products.discount_price`.
* **Effective Selling Price Formula:**
  $$\text{Effective Price} = \begin{cases} \text{discount\_price} & \text{if } \text{discount\_price} > 0 \text{ and } \text{discount\_price} < \text{price} \\ \text{price} & \text{otherwise} \end{cases}$$
* **Discount Percentage Formula:**
  $$\text{Discount \%} = \text{round}\left( \frac{\text{price} - \text{discount\_price}}{\text{price}} \times 100 \right)$$
* **Profit Margin per Unit Formula:**
  $$\text{Unit Profit (BDT)} = \text{Effective Selling Price} - \text{cost\_price}$$
  $$\text{Profit Margin \%} = \left( \frac{\text{Unit Profit}}{\text{Effective Selling Price}} \right) \times 100$$

---

## 6. Curated Product Relations

* **Related Products:**
  * Auto-queried from the same `category_id` excluding the current product ID:
    `SELECT * FROM products WHERE category_id = :cat_id AND id != :current_id AND is_active = 1 AND deleted_at IS NULL ORDER BY id DESC LIMIT 4`.
* **Frequently Bought Together (FBT) Bundles:**
  * Queries complimentary products within the same parent department to create a one-click bundled purchase discount.
* **Recently Viewed History:**
  * Stored in session array `$_SESSION['recently_viewed'] = [id1, id2, id3, id4, id5]`.
