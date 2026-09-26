# GroCo Grocery Store — Customer Storefront Feature Inventory

This document provides a comprehensive technical inventory of all customer-facing pages, user interactions, database dependencies, business rules, and API operations in the GroCo Grocery Store storefront.

---

## 1. Storefront Homepage

* **Page Name:** Storefront Homepage
* **URL / Path:** `/` or `/index.php` (file: `public/index.php`)
* **Purpose:** Core entry portal for online grocery shoppers; showcases promotional campaigns, categories, curated product collections, and newsletter subscriptions.
* **UI Components:**
  * Top navigation bar with live search autocomplete, language switcher (EN/BN), currency indicator (BDT ৳), cart count badge, wishlist count badge, notification drawer, and user profile avatar / dropdown.
  * Hero Banner Carousel: Auto-rotating slides with headline, promotional badge, call-to-action button.
  * Category Grid: Icon/image cards linking to category catalog pages with real-time active product counts.
  * Flash Sale Section: Promotional cards with countdown timer (hours/minutes/seconds), original vs discount pricing, discount percentage badges, and instant buy buttons.
  * Featured Products Grid: High-rated and editor-curated grocery items.
  * Today's Deals Grid: Discounted products with stock status.
  * Best Sellers Grid: Top purchased products based on historical order items count.
  * Features / Value Proposition Banner: Fast 2-Hour Delivery, 100% Organic & Fresh, Secure Cash on Delivery / Digital Payments, Easy 7-Day Returns.
  * Newsletter Subscription Card: Email capture with instant AJAX validation.
  * Footer: Store information, customer support contacts, quick links, category links, payment partner logos, copyright notice.
* **User Actions:**
  * Click category card &rarr; Navigate to filtered catalog (`products.php?category={slug}`).
  * Click product card &rarr; Navigate to product details (`product.php?slug={slug}`).
  * Click Quick View icon &rarr; Open modal with product gallery, price, quantity picker, and add to cart.
  * Click Add to Cart &rarr; AJAX dispatch to `public/ajax/cart.php`, increment cart counter, slide open cart drawer.
  * Click Add to Wishlist &rarr; AJAX dispatch to `public/ajax/wishlist.php`.
  * Click Add to Compare &rarr; AJAX dispatch to `public/ajax/compare.php`.
  * Submit Newsletter &rarr; AJAX submission to `public/ajax/newsletter.php`.
* **Backend / API Calls:**
  * `public/ajax/cart.php` (`action=add`)
  * `public/ajax/wishlist.php` (`action=toggle`)
  * `public/ajax/compare.php` (`action=add`)
  * `public/ajax/quickview.php` (`product_id={id}`)
  * `public/ajax/newsletter.php` (`email={email}`)
* **Database Tables Used:**
  * `banners`, `categories`, `products`, `product_reviews`, `coupons`, `settings`, `carts`, `cart_items`, `wishlists`, `compare_items`.
* **Authentication Requirement:** None (Public / Guest accessible).
* **Business Rules:**
  * Only active banners (`is_active = 1`) within date range (`starts_at <= NOW() <= ends_at`) are rendered.
  * Only active root categories (`parent_id IS NULL AND is_active = 1`) are displayed.
  * Only active, non-deleted products (`is_active = 1 AND deleted_at IS NULL`) are displayed in product grids.
  * Out-of-stock items (`stock <= 0`) display "Out of Stock" disabled button instead of "Add to Cart".

---

## 2. Product Catalog & Listing Page

* **Page Name:** Catalog & Filter Search
* **URL / Path:** `/products.php` (file: `public/products.php`)
* **Purpose:** Multi-facet catalog browser enabling customers to filter, sort, and search across all products.
* **UI Components:**
  * Catalog Hero Banner: Dynamic category/brand title and description.
  * Breadcrumbs navigation trail (`Home > Shop > Category`).
  * Filter Sidebar (Desktop) / Filter Drawer (Mobile):
    * Category checklist with real-time product count pills.
    * Brand checklist with count pills.
    * Price Range Slider (Min Price to Max Price in BDT).
    * Stock Availability Toggle (In Stock only).
    * On Sale / Discount Toggle (Discounted products only).
    * Minimum Star Rating Filter (1★ to 5★).
  * Catalog Toolbar:
    * Total product counter ("Showing X of Y products").
    * Sort Dropdown (`newest`, `price_asc`, `price_desc`, `popular`, `best_seller`, `rating`).
    * Grid / List view switcher.
  * Product Cards Grid: Responsive 3-column / 4-column product card layout.
  * Pagination Controls: Previous, page numbers, Next.
* **User Actions:**
  * Apply filter criteria &rarr; Triggers instant AJAX or query parameter reload.
  * Change sort order &rarr; Re-orders product query (`ORDER BY`).
  * Change page &rarr; Updates SQL `LIMIT :limit OFFSET :offset`.
  * Click quickview / add to cart / wishlist / compare.
* **Database Tables Used:**
  * `products`, `categories`, `brands`, `product_reviews`, `order_items`.
* **Business Rules:**
  * Search queries (`q=...`) search across `name`, `sku`, and `description` using prepared statements.
  * Out-of-stock items remain visible with an "Out of Stock" badge unless the "In Stock" filter is active.
  * Sale price (`discount_price`) takes precedence over regular price (`price`) when sorting by price.

---

## 3. Product Details Page

* **Page Name:** Product Details & Reviews
* **URL / Path:** `/product.php?slug={slug}` (file: `public/product.php`)
* **Purpose:** Comprehensive product view containing specifications, image zoom gallery, customer reviews, rating distribution, related products, frequently bought together bundles, and recently viewed history.
* **UI Components:**
  * Breadcrumb trail (`Home > Shop > Category > Product Name`).
  * Product Gallery: High-resolution main image container with thumbnail carousel below.
  * Product Header: Name, Brand link, Category link, SKU, Stock status pill, Average star rating + total review count link.
  * Pricing Area: Regular price, Discount price, Discount percentage badge, Unit measurement (e.g. `1 kg`, `500 gm`, `12 pcs`).
  * Purchase Actions: Quantity increment/decrement buttons, "Add to Cart" button, "Buy Now" button (adds to cart and redirects immediately to checkout), Wishlist toggle button, Compare toggle button.
  * Trust Badges: 100% Genuine Guarantee, 2-Hour Express Delivery, Safe Cash on Delivery, 7-Day Easy Return.
  * Tabbed Content Area:
    * Tab 1 (Description): Full rich-text product description.
    * Tab 2 (Specifications): Key-value product attributes (Brand, Origin, Weight, Package Type, Shelf Life).
    * Tab 3 (Customer Reviews): Rating summary card with overall average (e.g. `4.8 / 5.0`), 5-star breakdown progress bars, list of verified customer reviews with star ratings, comments, customer avatar, submission date, helpful counter, and review submission form.
  * Frequently Bought Together (FBT) Bundle Card: Displays current product + 2 related items with a combined bundle price and one-click "Add Bundle to Cart" action.
  * Related Products Slider: 4 products from the same category.
  * Recently Viewed Products Slider: Stored in customer session (last 5 viewed products).
* **Review Submission Flow:**
  * Verified Purchase Check: System inspects `orders` and `order_items` for the authenticated customer. If customer has a `delivered` order containing this product, the "Verified Purchase" badge is applied.
  * Input Form: Star rating selector (1-5 stars), review title, detailed comment, optional image uploads.
  * Status: New reviews default to `pending` status until approved in Admin Panel (`product_reviews.status = 'approved'`).
* **Database Tables Used:**
  * `products`, `product_images`, `categories`, `brands`, `product_reviews`, `users`, `orders`, `order_items`.
* **SEO & Structured Data:**
  * Emits Schema.org `Product`, `Offer`, `BreadcrumbList`, and `AggregateRating` JSON-LD.

---

## 4. Shopping Cart & Off-Canvas Cart Drawer

* **Page Name:** Shopping Cart & Mini-Cart Drawer
* **URL / Path:** `/cart.php` (file: `public/cart.php`) & off-canvas drawer (via `public/ajax/cart.php`)
* **Purpose:** View cart items, modify quantities, apply coupon codes, view free shipping eligibility, and proceed to checkout.
* **UI Components:**
  * Free Shipping Progress Bar: Shows remaining amount needed to unlock free shipping (based on `settings.site_min_order` or free delivery threshold).
  * Cart Items Table:
    * Product thumbnail + title + unit.
    * Unit price.
    * Quantity stepper (`-` / input / `+`) with stock cap enforcement.
    * Subtotal per line item (`quantity * price`).
    * Remove item button (trash icon).
  * Cart Summary Card:
    * Subtotal.
    * Coupon Code input box + "Apply Coupon" button.
    * Coupon discount line (if coupon active).
    * Estimated Delivery Fee.
    * Estimated Tax / VAT (if configured in `settings.site_tax`).
    * Grand Total in BDT (৳).
    * "Proceed to Checkout" primary action button.
    * "Continue Shopping" secondary link.
* **User Actions:**
  * Change quantity &rarr; AJAX updates `cart_items.quantity`, recalculates line total and cart summary without full page reload.
  * Remove item &rarr; Deletes row from `cart_items`, slides item out with animation.
  * Apply Coupon &rarr; AJAX dispatch to `public/ajax/coupon.php`, checks validity, stores coupon code in session.
* **Database Tables Used:**
  * `carts`, `cart_items`, `products`, `coupons`, `settings`.
* **Business Rules:**
  * Guest Cart: Tracked via `$_SESSION['guest_token']` bound to `carts.session_id`.
  * User Cart: Tracked via `$_SESSION['customer_id']` bound to `carts.user_id`.
  * On Login: Guest cart items are automatically merged into user's account cart.
  * Quantity cannot exceed `products.stock`.

---

## 5. Checkout & Order Placement

* **Page Name:** Single-Page / Step Checkout
* **URL / Path:** `/checkout.php` (file: `public/checkout.php`) & processor (`public/process_checkout.php`)
* **Purpose:** Finalize delivery address, schedule delivery time slot, choose payment method, and place confirmed order.
* **UI Components:**
  * Step 1: Customer Contact & Personal Information (Pre-filled for logged-in users).
  * Step 2: Delivery Address Selection:
    * Select from saved addresses (`addresses` table) or toggle "Add New Address".
    * Fields: Full Name, Phone Number, Alternative Phone, City/District, Area/Thana, Street Address/House/Road, Delivery Notes.
  * Step 3: Delivery Schedule Slot:
    * Date Picker: Today, Tomorrow, Select Date.
    * Time Slot Picker: Morning (8 AM - 12 PM), Afternoon (12 PM - 4 PM), Evening (4 PM - 8 PM), Express (Within 2 Hours).
  * Step 4: Payment Method Selection:
    * Cash on Delivery (COD).
    * bKash Direct / Gateway.
    * Nagad Direct / Gateway.
    * Credit / Debit Card (SSLCommerz Gateway).
  * Step 5: Order Summary Sidebar:
    * Itemized list with thumbnails, quantities, line totals.
    * Subtotal, Coupon Discount, Delivery Fee, VAT/Tax, Grand Total.
    * "Place Order" button.
* **Order Creation Workflow (`public/process_checkout.php`):**
  1. Validate CSRF token and cart item count (`cart_item_count() > 0`).
  2. Validate required address fields and phone number format (`+8801XXXXXXXXX`).
  3. Validate stock availability for all cart items within a database transaction (`BEGIN TRANSACTION`).
  4. Generate unique invoice number: `INV-` + `date('Ymd')` + random 4-digit number.
  5. Calculate exact financial totals: Subtotal, Discount, Delivery Fee, VAT, Grand Total.
  6. Insert into `orders` table with status `pending`, payment status `unpaid` (or `paid` if online instant).
  7. Insert each cart item into `order_items` (capturing snapshot of unit `price`, `product_name`, `sku`, `quantity`, `total`).
  8. Deduct stock from `products` table (`stock = stock - quantity`).
  9. Record stock movement in `inventory_logs` (`type = 'sale'`).
  10. Clear active cart in `cart_items`.
  11. If COD: Redirect to `public/thank-you.php?order_id={id}`.
  12. If Online Gateway (SSLCommerz/bKash): Initialize gateway session and redirect to payment gateway URL.
* **Database Tables Used:**
  * `orders`, `order_items`, `addresses`, `users`, `products`, `inventory_logs`, `coupons`, `transactions`, `notifications`.

---

## 6. Customer Account & Order Tracking

* **Page Name:** Customer Account Dashboard & Orders
* **URL / Path:** `/profile.php`, `/orders.php`, `/order-details.php`, `/addresses.php`
* **Purpose:** Manage customer profile, change password, link Google accounts, manage saved addresses, track live order status, and view order receipts.
* **UI Components:**
  * Profile Card: Customer avatar (with initial fallback badge), full name, email, phone, member since timestamp.
  * Security & Auth Methods Panel:
    * Shows active login methods (Google OAuth badge / Email+Password badge).
    * "Add Local Password" form for Google-only users.
    * "Change Password" form for password users.
    * "Sign Out of All Devices" button (increments `users.session_version`).
  * Order History Table: Order ID, invoice number, date, item count, total amount, payment method, payment status badge, order status badge, "View Details" button.
  * Order Details View (`order-details.php`):
    * Visual Status Tracker Timeline: `Pending` &rarr; `Confirmed` &rarr; `Processing` &rarr; `Shipped` &rarr; `Delivered`.
    * Delivery Rider Card (if assigned): Rider name, phone number.
    * Delivery Address Card.
    * Itemized order items table.
    * Print Invoice button.
  * Address Book (`addresses.php`): Create, edit, delete, and set default shipping/billing addresses.
* **Database Tables Used:**
  * `users`, `orders`, `order_items`, `order_status_history`, `addresses`, `delivery_boys`, `delivery_assignments`.
* **Authentication Requirement:** Required (`is_logged_in() === true`).

---

## 7. Wishlist & Product Comparison

* **Wishlist Page (`/wishlist.php`):**
  * Displays saved customer items with live prices and stock availability.
  * User can move item to cart in one click or remove from wishlist.
  * Saved in `wishlists` table (or session for guests).
* **Compare Page (`/compare.php`):**
  * Side-by-side comparison matrix of up to 4 products.
  * Rows: Thumbnail, Name, Brand, Category, Price, Stock Status, Weight/Unit, Rating, Description, Actions.
  * Stored in `compare_items` table / session.

---

## 8. Customer Authentication Flows

* **Login (`/login.php`):** Email + Password input with "Remember Me" 30-day persistent cookie, and "Continue with Google" button.
* **Registration (`/register.php`):** Full name, email, phone, password, confirm password. Blocks emails registered in `admins` table.
* **Forgot Password (`/forgot-password.php`):** Email input, generates SHA-256 token in `password_resets`, dispatches transactional reset email via SMTP.
* **Reset Password (`/reset-password.php?token={token}`):** Validates unexpired, unused token, updates password hash in `users`, marks token `used = 1`.
