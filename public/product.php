<?php
/**
 * ==========================================================================
 * public/product.php — Product Details Page
 * ==========================================================================
 * Displays detailed information, specifications, image gallery, customer
 * reviews, related products, frequently bought together, and recently viewed.
 * Handles review submissions securely.
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/dbconnect.php';

$extraScripts = ['js/reviews.js'];
$slug = input('slug', '', 'get');

if (empty($slug)) {
    redirect(url_for('products.php'));
}

try {
    $pdo = db();

    // 1. Fetch Product details
    $stmt = $pdo->prepare('
        SELECT p.*, 
               c.name AS category_name, 
               c.slug AS category_slug, 
               b.name AS brand_name,
               COALESCE(AVG(pr.rating), 0) AS avg_rating,
               COUNT(pr.id) AS review_count
        FROM products p
        LEFT JOIN categories c ON c.id = p.category_id
        LEFT JOIN brands b ON b.id = p.brand_id
        LEFT JOIN product_reviews pr ON pr.product_id = p.id
        WHERE p.slug = :slug AND p.is_active = 1
        GROUP BY p.id
        LIMIT 1
    ');
    $stmt->execute(['slug' => $slug]);
    $product = $stmt->fetch();

    if (!$product) {
        flash('catalog', 'Product not found or unavailable.', 'error');
        redirect(url_for('products.php'));
    }

    $productId = (int) $product['id'];
    $categoryId = (int) $product['category_id'];

    // 2. Fetch Product gallery images
    $imgStmt = $pdo->prepare('SELECT image_url FROM product_images WHERE product_id = :id ORDER BY sort_order ASC');
    $imgStmt->execute(['id' => $productId]);
    $gallery = $imgStmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($gallery) && !empty($product['thumbnail'])) {
        $gallery[] = $product['thumbnail'];
    }

    // 3. Check purchase verification & existing reviews for logged-in user
    $isLoggedIn = is_logged_in();
    $hasPurchased = false;
    $isDelivered = false;
    $existingReview = null;

    if ($isLoggedIn) {
        $userId = current_user_id();
        
        // Fetch existing review
        $existStmt = $pdo->prepare('SELECT * FROM product_reviews WHERE product_id = :pid AND user_id = :uid LIMIT 1');
        $existStmt->execute(['pid' => $productId, 'uid' => $userId]);
        $existingReview = $existStmt->fetch();
        if ($existingReview === false) {
            $existingReview = null;
        }

        // Verify purchase status
        $targetOrderId = (int) input('order_id', '0', 'get');
        if ($targetOrderId > 0) {
            $orderStmt = $pdo->prepare('
                SELECT o.status, o.payment_status, o.payment_method
                FROM orders o
                JOIN order_items oi ON oi.order_id = o.id
                WHERE o.user_id = :uid 
                  AND oi.product_id = :pid 
                  AND o.id = :oid
                LIMIT 1
            ');
            $orderStmt->execute(['uid' => $userId, 'pid' => $productId, 'oid' => $targetOrderId]);
        } else {
            $orderStmt = $pdo->prepare('
                SELECT o.status, o.payment_status, o.payment_method
                FROM orders o
                JOIN order_items oi ON oi.order_id = o.id
                WHERE o.user_id = :uid 
                  AND oi.product_id = :pid 
                ORDER BY (LOWER(o.status) = \'delivered\') DESC, o.id DESC
                LIMIT 1
            ');
            $orderStmt->execute(['uid' => $userId, 'pid' => $productId]);
        }
        $orderInfo = $orderStmt->fetch();

        if ($orderInfo) {
            $hasPurchased = true;
            if (strtolower($orderInfo['status']) === 'delivered' && ($orderInfo['payment_status'] === 'paid' || $orderInfo['payment_method'] === 'cod')) {
                $isDelivered = true;
            }
        }

        // STEP 8: Debug log
        error_log(sprintf(
            "[Verified Purchase Review Debug] customer_id: %d, product_id: %d, order_id: %d, slug: %s, review_exists: %d, purchase_exists: %d, delivered_exists: %d, permission_result: %d",
            $userId,
            $productId,
            $targetOrderId,
            $slug,
            $existingReview ? 1 : 0,
            $hasPurchased ? 1 : 0,
            $isDelivered ? 1 : 0,
            ($isLoggedIn && $hasPurchased && $isDelivered && !$existingReview) ? 1 : 0
        ));
    }
    $currentUserId = is_logged_in() ? current_user_id() : null;

    // 4. Fetch Rating Distribution
    $distStmt = $pdo->prepare('
        SELECT rating, COUNT(*) as count 
        FROM product_reviews 
        WHERE product_id = :pid AND status = \'approved\' 
        GROUP BY rating
    ');
    $distStmt->execute(['pid' => $productId]);
    $distributionRaw = $distStmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $ratingDistribution = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
    foreach ($distributionRaw as $stars => $cnt) {
        $ratingDistribution[(int) $stars] = (int) $cnt;
    }
    $totalApprovedReviews = array_sum($ratingDistribution);

    // Determine reviews sorting
    $sort = input('sort_reviews', 'latest', 'get');
    $orderBy = 'pr.created_at DESC';
    if ($sort === 'highest') {
        $orderBy = 'pr.rating DESC, pr.created_at DESC';
    } elseif ($sort === 'lowest') {
        $orderBy = 'pr.rating ASC, pr.created_at DESC';
    } elseif ($sort === 'helpful') {
        $orderBy = 'pr.helpful_count DESC, pr.created_at DESC';
    }

    // Determine reviews pagination
    $reviewPage = (int) input('page_reviews', '1', 'get');
    if ($reviewPage < 1) $reviewPage = 1;
    $reviewLimit = 5;
    $reviewOffset = ($reviewPage - 1) * $reviewLimit;

    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM product_reviews WHERE product_id = :pid AND status = \'approved\'');
    $countStmt->execute(['pid' => $productId]);
    $totalReviewsCount = (int) $countStmt->fetchColumn();
    $totalReviewPages = (int) ceil($totalReviewsCount / $reviewLimit);

    // Fetch paginated reviews
    $reviewListStmt = $pdo->prepare("
        SELECT pr.*, u.full_name, u.avatar 
        FROM product_reviews pr 
        JOIN users u ON u.id = pr.user_id 
        WHERE pr.product_id = :pid AND pr.status = 'approved'
        ORDER BY {$orderBy}
        LIMIT :limit OFFSET :offset
    ");
    $reviewListStmt->bindValue('pid', $productId, PDO::PARAM_INT);
    $reviewListStmt->bindValue('limit', $reviewLimit, PDO::PARAM_INT);
    $reviewListStmt->bindValue('offset', $reviewOffset, PDO::PARAM_INT);
    $reviewListStmt->execute();
    $reviews = $reviewListStmt->fetchAll();

    // 5. Track Recently Viewed in Session (max 5 items, excluding current)
    if (empty($_SESSION['recently_viewed'])) {
        $_SESSION['recently_viewed'] = [];
    }
    // Remove if already in list to push to front
    $_SESSION['recently_viewed'] = array_diff($_SESSION['recently_viewed'], [$productId]);
    array_unshift($_SESSION['recently_viewed'], $productId);
    $_SESSION['recently_viewed'] = array_slice($_SESSION['recently_viewed'], 0, 5);

    // Fetch Recently Viewed products details (excluding current)
    $recentProducts = [];
    $recentIds = array_diff($_SESSION['recently_viewed'], [$productId]);
    if (!empty($recentIds)) {
        $inClause = implode(',', array_map('intval', $recentIds));
        $recentProducts = $pdo->query("
            SELECT p.*, 
                   COALESCE(AVG(pr.rating), 0) AS avg_rating,
                   COUNT(pr.id) AS review_count
            FROM products p
            LEFT JOIN product_reviews pr ON pr.product_id = p.id
            WHERE p.id IN ({$inClause}) AND p.is_active = 1
            GROUP BY p.id
            ORDER BY FIELD(p.id, {$inClause})
            LIMIT 4
        ")->fetchAll();
    }

    // 6. Fetch Related Products (same category, excluding current)
    $relatedProductsStmt = $pdo->prepare('
        SELECT p.*, 
               c.name AS category_name,
               b.name AS brand_name,
               COALESCE(AVG(pr.rating), 0) AS avg_rating,
               COUNT(pr.id) AS review_count
        FROM products p
        LEFT JOIN categories c ON c.id = p.category_id
        LEFT JOIN brands b ON b.id = p.brand_id
        LEFT JOIN product_reviews pr ON pr.product_id = p.id
        WHERE p.category_id = :cat_id AND p.id != :curr_id AND p.is_active = 1
        GROUP BY p.id
        ORDER BY p.id DESC
        LIMIT 4
    ');
    $relatedProductsStmt->execute([
        'cat_id' => $categoryId,
        'curr_id' => $productId
    ]);
    $relatedProducts = $relatedProductsStmt->fetchAll();

    // 7. Frequently Bought Together Products
    // Queries up to 2 other products under the same category to suggest FBT bundle
    $fbtProductsStmt = $pdo->prepare('
        SELECT p.id, p.name, p.price, p.discount_price, p.thumbnail, p.slug
        FROM products p
        WHERE p.category_id = :cat_id AND p.id != :curr_id AND p.is_active = 1 AND p.stock > 0
        ORDER BY p.is_featured DESC, p.id ASC
        LIMIT 2
    ');
    $fbtProductsStmt->execute([
        'cat_id' => $categoryId,
        'curr_id' => $productId
    ]);
    $fbtProducts = $fbtProductsStmt->fetchAll();

} catch (PDOException $e) {
    error_log('[product.php] Load error: ' . $e->getMessage());
    if (APP_DEBUG) {
        die('Product Loading Error: ' . htmlspecialchars($e->getMessage()));
    }
}

// Page layout meta
$pageTitle = $product['name'] . ' — ' . ($product['brand_name'] ? $product['brand_name'] . ' — ' : '') . site_name();
$pageDescription = $product['short_description'] ?? truncate($product['description'] ?? '', 160);

$extraStylesheets = ['css/home.css', 'css/product.css'];
$extraScripts = [
    'js/quickview.js',
    'js/cart.js',
    'js/wishlist.js',
    'js/compare.js'
];

require_once __DIR__ . '/header.php';

// Prepare Breadcrumb trail
$breadcrumbs = [
    ['title' => t('shop') ?? 'Shop', 'link' => 'products.php'],
    ['title' => $product['category_name'] ?? 'Category', 'link' => 'products.php?category=' . $product['category_slug']],
    ['title' => $product['name']]
];
?>

<!-- Breadcrumbs -->
<?php include PUBLIC_PATH . '/components/breadcrumb.php'; ?>

<div class="container">
    <!-- Main Product Layout Grid -->
    <div class="product-detail-layout" data-id="<?= $productId ?>" data-name="<?= e($product['name']) ?>">
        
        <!-- Left: Image Gallery -->
        <div class="detail-gallery">
            <div class="detail-main-image-container" id="detailMainImageContainer">
                <?php 
                $mainImg = !empty($gallery) ? $gallery[0] : null;
                $mainImgUrl = image_url($mainImg, 'products');
                ?>
                <img class="detail-main-image" id="detailMainImage" src="<?= e($mainImgUrl) ?>" alt="<?= e($product['name']) ?>">
            </div>
            
            <?php if (!empty($gallery) && count($gallery) > 1): ?>
                <div class="detail-thumbnails">
                    <?php foreach ($gallery as $idx => $img): 
                        $thumbUrl = image_url($img, 'products');
                    ?>
                        <button type="button" class="detail-thumb-btn <?= $idx === 0 ? 'active' : '' ?>" data-large-url="<?= e($thumbUrl) ?>" aria-label="View product image <?= $idx + 1 ?>">
                            <img src="<?= e($thumbUrl) ?>" alt="Thumb <?= $idx + 1 ?>">
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Right: Information Details -->
        <div class="detail-info">
            <div class="detail-header">
                <?php if ($product['brand_name']): ?>
                    <span class="detail-brand"><?= e($product['brand_name']) ?></span>
                <?php endif; ?>
                <h1 class="detail-title"><?= e($product['name']) ?></h1>
                
                <div class="detail-meta-row">
                    <span class="detail-meta-item">Category: <strong><?= e($product['category_name']) ?></strong></span>
                    <span class="detail-stock-badge <?= $product['stock'] > 0 ? 'in-stock' : 'out-of-stock' ?>">
                        <i class="fas <?= $product['stock'] > 0 ? 'fa-check-circle' : 'fa-times-circle' ?>"></i>
                        <?= $product['stock'] > 0 ? 'In Stock' : 'Out of Stock' ?>
                    </span>
                </div>
            </div>

            <!-- Rating Stars Header -->
            <div class="detail-rating-row">
                <?php
                $rating = (float)$product['avg_rating'];
                include PUBLIC_PATH . '/components/rating-stars.php';
                ?>
                <span class="review-count">
                    <?= (float)$product['avg_rating'] > 0 ? number_format((float)$product['avg_rating'], 1) : 'No' ?> ratings 
                    (<a href="#reviewsTabBtn" onclick="switchTab('reviews'); return false;"><?= $product['review_count'] ?> reviews</a>)
                </span>
            </div>

            <!-- Price Block -->
            <div class="detail-price-box">
                <div class="detail-price-main">
                    <?php if ($product['discount_price'] !== null): ?>
                        <span class="detail-price-sale"><?= format_price($product['discount_price']) ?></span>
                        <span class="detail-price-original"><?= format_price($product['price']) ?></span>
                        
                        <?php 
                        $discPct = (int) round((((float)$product['price'] - (float)$product['discount_price']) / (float)$product['price']) * 100);
                        ?>
                        <span class="detail-discount-percent">-<?= $discPct ?>% OFF</span>
                    <?php else: ?>
                        <span class="detail-price-regular"><?= format_price($product['price']) ?></span>
                    <?php endif; ?>
                </div>
                <span class="detail-unit-info">Price per <?= e($product['unit']) ?></span>
            </div>

            <!-- Short Description -->
            <?php if (!empty($product['short_description'])): ?>
                <div class="detail-short-desc">
                    <p><?= e($product['short_description']) ?></p>
                </div>
            <?php endif; ?>

            <!-- Action Block (Qty, Add, Buy, Wish, Compare) -->
            <?php if ($product['stock'] > 0): ?>
                <div class="detail-actions-area">
                    <div class="detail-qty-selector">
                        <button type="button" class="detail-qty-btn" id="btnQtyMinus" aria-label="Decrease Quantity"><i class="fas fa-minus"></i></button>
                        <input type="number" id="detailQtyInput" class="detail-qty-input" value="1" min="1" max="<?= $product['stock'] ?>" readonly>
                        <button type="button" class="detail-qty-btn" id="btnQtyPlus" aria-label="Increase Quantity"><i class="fas fa-plus"></i></button>
                    </div>

                    <div class="detail-buy-buttons">
                        <button type="button" class="btn btn-add-cart btn-primary detail-btn-add" id="detailBtnAdd" data-product-id="<?= $productId ?>">
                            <i class="fas fa-cart-plus"></i> Add to Cart
                        </button>
                        <button type="button" class="btn btn-buy-now btn-accent detail-btn-buy" id="detailBtnBuy" data-product-id="<?= $productId ?>">
                            Buy Now
                        </button>
                    </div>

                    <div class="detail-icon-actions">
                        <button type="button" class="detail-icon-btn btn-wishlist" data-product-id="<?= $productId ?>" title="Add to Wishlist" aria-label="Add to Wishlist">
                            <i class="far fa-heart"></i>
                        </button>
                        <button type="button" class="detail-icon-btn btn-compare" data-product-id="<?= $productId ?>" title="Add to Compare" aria-label="Add to Compare">
                            <i class="fas fa-code-compare"></i>
                        </button>
                    </div>
                </div>
            <?php else: ?>
                <div class="detail-actions-area">
                    <button type="button" class="btn btn-out-stock" style="width: 100%; border-radius: var(--radius-pill);" disabled>
                        <i class="fas fa-ban"></i> Product is Out of Stock
                    </button>
                </div>
            <?php endif; ?>

            <!-- Share block -->
            <div class="detail-share-row">
                <span>Share Product:</span>
                <div class="share-links">
                    <a href="https://facebook.com/sharer/sharer.php?u=<?= urlencode(current_url()) ?>" target="_blank" rel="noopener" class="share-link share-fb" aria-label="Share on Facebook"><i class="fab fa-facebook-f"></i></a>
                    <a href="https://twitter.com/intent/tweet?url=<?= urlencode(current_url()) ?>&text=<?= urlencode($product['name']) ?>" target="_blank" rel="noopener" class="share-link share-tw" aria-label="Share on Twitter"><i class="fab fa-twitter"></i></a>
                    <a href="https://api.whatsapp.com/send?text=<?= urlencode($product['name'] . ' ' . current_url()) ?>" target="_blank" rel="noopener" class="share-link share-wa" aria-label="Share on WhatsApp"><i class="fab fa-whatsapp"></i></a>
                </div>
            </div>

            <!-- Specs footer metadata -->
            <div class="detail-specs-short">
                <div>SKU: <strong><?= e($product['sku']) ?></strong></div>
                <?php if ($product['barcode']): ?>
                    <div>Barcode: <strong><?= e($product['barcode']) ?></strong></div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Product Details Tabs Section -->
    <div class="product-tabs-container">
        <div class="tabs-navigation">
            <button type="button" class="tab-btn active" id="descriptionTabBtn" onclick="switchTab('description')">Description</button>
            <button type="button" class="tab-btn" id="specificationsTabBtn" onclick="switchTab('specifications')">Specifications</button>
            <button type="button" class="tab-btn" id="reviewsTabBtn" onclick="switchTab('reviews')">Reviews (<?= count($reviews) ?>)</button>
        </div>

        <div class="tabs-content">
            <!-- Description Tab -->
            <div class="tab-pane active" id="tabDescription">
                <p><?= nl2br(e($product['description'])) ?></p>
            </div>

            <!-- Specifications Tab -->
            <div class="tab-pane" id="tabSpecifications">
                <table class="specs-table">
                    <tbody>
                        <tr>
                            <th>Product Name</th>
                            <td><?= e($product['name']) ?></td>
                        </tr>
                        <?php if ($product['brand_name']): ?>
                            <tr>
                                <th>Manufacturer/Brand</th>
                                <td><?= e($product['brand_name']) ?></td>
                            </tr>
                        <?php endif; ?>
                        <tr>
                            <th>Category</th>
                            <td><?= e($product['category_name']) ?></td>
                        </tr>
                        <tr>
                            <th>Unit Size</th>
                            <td><?= e($product['unit']) ?></td>
                        </tr>
                        <tr>
                            <th>SKU Code</th>
                            <td><?= e($product['sku']) ?></td>
                        </tr>
                        <?php if ($product['barcode']): ?>
                            <tr>
                                <th>Barcode</th>
                                <td><?= e($product['barcode']) ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="tab-pane" id="tabReviews">
                <div class="reviews-grid-layout" style="display:grid; grid-template-columns: 300px 1fr; gap: var(--space-6); align-items: start;">
                    <!-- Rating Summary Panel -->
                    <div class="rating-summary-panel" style="background:var(--color-surface); border:1px solid var(--color-border); border-radius:var(--radius-md); padding:var(--space-5); box-shadow:var(--shadow-sm);">
                        <h4 style="font-size:15px; font-weight:800; color:var(--color-text); margin-bottom:12px;">Customer Ratings</h4>
                        <div style="display:flex; align-items:baseline; gap:6px;">
                            <span class="summary-score" style="font-size:32px; font-weight:800; color:var(--color-text);"><?= number_format((float)$product['avg_rating'], 1) ?></span>
                            <span style="font-size:14px; color:var(--color-text-muted);">out of 5</span>
                        </div>
                        
                        <div class="summary-stars" style="color:var(--color-warning); font-size:16px; margin:4px 0 8px 0;">
                            <?php
                            $full = (int) floor((float)$product['avg_rating']);
                            $half = ((float)$product['avg_rating'] - $full) >= 0.5 ? 1 : 0;
                            for ($s = 1; $s <= 5; $s++) {
                                if ($s <= $full) {
                                    echo '<i class="fas fa-star"></i>';
                                } elseif ($s === $full + 1 && $half) {
                                    echo '<i class="fas fa-star-half-alt"></i>';
                                } else {
                                    echo '<i class="far fa-star"></i>';
                                }
                            }
                            ?>
                        </div>
                        <div class="summary-count" style="font-size:12px; color:var(--color-text-muted); margin-bottom:16px; border-bottom:1px solid var(--color-border); padding-bottom:12px;">
                            <?= $totalReviewsCount ?> customer rating<?= $totalReviewsCount === 1 ? '' : 's' ?>
                        </div>

                        <!-- Rating Distribution Bars -->
                        <div class="rating-distributions">
                            <?php
                            for ($stars = 5; $stars >= 1; $stars--) {
                                $count = $ratingDistribution[$stars];
                                $percent = $totalApprovedReviews > 0 ? (int) round(($count / $totalApprovedReviews) * 100) : 0;
                                ?>
                                <div class="rating-dist-row" style="display:flex; align-items:center; gap:8px; font-size:12px; margin-bottom:6px;">
                                    <span style="width:24px; text-align:right; font-weight:700; color:var(--color-text);"><?= $stars ?>★</span>
                                    <div class="progress-bar-wrapper" style="flex:1; height:8px; background:#e7e9ec; border-radius:4px; overflow:hidden;">
                                        <div class="progress-bar-fill" style="width:<?= $percent ?>%; height:100%; background:var(--color-warning);"></div>
                                    </div>
                                    <span style="width:32px; text-align:left; color:var(--color-text-muted);"><?= $percent ?>%</span>
                                    <span style="color:var(--color-text-faint); font-size:10px;">(<?= $count ?>)</span>
                                </div>
                                <?php
                            }
                            ?>
                        </div>
                    </div>

                    <!-- Reviews List & Submission -->
                    <div class="reviews-main-panel">
                        <!-- Write/Edit Review Form -->
                        <div class="review-submit-section" style="margin-bottom: var(--space-6);">
                            <?php include PUBLIC_PATH . '/components/review-form.php'; ?>
                        </div>

                        <!-- Sorting & Filter Actions -->
                        <div class="reviews-filter-bar" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; border-bottom:1px solid var(--color-border); padding-bottom:12px; flex-wrap:wrap; gap:8px;">
                            <h4 style="font-size:15px; font-weight:800; color:var(--color-text); margin:0;">Customer Reviews</h4>
                            <form method="get" action="#tabReviews" style="display:flex; align-items:center; gap:8px;">
                                <input type="hidden" name="slug" value="<?= e($product['slug']) ?>">
                                <label for="reviewSortSelect" style="font-size:12px; color:var(--color-text-muted);">Sort by:</label>
                                <select id="reviewSortSelect" name="sort_reviews" onchange="this.form.submit()" style="font-size:12px; padding:6px 12px; border:1px solid var(--color-border); border-radius:var(--radius-pill); outline:none; background:#fff; cursor:pointer;">
                                    <option value="latest" <?= $sort === 'latest' ? 'selected' : '' ?>>Latest</option>
                                    <option value="highest" <?= $sort === 'highest' ? 'selected' : '' ?>>Highest Rating</option>
                                    <option value="lowest" <?= $sort === 'lowest' ? 'selected' : '' ?>>Lowest Rating</option>
                                    <option value="helpful" <?= $sort === 'helpful' ? 'selected' : '' ?>>Most Helpful</option>
                                </select>
                            </form>
                        </div>

                        <!-- Customer Reviews List -->
                        <div class="reviews-list" style="display:flex; flex-direction:column; gap: var(--space-4);">
                            <?php if (!empty($reviews)): ?>
                                <?php foreach ($reviews as $review): ?>
                                    <?php include PUBLIC_PATH . '/components/review-card.php'; ?>
                                <?php endforeach; ?>
                                
                                <!-- Reviews Pagination -->
                                <?php if ($totalReviewPages > 1): ?>
                                    <div class="reviews-pagination" style="display:flex; gap:6px; margin-top:24px; justify-content:center;">
                                        <?php for ($p = 1; $p <= $totalReviewPages; $p++): ?>
                                            <a href="?slug=<?= e($product['slug']) ?>&sort_reviews=<?= e($sort) ?>&page_reviews=<?= $p ?>#tabReviews" class="pagination-link <?= $reviewPage === $p ? 'active' : '' ?>" style="display:inline-flex; width:36px; height:36px; align-items:center; justify-content:center; border:1px solid var(--color-border); border-radius:50%; text-decoration:none; font-size:12px; font-weight:700; <?= $reviewPage === $p ? 'background:var(--color-primary); color:#fff; border-color:var(--color-primary);' : 'color:var(--color-text);' ?>"><?= $p ?></a>
                                        <?php endfor; ?>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <p style="font-size:13px; color:var(--color-text-muted); text-align:center; padding:24px 0; background:var(--color-surface); border:1px solid var(--color-border); border-radius:var(--radius-md);">There are no reviews for this product yet. Be the first to share your thoughts!</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div></div>

    <!-- Frequently Bought Together Bundle Grid -->
    <?php include PUBLIC_PATH . '/components/frequently-bought.php'; ?>

    <!-- Related Products Section -->
    <?php if (!empty($relatedProducts)): ?>
        <section class="cross-sells-wrapper">
            <h2 class="cross-sells-title">Related Products</h2>
            <div class="products-grid">
                <?php 
                foreach ($relatedProducts as $p) {
                    $product = $p; // bind product for the template
                    include PUBLIC_PATH . '/components/product-card.php';
                }
                ?>
            </div>
        </section>
    <?php endif; ?>

    <!-- Recently Viewed Section -->
    <?php if (!empty($recentProducts)): ?>
        <section class="cross-sells-wrapper">
            <h2 class="cross-sells-title">Recently Viewed</h2>
            <div class="products-grid">
                <?php 
                foreach ($recentProducts as $p) {
                    $product = $p; // bind product for the template
                    include PUBLIC_PATH . '/components/product-card.php';
                }
                ?>
            </div>
        </section>
    <?php endif; ?>
</div>

<!-- Tab Switcher, Gallery swap, Star Selector, FBT calculation Scripts -->
<script>
(function() {
    'use strict';

    // 1. Gallery Thumbnail Swap
    const mainImg = document.getElementById('detailMainImage');
    const thumbBtns = document.querySelectorAll('.detail-thumb-btn');
    
    thumbBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            thumbBtns.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            const newUrl = btn.dataset.largeUrl;
            if (mainImg && newUrl) {
                mainImg.src = newUrl;
            }
        });
    });

    // 2. Tab Switching
    window.switchTab = function switchTab(tabName) {
        const descBtn = document.getElementById('descriptionTabBtn');
        const specBtn = document.getElementById('specificationsTabBtn');
        const revBtn = document.getElementById('reviewsTabBtn');

        const descPane = document.getElementById('tabDescription');
        const specPane = document.getElementById('tabSpecifications');
        const revPane = document.getElementById('tabReviews');

        // Reset
        [descBtn, specBtn, revBtn].forEach(b => b?.classList.remove('active'));
        [descPane, specPane, revPane].forEach(p => p?.classList.remove('active'));

        if (tabName === 'description') {
            descBtn?.classList.add('active');
            descPane?.classList.add('active');
        } else if (tabName === 'specifications') {
            specBtn?.classList.add('active');
            specPane?.classList.add('active');
        } else if (tabName === 'reviews') {
            revBtn?.classList.add('active');
            revPane?.classList.add('active');
        }
    };

    // 3. Star Selector inside Submit Review Form
    const ratingStars = document.querySelectorAll('#ratingSelectStars .rating-select-star');
    const ratingInput = document.getElementById('ratingInput');

    if (ratingStars && ratingInput) {
        // Handle prefilled value if form failed validation
        const prefillVal = parseInt(ratingInput.value);
        if (prefillVal > 0) {
            highlightStars(prefillVal);
        }

        ratingStars.forEach(star => {
            star.addEventListener('click', () => {
                const val = parseInt(star.dataset.value);
                ratingInput.value = val.toString();
                highlightStars(val);
            });

            star.addEventListener('mouseenter', () => {
                const val = parseInt(star.dataset.value);
                highlightStars(val);
            });

            star.addEventListener('mouseleave', () => {
                const currentVal = parseInt(ratingInput.value || '0');
                highlightStars(currentVal);
            });
        });
    }

    function highlightStars(val) {
        ratingStars.forEach(star => {
            const starVal = parseInt(star.dataset.value);
            if (starVal <= val) {
                star.classList.replace('far', 'fas');
                star.classList.add('selected');
            } else {
                star.classList.replace('fas', 'far');
                star.classList.remove('selected');
            }
        });
    }

    // 4. Quantity Adjuster
    const qtyInput = document.getElementById('detailQtyInput');
    const minusBtn = document.getElementById('btnQtyMinus');
    const plusBtn = document.getElementById('btnQtyPlus');

    if (qtyInput && minusBtn && plusBtn) {
        const maxLimit = parseInt(qtyInput.getAttribute('max') || '999');
        minusBtn.addEventListener('click', () => {
            let val = parseInt(qtyInput.value);
            if (val > 1) {
                qtyInput.value = (val - 1).toString();
            }
        });
        plusBtn.addEventListener('click', () => {
            let val = parseInt(qtyInput.value);
            if (val < maxLimit) {
                qtyInput.value = (val + 1).toString();
            }
        });
    }

    // 5. Add to Cart inside product details
    const addBtn = document.getElementById('detailBtnAdd');
    addBtn?.addEventListener('click', async () => {
        const pId = addBtn.dataset.productId;
        const qty = qtyInput ? qtyInput.value : '1';

        const originalText = addBtn.innerHTML;
        addBtn.disabled = true;
        addBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';

        const json = await window.apiPost('ajax/add_to_cart.php', {
            product_id: pId,
            quantity: qty
        });

        addBtn.disabled = false;
        addBtn.innerHTML = originalText;

        if (json.success) {
            window.showToast?.(json.message, 'success');
            const cartBadge = document.getElementById('cartCount');
            if (cartBadge && json.data?.cart_count !== undefined) {
                cartBadge.textContent = json.data.cart_count.toString();
            }
        }
    });

    // Buy Now inside product details
    const buyBtn = document.getElementById('detailBtnBuy');
    buyBtn?.addEventListener('click', async () => {
        const pId = buyBtn.dataset.productId;
        const qty = qtyInput ? qtyInput.value : '1';

        buyBtn.disabled = true;

        const json = await window.apiPost('ajax/add_to_cart.php', {
            product_id: pId,
            quantity: qty
        });

        if (json.success) {
            window.location.href = 'checkout.php';
        } else {
            buyBtn.disabled = false;
        }
    });

    // 6. Frequently Bought Together (FBT) calculations
    const fbtContainer = document.querySelector('.fbt-container');
    if (fbtContainer) {
        const fbtCheckboxes = fbtContainer.querySelectorAll('.fbt-checkbox');
        const fbtTotalEl = document.getElementById('fbtTotalPrice');
        const fbtCountEl = document.getElementById('fbtTotalCount');
        const btnAddFbtAll = document.getElementById('btnFbtAddAll');

        const calculateFbtTotal = () => {
            const basePrice = parseFloat(fbtTotalEl.dataset.basePrice);
            let total = basePrice;
            let count = 1;

            fbtCheckboxes.forEach(checkbox => {
                if (checkbox.checked) {
                    const price = parseFloat(checkbox.closest('.fbt-bundle-item').dataset.price);
                    total += price;
                    count++;
                }
            });

            // Format price: BDT key mapping
            fbtTotalEl.textContent = '৳' + total.toFixed(2);
            fbtCountEl.textContent = `Bundle Total (for ${count} items)`;
        };

        fbtCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', calculateFbtTotal);
        });

        // Add all selected FBT items to cart sequentially
        btnAddFbtAll?.addEventListener('click', async () => {
            const pIds = [document.querySelector('.product-detail-layout').dataset.id];
            
            fbtCheckboxes.forEach(checkbox => {
                if (checkbox.checked) {
                    pIds.push(checkbox.closest('.fbt-bundle-item').dataset.id);
                }
            });

            btnAddFbtAll.disabled = true;
            const origHtml = btnAddFbtAll.innerHTML;
            btnAddFbtAll.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding Bundle...';

            let countAdded = 0;
            let lastCartCount = 0;

            for (const pid of pIds) {
                const json = await window.apiPost('ajax/add_to_cart.php', {
                    product_id: pid,
                    quantity: 1
                });
                if (json.success) {
                    countAdded++;
                    lastCartCount = json.data?.cart_count ?? lastCartCount;
                }
            }

            btnAddFbtAll.disabled = false;
            btnAddFbtAll.innerHTML = origHtml;

            if (countAdded > 0) {
                window.showToast?.(`Successfully added ${countAdded} bundle items to your cart!`, 'success');
                const badge = document.getElementById('cartCount');
                if (badge && lastCartCount > 0) {
                    badge.textContent = lastCartCount.toString();
                }
            }
        });
    }

})();
</script>

<?php
require_once __DIR__ . '/footer.php';
?>
