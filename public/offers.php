<?php
/**
 * ==========================================================================
 * public/offers.php — Marketing Promotions & Offers Page
 * ==========================================================================
 * Displays active coupon codes, countdown flash deals, seasonal offers, and
 * newsletter inputs.
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/dbconnect.php';

$coupons = [];
$pdo = db();

try {
    // Fetch all active, non-expired coupon discounts
    $stmt = $pdo->query('
        SELECT * FROM coupons 
        WHERE is_active = 1 AND (expiry_date IS NULL OR expiry_date >= CURDATE()) 
        ORDER BY discount_percent DESC, id DESC
    ');
    $coupons = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log('[offers.php] Coupon query fail: ' . $e->getMessage());
}

$pageTitle = 'Hot Deals & Coupon Offers — ' . site_name();
$pageDescription = 'Discover hot discounts, copy active coupon codes, track ticking flash sales, and save on organic groceries.';

$extraStylesheets = ['css/home.css', 'css/products.css', 'css/offers.css', 'css/newsletter.css'];
$extraScripts = ['js/offers.js', 'js/newsletter.js', 'js/quickview.js', 'js/cart.js', 'js/wishlist.js', 'js/compare.js'];

require_once __DIR__ . '/header.php';

// Prepare Breadcrumbs trail
$breadcrumbs = [
    ['title' => 'Hot Deals & Offers']
];
?>

<!-- Breadcrumbs -->
<?php include PUBLIC_PATH . '/components/breadcrumb.php'; ?>

<div class="container" style="margin-top: var(--space-5); margin-bottom: var(--space-6);">
    
    <!-- Hero Header -->
    <div class="offers-hero-banner" style="background:linear-gradient(135deg, #1098ad 0%, #0c8599 100%); border-radius:var(--radius-lg); padding:var(--space-5); color:var(--color-surface); text-align:center; margin-bottom:var(--space-5); box-shadow:var(--shadow-sm); relative; overflow:hidden;">
        <h1 style="font-size:var(--fs-lg); font-weight:800; margin:0 0 6px 0; letter-spacing:-0.5px;"><i class="fas fa-percent"></i> Save Big on Groceries</h1>
        <p style="font-size:var(--fs-xs); color:rgba(255, 255, 255, 0.9); margin:0;">Copy active coupons below and apply them on checkout for instant savings.</p>
    </div>

    <!-- Active Coupons Section -->
    <section style="margin-bottom: var(--space-5);">
        <h2 style="font-size:var(--fs-sm); font-weight:800; text-transform:uppercase; color:var(--color-text-faint); margin:0 0 var(--space-3) 0; letter-spacing:0.5px;">Active Store Coupons</h2>
        
        <?php if (!empty($coupons)): ?>
            <div class="coupons-grid" style="display:grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap:var(--space-4);">
                <?php foreach ($coupons as $c): 
                    $pct = (int) $c['discount_percent'];
                    $minSpend = (float) ($c['min_spend'] ?? 0);
                    $expiry = $c['expiry_date'] ? date('M d, Y', strtotime($c['expiry_date'])) : 'Never Expires';
                ?>
                    <div class="coupon-offer-card" style="background:var(--color-surface); border:2px dashed var(--color-border); border-radius:var(--radius-md); padding:var(--space-4); display:flex; flex-direction:column; gap:var(--space-3); relative; overflow:hidden; box-shadow:var(--shadow-sm);">
                        
                        <!-- Left cutout circles for coupon ticket visual look -->
                        <div class="coupon-ticket-cutout left"></div>
                        <div class="coupon-ticket-cutout right"></div>

                        <div style="display:flex; justify-content:space-between; align-items:start;">
                            <div>
                                <span class="coupon-pct-badge" style="background:var(--color-primary-light); color:var(--color-primary-dark); font-weight:800; font-size:16px; padding:4px 10px; border-radius:var(--radius-sm);"><?= $pct ?>% OFF</span>
                                <span style="font-size:10px; color:var(--color-text-faint); font-weight:700; display:block; margin-top:6px;">Min. Spend: <?= format_price($minSpend) ?></span>
                            </div>
                            <span style="font-size:9px; color:var(--color-danger); background:#fdf2f2; font-weight:800; padding:2px 6px; border-radius:var(--radius-sm); border:1px solid #f8b4b4;">EXP: <?= $expiry ?></span>
                        </div>

                        <div>
                            <h4 style="font-size:var(--fs-xs); font-weight:800; color:var(--color-text); margin:0 0 4px 0;"><?= e($c['description'] ?? 'Store Discount') ?></h4>
                        </div>

                        <div class="coupon-code-copy-box" style="display:flex; background:var(--color-bg); border:1px solid var(--color-border); border-radius:var(--radius-pill); padding:4px; align-items:center; justify-content:space-between; margin-top:auto;">
                            <code class="coupon-code-text" style="font-family:var(--font-mono, monospace); font-weight:800; font-size:var(--fs-xs); padding-left:12px; color:var(--color-text); letter-spacing:0.5px;"><?= e($c['code']) ?></code>
                            <button type="button" class="btn btn-primary btn-copy-coupon" data-code="<?= e($c['code']) ?>" style="font-size:10px; padding:6px 14px; border:none; border-radius:var(--radius-pill); font-weight:700; height:auto; width:auto; display:inline-flex; align-items:center; gap:3px;">
                                <i class="far fa-copy"></i> Copy
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div style="background:var(--color-surface); border:1px solid var(--color-border); border-radius:var(--radius-md); padding:var(--space-5); text-align:center; color:var(--color-text-faint);">
                <p>No active store coupons found at this moment. Stay tuned!</p>
            </div>
        <?php endif; ?>
    </section>

    <!-- Flash Sale Ticking block -->
    <?php include PUBLIC_PATH . '/components/flash-sale.php'; ?>

    <!-- Seasonal Banners / Marketing cards -->
    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:var(--space-4); margin-bottom:var(--space-5); flex-wrap:wrap;">
        <div class="seasonal-banner-card card-blue" style="border-radius:var(--radius-lg); padding:var(--space-5); color:var(--color-surface); display:flex; flex-direction:column; gap:var(--space-2); min-height:160px; justify-content:center; box-shadow:var(--shadow-sm); relative; overflow:hidden;">
            <span style="font-size:9px; font-weight:800; text-transform:uppercase; letter-spacing:0.5px; opacity:0.8;">Fresh Harvest</span>
            <h3 style="font-size:var(--fs-sm); font-weight:800; margin:0;">Organic Vegetable Packs</h3>
            <p style="font-size:11px; margin:0; opacity:0.9;">Save 15% on direct farm harvested bundle packages this weekend.</p>
            <a href="<?= url_for('products.php?category=vegetables') ?>" class="btn btn-primary" style="font-size:10px; font-weight:700; border-radius:var(--radius-pill); border:none; padding:8px 16px; width:fit-content; margin-top:8px; background:var(--color-surface); color:var(--color-primary);">Buy Now</a>
        </div>
        <div class="seasonal-banner-card card-orange" style="border-radius:var(--radius-lg); padding:var(--space-5); color:var(--color-surface); display:flex; flex-direction:column; gap:var(--space-2); min-height:160px; justify-content:center; box-shadow:var(--shadow-sm); relative; overflow:hidden;">
            <span style="font-size:9px; font-weight:800; text-transform:uppercase; letter-spacing:0.5px; opacity:0.8;">Summer Special</span>
            <h3 style="font-size:var(--fs-sm); font-weight:800; margin:0;">Sweet Juicy Mangoes</h3>
            <p style="font-size:11px; margin:0; opacity:0.9;">Fresh Rajshahi Mangoes at unmatched prices. Get flat discounts.</p>
            <a href="<?= url_for('products.php?category=fruits') ?>" class="btn btn-primary" style="font-size:10px; font-weight:700; border-radius:var(--radius-pill); border:none; padding:8px 16px; width:fit-content; margin-top:8px; background:var(--color-surface); color:#e67e22;">Browse Fruits</a>
        </div>
    </div>

    <!-- Newsletter subscription form -->
    <?php include PUBLIC_PATH . '/components/newsletter-form.php'; ?>

</div>

<?php
require_once __DIR__ . '/footer.php';
?>
