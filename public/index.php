<?php
declare(strict_types=1);

/**
 * ==========================================================================
 * public/index.php — Storefront Homepage
 * ==========================================================================
 * Core entry point for the grocery store storefront.
 * Fetches and displays Hero Slider, Category Grid, and various product grids:
 * Featured, Today's Deals, Best Sellers, Popular Products, New Arrivals,
 * Flash Sales, and Featured Brands.
 * ==========================================================================
 */

require_once __DIR__ . '/dbconnect.php';

// Set page meta details
$pageTitle = site_name() . ' — Fresh Groceries Delivered Fast';
$pageDescription = 'Shop fresh fruits, organic vegetables, dairy, snacks, cooking oil, and daily essentials online. Fast delivery in Bangladesh.';

// Set stylesheets and javascript files needed for this page
$extraStylesheets = ['css/home.css', 'css/newsletter.css'];
$extraScripts = [
    'js/slider.js',
    'js/quickview.js',
    'js/cart.js',
    'js/wishlist.js',
    'js/compare.js',
    'js/newsletter.js'
];

try {
    $pdo = db();

    // 1. Fetch Banners dynamically from database banners table
    $bannersData = $pdo->query("
        SELECT * FROM banners 
        WHERE is_active = 1 
          AND (starts_at IS NULL OR starts_at <= NOW()) 
          AND (ends_at IS NULL OR ends_at >= NOW()) 
        ORDER BY priority ASC
    ")->fetchAll();
    
    $banners = [];
    foreach ($bannersData as $bRow) {
        $banners[] = [
            'image' => '../uploads/banners/' . $bRow['image_path'],
            'title' => $bRow['title'],
            'subtitle' => 'Limited Time Exclusive Deal',
            'button_text' => 'Shop Now',
            'button_link' => $bRow['link_url'] ?? 'products.php'
        ];
    }

    // 2. Fetch Categories (Root categories only, with active product count)
    $categories = $pdo->query('
        SELECT c.id, c.name, c.slug, c.image, COUNT(p.id) AS product_count 
        FROM categories c 
        LEFT JOIN products p ON p.category_id = c.id AND p.is_active = 1
        WHERE c.is_active = 1 AND c.parent_id IS NULL
        GROUP BY c.id 
        ORDER BY c.name ASC 
        LIMIT 8
    ')->fetchAll();

    // 3. Fetch Featured Products (is_featured = 1, with average rating and review counts)
    $featuredProducts = $pdo->query('
        SELECT p.*, 
               COALESCE(AVG(pr.rating), 0) AS avg_rating,
               COUNT(pr.id) AS review_count
        FROM products p
        LEFT JOIN product_reviews pr ON pr.product_id = p.id
        WHERE p.is_active = 1 AND p.is_featured = 1
        GROUP BY p.id
        ORDER BY p.id DESC
        LIMIT 8
    ')->fetchAll();

    // 4. Fetch Today\'s Deals (products with discount_price, limited to 4 for clean layout next to countdown)
    $todaysDeals = $pdo->query('
        SELECT p.*, 
               COALESCE(AVG(pr.rating), 0) AS avg_rating,
               COUNT(pr.id) AS review_count
        FROM products p
        LEFT JOIN product_reviews pr ON pr.product_id = p.id
        WHERE p.is_active = 1 AND p.discount_price IS NOT NULL AND p.discount_price < p.price
        GROUP BY p.id
        ORDER BY p.id DESC
        LIMIT 4
    ')->fetchAll();

    // 5. Fetch Best Sellers (ordered by sales count, fallback to id DESC)
    $bestSellers = $pdo->query('
        SELECT p.*, 
               COUNT(oi.id) AS sales_count,
               COALESCE(AVG(pr.rating), 0) AS avg_rating,
               COUNT(pr.id) AS review_count
        FROM products p
        LEFT JOIN order_items oi ON oi.product_id = p.id
        LEFT JOIN product_reviews pr ON pr.product_id = p.id
        WHERE p.is_active = 1
        GROUP BY p.id
        ORDER BY sales_count DESC, p.id DESC
        LIMIT 8
    ')->fetchAll();

    // 6. Fetch Popular Products (ordered by high ratings and reviews)
    $popularProducts = $pdo->query('
        SELECT p.*, 
               COALESCE(AVG(pr.rating), 0) AS avg_rating,
               COUNT(pr.id) AS review_count
        FROM products p
        LEFT JOIN product_reviews pr ON pr.product_id = p.id
        WHERE p.is_active = 1
        GROUP BY p.id
        ORDER BY avg_rating DESC, review_count DESC, p.id DESC
        LIMIT 8
    ')->fetchAll();

    // 7. Fetch New Arrivals
    $newArrivals = $pdo->query('
        SELECT p.*, 
               COALESCE(AVG(pr.rating), 0) AS avg_rating,
               COUNT(pr.id) AS review_count
        FROM products p
        LEFT JOIN product_reviews pr ON pr.product_id = p.id
        WHERE p.is_active = 1
        GROUP BY p.id
        ORDER BY p.created_at DESC, p.id DESC
        LIMIT 8
    ')->fetchAll();

    // 8. Fetch Flash Sales (products with highest discount percentages)
    $flashSales = $pdo->query('
        SELECT p.*, 
               COALESCE(AVG(pr.rating), 0) AS avg_rating,
               COUNT(pr.id) AS review_count,
               ROUND(((p.price - p.discount_price) / p.price) * 100) AS discount_percentage
        FROM products p
        LEFT JOIN product_reviews pr ON pr.product_id = p.id
        WHERE p.is_active = 1 AND p.discount_price IS NOT NULL AND p.discount_price < p.price
        GROUP BY p.id
        ORDER BY discount_percentage DESC, p.id DESC
        LIMIT 8
    ')->fetchAll();

    // 9. Fetch Brands
    $brands = $pdo->query('
        SELECT id, name, slug, logo FROM brands 
        ORDER BY name ASC 
        LIMIT 12
    ')->fetchAll();

} catch (PDOException $e) {
    error_log('[index.php] Database error: ' . $e->getMessage());
    // In production fail gracefully, in dev show details
    if (APP_DEBUG) {
        die('Homepage Database Error: ' . htmlspecialchars($e->getMessage()));
    }
}

// Require Header
require_once __DIR__ . '/header.php';
?>

<!-- 1. Hero Slider Section -->
<?php if (!empty($banners)): ?>
    <?php include PUBLIC_PATH . '/components/hero.php'; ?>
<?php endif; ?>

<div class="container">

    <!-- 2. Smart Category Grid Section -->
    <?php if (!empty($categories)): ?>
        <section class="products-section">
            <div class="category-section-header">
                <div class="section-title-group">
                    <h2>Shop by Category</h2>
                    <p>Search your daily needs from our fresh categories</p>
                </div>
                <a href="<?= url_for('categories.php') ?>" class="view-all-link">
                    View All <i class="fas fa-chevron-right"></i>
                </a>
            </div>
            <div class="category-grid">
                <?php foreach ($categories as $category): ?>
                    <?php include PUBLIC_PATH . '/components/category-card.php'; ?>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <!-- 3. Featured Products Section -->
    <?php if (!empty($featuredProducts)): ?>
        <section class="products-section">
            <div class="category-section-header">
                <div class="section-title-group">
                    <h2>Featured Products</h2>
                    <p>Highly recommended fresh & organic items</p>
                </div>
                <a href="<?= url_for('products.php?featured=1') ?>" class="view-all-link">
                    Explore More <i class="fas fa-chevron-right"></i>
                </a>
            </div>
            <div class="products-grid">
                <?php foreach ($featuredProducts as $product): ?>
                    <?php include PUBLIC_PATH . '/components/product-card.php'; ?>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <!-- 4. Today's Deal Section with Live Countdown Timer -->
    <?php if (!empty($todaysDeals)): ?>
        <section class="todays-deal-section">
            <div class="todays-deal-header">
                <div class="deal-title-group">
                    <h2><i class="fas fa-bolt"></i> Today's Deals</h2>
                    <p>Grab these special discounts before the day ends!</p>
                </div>
                <!-- Countdown Clock -->
                <div class="countdown-timer" id="dealCountdown">
                    <div class="countdown-box">
                        <span class="countdown-num" id="dealHours">00</span>
                        <span class="countdown-label">Hrs</span>
                    </div>
                    <div class="countdown-box">
                        <span class="countdown-num" id="dealMinutes">00</span>
                        <span class="countdown-label">Min</span>
                    </div>
                    <div class="countdown-box">
                        <span class="countdown-num" id="dealSeconds">00</span>
                        <span class="countdown-label">Sec</span>
                    </div>
                </div>
            </div>
            <div class="products-grid">
                <?php foreach ($todaysDeals as $product): ?>
                    <?php include PUBLIC_PATH . '/components/product-card.php'; ?>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <!-- 5. Best Sellers Section -->
    <?php if (!empty($bestSellers)): ?>
        <section class="products-section">
            <div class="category-section-header">
                <div class="section-title-group">
                    <h2>Best Sellers</h2>
                    <p>Most purchased items in our store</p>
                </div>
                <a href="<?= url_for('products.php?sort=best_seller') ?>" class="view-all-link">
                    View More <i class="fas fa-chevron-right"></i>
                </a>
            </div>
            <div class="products-grid">
                <?php foreach ($bestSellers as $product): ?>
                    <?php include PUBLIC_PATH . '/components/product-card.php'; ?>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <!-- 6. Popular Products Section -->
    <?php if (!empty($popularProducts)): ?>
        <section class="products-section">
            <div class="category-section-header">
                <div class="section-title-group">
                    <h2>Popular Products</h2>
                    <p>Customer favorites with highest ratings</p>
                </div>
                <a href="<?= url_for('products.php?sort=popular') ?>" class="view-all-link">
                    View More <i class="fas fa-chevron-right"></i>
                </a>
            </div>
            <div class="products-grid">
                <?php foreach ($popularProducts as $product): ?>
                    <?php include PUBLIC_PATH . '/components/product-card.php'; ?>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <!-- 7. New Arrivals Section -->
    <?php if (!empty($newArrivals)): ?>
        <section class="products-section">
            <div class="category-section-header">
                <div class="section-title-group">
                    <h2>New Arrivals</h2>
                    <p>Just added fresh products in our catalog</p>
                </div>
                <a href="<?= url_for('products.php?sort=newest') ?>" class="view-all-link">
                    View More <i class="fas fa-chevron-right"></i>
                </a>
            </div>
            <div class="products-grid">
                <?php foreach ($newArrivals as $product): ?>
                    <?php include PUBLIC_PATH . '/components/product-card.php'; ?>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <!-- 8. Flash Sale Section -->
    <?php if (!empty($flashSales)): ?>
        <section class="products-section">
            <div class="category-section-header">
                <div class="section-title-group">
                    <h2>Flash Sales</h2>
                    <p>Maximum discounts on select products</p>
                </div>
                <a href="<?= url_for('products.php?on_sale=1') ?>" class="view-all-link">
                    See Offers <i class="fas fa-chevron-right"></i>
                </a>
            </div>
            <div class="products-grid">
                <?php foreach ($flashSales as $product): ?>
                    <?php include PUBLIC_PATH . '/components/product-card.php'; ?>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <!-- 9. Featured Brands Section -->
    <?php if (!empty($brands)): ?>
        <section class="products-section">
            <div class="category-section-header">
                <div class="section-title-group">
                    <h2>Featured Brands</h2>
                    <p>Top manufacturer products delivered to you</p>
                </div>
            </div>
            <div class="brand-grid">
                <?php foreach ($brands as $brand): ?>
                    <a href="<?= url_for('products.php?brand=' . e($brand['slug'])) ?>" class="brand-card" title="<?= e($brand['name']) ?>">
                        <span><?= e($brand['name']) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <!-- 10. Why Choose Us Section -->
    <section class="products-section">
        <div class="category-section-header">
            <div class="section-title-group">
                <h2>Why Choose Us</h2>
                <p>We guarantee quality service and fast fulfillment</p>
            </div>
        </div>
        <div class="features-grid">
            <div class="feature-box">
                <div class="feature-icon-wrapper"><i class="fas fa-truck-fast"></i></div>
                <div class="feature-info">
                    <h3>Fast Delivery</h3>
                    <p>Delivery inside Dhaka within 1 hour, standard delivery inside 24 hours.</p>
                </div>
            </div>
            <div class="feature-box">
                <div class="feature-icon-wrapper"><i class="fas fa-circle-check"></i></div>
                <div class="feature-info">
                    <h3>Fresh & Handpicked</h3>
                    <p>Premium fruits, organic veggies, and fresh meat inspected for quality daily.</p>
                </div>
            </div>
            <div class="feature-box">
                <div class="feature-icon-wrapper"><i class="fas fa-shield-halved"></i></div>
                <div class="feature-info">
                    <h3>Secure Checkout</h3>
                    <p>100% secure payments using local banking portals or cash on delivery.</p>
                </div>
            </div>
            <div class="feature-box">
                <div class="feature-icon-wrapper"><i class="fas fa-arrows-rotate"></i></div>
                <div class="feature-info">
                    <h3>Easy Returns</h3>
                    <p>No questions asked return policy on delivery if items do not match quality.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- 10. Recently Viewed Section -->
    <?php include PUBLIC_PATH . '/components/recently-viewed.php'; ?>

    <!-- 11. Newsletter Subscription Section -->
    <?php include PUBLIC_PATH . '/components/newsletter-form.php'; ?>

</div>

<!-- Countdown Timer JavaScript -->
<script>
(function() {
    'use strict';
    
    function updateCountdown() {
        const now = new Date();
        const endOfDay = new Date(now.getFullYear(), now.getMonth(), now.getDate(), 23, 59, 59);
        const diff = endOfDay - now;
        
        if (diff <= 0) {
            return;
        }
        
        const hours = Math.floor((diff / (1000 * 60 * 60)) % 24);
        const minutes = Math.floor((diff / 1000 / 60) % 60);
        const seconds = Math.floor((diff / 1000) % 60);
        
        const hEl = document.getElementById('dealHours');
        const mEl = document.getElementById('dealMinutes');
        const sEl = document.getElementById('dealSeconds');
        
        if (hEl) hEl.textContent = hours.toString().padStart(2, '0');
        if (mEl) mEl.textContent = minutes.toString().padStart(2, '0');
        if (sEl) sEl.textContent = seconds.toString().padStart(2, '0');
    }
    
    setInterval(updateCountdown, 1000);
    updateCountdown();
})();
</script>

<?php
// Require Footer
require_once __DIR__ . '/footer.php';
?>
