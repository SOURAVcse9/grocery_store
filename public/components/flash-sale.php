<?php
/**
 * ==========================================================================
 * public/components/flash-sale.php
 * ==========================================================================
 * Flash Sale Promotional Section with Countdown Clock.
 * Queries items with active discounts and displays them with a timer.
 * ==========================================================================
 */

declare(strict_types=1);

try {
    $pdo = db();
    
    // Fetch products on sale (limited to 4 for grid view)
    $stmt = $pdo->query("
        SELECT p.*, c.name AS category_name, b.name AS brand_name,
               COALESCE(AVG(pr.rating), 0) AS avg_rating,
               COUNT(pr.id) AS review_count
        FROM products p
        LEFT JOIN categories c ON c.id = p.category_id
        LEFT JOIN brands b ON b.id = p.brand_id
        LEFT JOIN product_reviews pr ON pr.product_id = p.id
        WHERE p.is_active = 1 AND p.discount_price IS NOT NULL AND p.discount_price < p.price
        GROUP BY p.id
        ORDER BY (p.price - p.discount_price) DESC, p.id DESC
        LIMIT 4
    ");
    $saleProducts = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log('[components/flash-sale.php] Query fail: ' . $e->getMessage());
    $saleProducts = [];
}

// Skip rendering if no products are currently discounted
if (empty($saleProducts)) {
    return;
}

// Generate target countdown time (e.g., end of today)
$targetTime = date('Y-m-d 23:59:59');
?>

<section class="products-section flash-sale-section" style="background:var(--color-bg); padding:var(--space-4) var(--space-3); border-radius:var(--radius-lg); border:1px solid var(--color-border); margin-bottom:var(--space-5);">
    <div class="category-section-header" style="flex-wrap:wrap; gap:var(--space-3); border-bottom:none; margin-bottom:var(--space-3); padding-bottom:0;">
        <div class="section-title-group" style="display:flex; align-items:center; gap:var(--space-4); flex-wrap:wrap;">
            <div style="display:inline-flex; align-items:center; gap:var(--space-2); color:var(--color-danger); font-weight:800; font-size:var(--fs-md);">
                <i class="fas fa-bolt-lightning"></i>
                <h2>Flash Sale</h2>
            </div>
            
            <!-- Countdown Clock Container -->
            <div class="flash-sale-countdown-wrapper" id="flashSaleCountdown" data-target="<?= e($targetTime) ?>" style="display:flex; gap:6px; font-weight:800; font-size:12px;">
                <span style="color:var(--color-text-muted); font-weight:700; text-transform:uppercase; font-size:10px; margin-right:4px; display:inline-flex; align-items:center;">Ends In:</span>
                <span class="timer-box hours" style="background:var(--color-danger); color:var(--color-surface); padding:4px 8px; border-radius:var(--radius-sm);">00</span>
                <span class="timer-colon" style="color:var(--color-danger); align-self:center;">:</span>
                <span class="timer-box minutes" style="background:var(--color-danger); color:var(--color-surface); padding:4px 8px; border-radius:var(--radius-sm);">00</span>
                <span class="timer-colon" style="color:var(--color-danger); align-self:center;">:</span>
                <span class="timer-box seconds" style="background:var(--color-danger); color:var(--color-surface); padding:4px 8px; border-radius:var(--radius-sm);">00</span>
            </div>
        </div>
        <a href="<?= url_for('products.php?discount=on_sale') ?>" class="view-all-link" style="color:var(--color-danger);">
            All Deals <i class="fas fa-chevron-right"></i>
        </a>
    </div>

    <!-- Product Grid -->
    <div class="products-grid" style="grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));">
        <?php foreach ($saleProducts as $product): ?>
            <?php include PUBLIC_PATH . '/components/product-card.php'; ?>
        <?php endforeach; ?>
    </div>
</section>

<script>
(function() {
    const clock = document.getElementById('flashSaleCountdown');
    if (!clock) return;

    const targetDate = new Date(clock.dataset.target.replace(/-/g, "/")).getTime();

    const timer = setInterval(() => {
        const now = new Date().getTime();
        const distance = targetDate - now;

        if (distance < 0) {
            clearInterval(timer);
            clock.innerHTML = '<span style="color:var(--color-text-faint); font-weight:700; text-transform:uppercase; font-size:10px;">Sale Ended</span>';
            return;
        }

        const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
        const seconds = Math.floor((distance % (1000 * 60)) / 1000);

        const pad = (num) => num.toString().padStart(2, '0');

        const boxH = clock.querySelector('.hours');
        const boxM = clock.querySelector('.minutes');
        const boxS = clock.querySelector('.seconds');

        if (boxH) boxH.textContent = pad(hours);
        if (boxM) boxM.textContent = pad(minutes);
        if (boxS) boxS.textContent = pad(seconds);
    }, 1000);
})();
</script>
