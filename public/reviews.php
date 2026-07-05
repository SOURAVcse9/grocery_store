<?php
/**
 * ==========================================================================
 * public/reviews.php — Dedicated Customer Product Reviews Index Page
 * ==========================================================================
 * Summarizes product ratings, plots distribution meters, handles verified
 * buyers locks, pre-queries existing posts, and lists reviews.
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/dbconnect.php';

$productId = (int) input('product_id', '0', 'get');

if ($productId <= 0) {
    // If slug is passed instead
    $slug = input('slug', '', 'get');
    if (!empty($slug)) {
        try {
            $prodQuery = db()->prepare('SELECT id FROM products WHERE slug = :slug AND is_active = 1 LIMIT 1');
            $prodQuery->execute(['slug' => $slug]);
            $productId = (int) $prodQuery->fetchColumn();
        } catch (PDOException $e) {
            error_log('[reviews.php] Slug match fail: ' . $e->getMessage());
        }
    }
}

if ($productId <= 0) {
    flash('catalog', 'Invalid product selection.', 'error');
    redirect(url_for('products.php'));
}

try {
    $pdo = db();

    // 1. Fetch Product details
    $prodStmt = $pdo->prepare('
        SELECT p.*, c.name AS category_name, b.name AS brand_name,
               COALESCE(AVG(pr.rating), 0) AS avg_rating,
               COUNT(pr.id) AS review_count
        FROM products p
        LEFT JOIN categories c ON c.id = p.category_id
        LEFT JOIN brands b ON b.id = p.brand_id
        LEFT JOIN product_reviews pr ON pr.product_id = p.id
        WHERE p.id = :id AND p.is_active = 1
        GROUP BY p.id
        LIMIT 1
    ');
    $prodStmt->execute(['id' => $productId]);
    $product = $prodStmt->fetch();

    if (!$product) {
        flash('catalog', 'Product unavailable.', 'error');
        redirect(url_for('products.php'));
    }

    $avgRating = (float) $product['avg_rating'];
    $reviewCount = (int) $product['review_count'];

    // 2. Compute Star Rating Distribution chart data
    $dist = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
    $distStmt = $pdo->prepare('
        SELECT rating, COUNT(*) as count 
        FROM product_reviews 
        WHERE product_id = :pid AND status = \'approved\' 
        GROUP BY rating
    ');
    $distStmt->execute(['pid' => $productId]);
    foreach ($distStmt->fetchAll() as $row) {
        $dist[(int)$row['rating']] = (int) $row['count'];
    }

    // 3. Check login and purchase validations
    $hasPurchased = false;
    $existingReview = null;

    if (is_logged_in()) {
        $userId = current_user_id();

        // Check purchase
        $purchaseStmt = $pdo->prepare('
            SELECT COUNT(*) 
            FROM orders o
            JOIN order_items oi ON oi.order_id = o.id
            WHERE o.user_id = :uid 
              AND oi.product_id = :pid 
              AND o.status = \'delivered\'
        ');
        $purchaseStmt->execute(['uid' => $userId, 'pid' => $productId]);
        $hasPurchased = ((int) $purchaseStmt->fetchColumn() > 0);

        // Fetch user's existing review (for update/delete inline prefill)
        $existStmt = $pdo->prepare('SELECT * FROM product_reviews WHERE product_id = :pid AND user_id = :uid LIMIT 1');
        $existStmt->execute(['pid' => $productId, 'uid' => $userId]);
        $existingReview = $existStmt->fetch();
    }

    // 4. Fetch First Page of Reviews (newest first, limit 5)
    $reviewsSql = "
        SELECT pr.*, u.full_name, u.avatar,
               (SELECT COUNT(*) FROM orders o 
                JOIN order_items oi ON oi.order_id = o.id 
                WHERE o.user_id = pr.user_id 
                  AND oi.product_id = pr.product_id 
                  AND o.status = 'delivered') AS verified_purchase
        FROM product_reviews pr
        JOIN users u ON u.id = pr.user_id
        WHERE pr.product_id = :pid AND pr.status = 'approved'
        ORDER BY pr.created_at DESC
        LIMIT 5 OFFSET 0
    ";
    $listStmt = $pdo->prepare($reviewsSql);
    $listStmt->execute(['pid' => $productId]);
    $reviews = $listStmt->fetchAll();

} catch (PDOException $e) {
    error_log('[reviews.php] Error: ' . $e->getMessage());
    if (APP_DEBUG) {
        die('Reviews index DB error: ' . htmlspecialchars($e->getMessage()));
    }
}

// Layout metadata
$pageTitle = 'Customer Reviews — ' . $product['name'] . ' — ' . site_name();
$pageDescription = 'Read what verified buyers are saying about ' . $product['name'] . '. View average star breakdown and write reviews.';

$extraStylesheets = ['css/home.css', 'css/product.css', 'css/reviews.css'];
$extraScripts = ['js/reviews.js'];

require_once __DIR__ . '/header.php';

// Prepare Breadcrumbs trail
$breadcrumbs = [
    ['title' => t('shop') ?? 'Shop', 'link' => 'products.php'],
    ['title' => $product['name'], 'link' => 'product.php?slug=' . $product['slug']],
    ['title' => 'Customer Reviews']
];
?>

<!-- Breadcrumbs -->
<?php include PUBLIC_PATH . '/components/breadcrumb.php'; ?>

<div class="container" style="margin-top: var(--space-5);">
    
    <!-- 2 Column Layout -->
    <div class="product-detail-layout" style="grid-template-columns: 320px 1fr; align-items: start; background:transparent; border:none; padding:0; box-shadow:none;">
        
        <!-- Left Column: Product Info Summary & Form -->
        <div style="display:flex; flex-direction:column; gap:var(--space-4); width:100%;">
            
            <!-- Product Summary Card -->
            <div class="dashboard-card" style="margin-bottom:0;">
                <div style="text-align:center; margin-bottom:var(--space-3);">
                    <a href="<?= url_for('product.php?slug=' . $product['slug']) ?>" style="width:120px; height:120px; display:inline-flex; align-items:center; justify-content:center; background:var(--color-bg); border:1px solid var(--color-border); border-radius:var(--radius-sm); padding:var(--space-2); margin-bottom:var(--space-2);">
                        <img src="<?= e(image_url($product['thumbnail'], 'products')) ?>" alt="<?= e($product['name']) ?>" style="max-width:100%; max-height:100%; object-fit:contain;">
                    </a>
                    <h2 style="font-size:var(--fs-sm); font-weight:800; margin:0 0 var(--space-1) 0; line-height:1.4;">
                        <a href="<?= url_for('product.php?slug=' . $product['slug']) ?>" style="color:var(--color-text);"><?= e($product['name']) ?></a>
                    </h2>
                    <span style="font-size:11px; color:var(--color-text-faint); font-weight:700; text-transform:uppercase;"><?= e($product['brand_name'] ?? 'Other') ?></span>
                </div>

                <div class="rating-summary-panel" style="margin-bottom:var(--space-4); background:var(--color-bg);">
                    <div class="summary-score"><?= number_format($avgRating, 1) ?></div>
                    <div class="summary-stars">
                        <?php 
                        $rating = $avgRating;
                        include PUBLIC_PATH . '/components/rating-stars.php'; 
                        ?>
                    </div>
                    <div class="summary-count">Based on <?= $reviewCount ?> ratings</div>
                </div>

                <!-- Distribution Graph Chart -->
                <div class="rating-dist-wrapper">
                    <?php 
                    for ($s = 5; $s >= 1; $s--): 
                        $count = $dist[$s];
                        $pct = $reviewCount > 0 ? (int) round(($count / $reviewCount) * 100) : 0;
                    ?>
                        <div class="rating-dist-row">
                            <span class="rating-dist-label"><?= $s ?> star</span>
                            <div class="rating-dist-bar-bg">
                                <div class="rating-dist-bar-fill" style="width: <?= $pct ?>%;"></div>
                            </div>
                            <span class="rating-dist-percent"><?= $pct ?>%</span>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>

            <!-- Review Submission Form component -->
            <?php include PUBLIC_PATH . '/components/review-form.php'; ?>

        </div>

        <!-- Right Column: Paginated Reviews Log List -->
        <div style="display:flex; flex-direction:column; gap:var(--space-4); width:100%;">
            
            <!-- Sorting toolbar -->
            <div class="catalog-toolbar" style="margin-bottom:0;">
                <div class="toolbar-left">
                    <span class="product-count-label" id="reviewCountLabel">
                        Showing <?= count($reviews) ?> of <?= $reviewCount ?> reviews
                    </span>
                </div>
                <div class="toolbar-right">
                    <label for="reviewSortSelect" class="sort-label">Sort By:</label>
                    <select id="reviewSortSelect" class="sort-select" style="font-size:11px; padding:6px 12px;" onchange="loadFilteredReviews(<?= $productId ?>, 1);">
                        <option value="newest">Newest First</option>
                        <option value="highest">Highest Rating</option>
                        <option value="lowest">Lowest Rating</option>
                    </select>
                </div>
            </div>

            <!-- Reviews Container Box (with Overlay spinner) -->
            <div class="catalog-content-wrapper">
                <div class="catalog-loading-overlay" id="catalogLoadingOverlay">
                    <div class="catalog-loading-spinner">
                        <i class="fas fa-spinner fa-spin"></i> Filtering...
                    </div>
                </div>

                <div id="reviewsIndexContainer" class="reviews-list">
                    <?php if (!empty($reviews)): ?>
                        <?php foreach ($reviews as $review): ?>
                            <?php include PUBLIC_PATH . '/components/review-card.php'; ?>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="text-align:center; color:var(--color-text-muted); font-size:var(--fs-xs); padding: var(--space-4) 0; margin:0;">There are no reviews for this product yet.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Pagination container -->
            <div class="catalog-pagination" id="reviewsPaginationContainer" style="margin-top:0; display:flex; justify-content:center;">
                <?php
                $totalPages = (int) ceil($reviewCount / $limit);
                $baseUrl = 'reviews.php';
                $queryParams = $_GET;
                unset($queryParams['page']);

                if ($totalPages > 1) {
                    include PUBLIC_PATH . '/components/pagination.php';
                }
                ?>
            </div>

        </div>

    </div>
</div>

<script>
// Dynamic AJAX reviews filtering and pagination
async function loadFilteredReviews(pid, page = 1) {
    const sortSelect = document.getElementById('reviewSortSelect');
    const sort = sortSelect ? sortSelect.value : 'newest';
    const overlay = document.getElementById('catalogLoadingOverlay');
    const container = document.getElementById('reviewsIndexContainer');
    const pagination = document.getElementById('reviewsPaginationContainer');

    overlay?.classList.add('is-loading');

    try {
        const res = await fetch(`api/reviews.php?product_id=${pid}&sort=${sort}&page=${page}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const json = await res.json();

        if (json.success && json.data) {
            if (container) container.innerHTML = json.data.html;
            if (pagination) pagination.innerHTML = json.data.pagination_html;
            
            // Re-bind pagination clicks in reviews index if standard page click event is delegated
        }
    } catch (err) {
        console.error('Failed to load reviews:', err);
    } finally {
        overlay?.classList.remove('is-loading');
    }
}

// Delegate Pagination clicks inside the reviews view
document.addEventListener('click', (e) => {
    const link = e.target.closest('.pagination-link');
    if (!link || !document.getElementById('reviewsPaginationContainer')?.contains(link)) return;

    e.preventDefault();
    const href = link.getAttribute('href');
    if (href) {
        const urlParams = new URLSearchParams(href.split('?')[1] ?? '');
        const page = parseInt(urlParams.get('page') ?? '1');
        const pid = parseInt(new URLSearchParams(window.location.search).get('product_id') || '<?= $productId ?>');
        loadFilteredReviews(pid, page);
        
        window.scrollTo({
            top: document.getElementById('catalogWrapper').offsetTop - 100,
            behavior: 'smooth'
        });
    }
});
</script>

<?php
require_once __DIR__ . '/footer.php';
?>
