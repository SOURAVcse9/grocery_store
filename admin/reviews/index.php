<?php
/**
 * ==========================================================================
 * admin/reviews/index.php — Administration Reviews Moderation Page
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/../../public/dbconnect.php';
require_once __DIR__ . '/../middleware/auth_middleware.php';
require_admin_auth();

$pdo = db();

/**
 * sync_product_rating_stats()
 * Recalculates and updates avg_rating and review_count in products table.
 */
function sync_product_rating_stats(int $productId, PDO $pdo): void
{
    $stmt = $pdo->prepare("
        UPDATE products SET 
            avg_rating = COALESCE((SELECT ROUND(AVG(rating), 2) FROM product_reviews WHERE product_id = :pid AND status = 'approved'), 0.00),
            review_count = (SELECT COUNT(*) FROM product_reviews WHERE product_id = :pid2 AND status = 'approved')
        WHERE id = :pid3
    ");
    $stmt->execute([
        'pid' => $productId,
        'pid2' => $productId,
        'pid3' => $productId
    ]);
}

// Handle Moderation POST actions
if (method_is('post')) {
    verify_csrf_or_fail();
    
    $action = input('action', '');
    $reviewId = (int) input('review_id', '0');
    
    if ($reviewId > 0) {
        try {
            // Find review to get product_id
            $stmtRev = $pdo->prepare('SELECT product_id, review_images FROM product_reviews WHERE id = :id LIMIT 1');
            $stmtRev->execute(['id' => $reviewId]);
            $reviewData = $stmtRev->fetch();
            
            if ($reviewData) {
                $productId = (int) $reviewData['product_id'];
                
                if ($action === 'approve') {
                    $up = $pdo->prepare("UPDATE product_reviews SET status = 'approved', updated_at = NOW() WHERE id = :id");
                    $up->execute(['id' => $reviewId]);
                    sync_product_rating_stats($productId, $pdo);
                    flash('reviews_admin', 'Review approved successfully.', 'success');
                } elseif ($action === 'reject') {
                    $up = $pdo->prepare("UPDATE product_reviews SET status = 'rejected', updated_at = NOW() WHERE id = :id");
                    $up->execute(['id' => $reviewId]);
                    sync_product_rating_stats($productId, $pdo);
                    flash('reviews_admin', 'Review marked as rejected.', 'success');
                } elseif ($action === 'hide') {
                    $up = $pdo->prepare("UPDATE product_reviews SET status = 'hidden', updated_at = NOW() WHERE id = :id");
                    $up->execute(['id' => $reviewId]);
                    sync_product_rating_stats($productId, $pdo);
                    flash('reviews_admin', 'Review hidden from catalog listings.', 'success');
                } elseif ($action === 'delete') {
                    // Delete images from disk
                    if (!empty($reviewData['review_images'])) {
                        $images = json_decode($reviewData['review_images'], true);
                        if (is_array($images)) {
                            foreach ($images as $img) {
                                $fullPath = __DIR__ . '/../../' . $img;
                                if (file_exists($fullPath)) {
                                    @unlink($fullPath);
                                }
                            }
                        }
                    }
                    
                    $del = $pdo->prepare('DELETE FROM product_reviews WHERE id = :id');
                    $del->execute(['id' => $reviewId]);
                    sync_product_rating_stats($productId, $pdo);
                    flash('reviews_admin', 'Review permanently deleted from database.', 'success');
                }
            }
        } catch (PDOException $e) {
            error_log('[admin/reviews] Status mutation fail: ' . $e->getMessage());
            flash('reviews_admin', 'Failed to update review status due to database error.', 'error');
        }
    }
    
    redirect(current_url());
}

// Fetch Search and Filter query parameters
$search = trim(input('search', '', 'get'));
$ratingFilter = (int) input('rating', '0', 'get');
$statusFilter = trim(input('status', '', 'get'));
$sort = trim(input('sort', 'newest', 'get'));
$page = (int) input('page', '1', 'get');

if ($page < 1) $page = 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Construct SQL query
$where = ['1=1'];
$params = [];

if (!empty($search)) {
    $where[] = '(u.full_name LIKE :search OR p.name LIKE :search OR pr.review_title LIKE :search OR pr.review_comment LIKE :search)';
    $params['search'] = '%' . $search . '%';
}

if ($ratingFilter > 0) {
    $where[] = 'pr.rating = :rating';
    $params['rating'] = $ratingFilter;
}

if (!empty($statusFilter)) {
    $where[] = 'pr.status = :status';
    $params['status'] = $statusFilter;
}

$whereClause = 'WHERE ' . implode(' AND ', $where);

// Sort mapping
$orderBy = 'pr.created_at DESC';
if ($sort === 'oldest') {
    $orderBy = 'pr.created_at ASC';
} elseif ($sort === 'rating_desc') {
    $orderBy = 'pr.rating DESC, pr.created_at DESC';
} elseif ($sort === 'rating_asc') {
    $orderBy = 'pr.rating ASC, pr.created_at DESC';
}

try {
    // Count total matches
    $countSql = "
        SELECT COUNT(*) 
        FROM product_reviews pr
        JOIN users u ON u.id = pr.user_id
        JOIN products p ON p.id = pr.product_id
        {$whereClause}
    ";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $totalCount = (int) $countStmt->fetchColumn();
    $totalPages = (int) ceil($totalCount / $limit);

    // Fetch review logs
    $selectSql = "
        SELECT pr.*, u.full_name, u.avatar, p.name AS product_name, p.slug AS product_slug
        FROM product_reviews pr
        JOIN users u ON u.id = pr.user_id
        JOIN products p ON p.id = pr.product_id
        {$whereClause}
        ORDER BY {$orderBy}
        LIMIT :limit OFFSET :offset
    ";
    
    $stmt = $pdo->prepare($selectSql);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $reviews = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log('[admin/reviews] list fetch fail: ' . $e->getMessage());
    $reviews = [];
    $totalCount = 0;
    $totalPages = 1;
}

$pageTitle = 'Manage Reviews — ' . site_name();
require_once __DIR__ . '/../layouts/dashboard_layout.php';
?>

<div style="margin-top: var(--space-6); margin-bottom: var(--space-8);">
    
    <!-- Admin Header Breadcrumbs link -->
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-5); flex-wrap:wrap; gap:16px;">
        <div>
            <h1 style="font-size:var(--fs-xl); font-weight:800; color:var(--color-text); margin:0;">Reviews Moderation</h1>
            <p style="font-size:var(--fs-sm); color:var(--color-text-muted); margin:4px 0 0 0;">Moderate, approve, reject, or hide reviews across product catalog pages.</p>
        </div>
        <a href="../index.php" class="btn btn-secondary" style="border-radius:var(--radius-pill); font-weight:700; padding:10px 20px;"><i class="fas fa-arrow-left"></i> Return to Dashboard</a>
    </div>

    <!-- Feedback messages -->
    <?php if (has_flash('reviews_admin')): ?>
        <div style="background:#e6fcf5; border:1px solid #c3fae8; color:#0ca678; padding:14px; border-radius:var(--radius-md); font-size:var(--fs-sm); font-weight:600; margin-bottom:var(--space-4);">
            <?= flash('reviews_admin') ?>
        </div>
    <?php endif; ?>

    <!-- Search & Filter Controls Panel -->
    <div class="dashboard-card" style="padding:var(--space-5); margin-bottom:var(--space-4);">
        <form method="get" style="display:grid; grid-template-columns: 1fr 150px 150px 150px auto; gap:12px; align-items:end; width:100%;" class="grid-5">
            <!-- Search field -->
            <div class="form-field-group" style="margin:0;">
                <label for="adminSearch" style="font-size:11px; font-weight:700; color:var(--color-text-muted); text-transform:uppercase;">Search Query</label>
                <input type="text" id="adminSearch" name="search" placeholder="Search by name, product, comment..." value="<?= e($search) ?>" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
            </div>

            <!-- Rating Filter -->
            <div class="form-field-group" style="margin:0;">
                <label for="adminRating" style="font-size:11px; font-weight:700; color:var(--color-text-muted); text-transform:uppercase;">Rating Stars</label>
                <select id="adminRating" name="rating" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none; background:#fff; cursor:pointer;">
                    <option value="0">All Stars</option>
                    <?php for ($s = 5; $s >= 1; $s--): ?>
                        <option value="<?= $s ?>" <?= $ratingFilter === $s ? 'selected' : '' ?>><?= $s ?>★ Rating</option>
                    <?php endfor; ?>
                </select>
            </div>

            <!-- Status Filter -->
            <div class="form-field-group" style="margin:0;">
                <label for="adminStatus" style="font-size:11px; font-weight:700; color:var(--color-text-muted); text-transform:uppercase;">Status</label>
                <select id="adminStatus" name="status" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none; background:#fff; cursor:pointer;">
                    <option value="">All Statuses</option>
                    <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="approved" <?= $statusFilter === 'approved' ? 'selected' : '' ?>>Approved</option>
                    <option value="rejected" <?= $statusFilter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                    <option value="hidden" <?= $statusFilter === 'hidden' ? 'selected' : '' ?>>Hidden</option>
                </select>
            </div>

            <!-- Sort option -->
            <div class="form-field-group" style="margin:0;">
                <label for="adminSort" style="font-size:11px; font-weight:700; color:var(--color-text-muted); text-transform:uppercase;">Sort Order</label>
                <select id="adminSort" name="sort" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none; background:#fff; cursor:pointer;">
                    <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest First</option>
                    <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Oldest First</option>
                    <option value="rating_desc" <?= $sort === 'rating_desc' ? 'selected' : '' ?>>Highest Rating</option>
                    <option value="rating_asc" <?= $sort === 'rating_asc' ? 'selected' : '' ?>>Lowest Rating</option>
                </select>
            </div>

            <!-- Buttons -->
            <div style="display:flex; gap:8px;">
                <button type="submit" class="btn btn-primary" style="padding:10px 20px; border:none; border-radius:var(--radius-pill); font-weight:700;">Filter</button>
                <a href="index.php" class="btn btn-secondary" style="padding:10px 20px; border-radius:var(--radius-pill); text-decoration:none; display:inline-block; text-align:center; font-weight:700;">Clear</a>
            </div>
        </form>
    </div>

    <!-- Review logs table wrapper -->
    <div class="dashboard-card" style="padding:0; overflow:hidden;">
        <div style="padding:var(--space-4) var(--space-5); border-bottom:1px solid var(--color-border); background:var(--color-bg); display:flex; justify-content:space-between; align-items:center;">
            <strong style="font-size:var(--fs-sm); color:var(--color-text);"><?= $totalCount ?> matching reviews</strong>
        </div>

        <div style="overflow-x:auto;">
            <table style="width:100%; border-collapse:collapse; text-align:left;" class="admin-reviews-table">
                <thead>
                    <tr style="background:#f8f9fa; border-bottom:1px solid var(--color-border); font-size:11px; text-transform:uppercase; color:var(--color-text-muted); font-weight:700;">
                        <th style="padding:16px 20px;">Reviewer / Product</th>
                        <th style="padding:16px 20px; width:80px;">Rating</th>
                        <th style="padding:16px 20px;">Comments</th>
                        <th style="padding:16px 20px; width:120px;">Review Images</th>
                        <th style="padding:16px 20px; width:100px;">Status</th>
                        <th style="padding:16px 20px; width:220px; text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody style="font-size:13px; color:var(--color-text-muted);">
                    <?php if (!empty($reviews)): ?>
                        <?php foreach ($reviews as $rev): 
                            $status = $rev['status'];
                            $badgeColor = '#adb5bd';
                            if ($status === 'approved') $badgeColor = '#0ca678';
                            elseif ($status === 'pending') $badgeColor = '#f59f00';
                            elseif ($status === 'rejected') $badgeColor = '#f03e3e';
                            
                            $verified = (bool) $rev['verified_purchase'];
                        ?>
                            <tr style="border-bottom:1px solid var(--color-border); vertical-align:top;">
                                <td style="padding:16px 20px;">
                                    <strong style="color:var(--color-text); display:block;"><?= e($rev['full_name']) ?></strong>
                                    <span style="font-size:11px; display:block; margin-top:2px;">
                                        Product: <a href="<?= url_for('product.php?slug=' . $rev['product_slug']) ?>" target="_blank" style="color:var(--color-primary); font-weight:600;"><?= e($rev['product_name']) ?></a>
                                    </span>
                                    <?php if ($verified): ?>
                                        <span style="font-size:10px; background:#e6fcf5; color:#0ca678; padding:2px 6px; border-radius:4px; font-weight:700; display:inline-block; margin-top:6px;"><i class="fas fa-circle-check"></i> Verified Purchase</span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding:16px 20px; color:var(--color-warning); font-weight:700;">
                                    <?= (int) $rev['rating'] ?>★
                                </td>
                                <td style="padding:16px 20px;">
                                    <?php if (!empty($rev['review_title'])): ?>
                                        <strong style="color:var(--color-text); display:block; margin-bottom:4px;"><?= e($rev['review_title']) ?></strong>
                                    <?php endif; ?>
                                    <p style="margin:0; font-size:12px; line-height:1.6; max-width:400px;"><?= nl2br(e($rev['review_comment'])) ?></p>
                                    <span style="font-size:10px; color:var(--color-text-faint); display:block; margin-top:6px;">Submitted: <?= date('M d, Y H:i', strtotime($rev['created_at'])) ?></span>
                                </td>
                                <td style="padding:16px 20px;">
                                    <?php if (!empty($rev['review_images'])): 
                                        $imgs = json_decode($rev['review_images'], true);
                                        if (is_array($imgs) && !empty($imgs)):
                                    ?>
                                        <div style="display:flex; gap:6px; flex-wrap:wrap;">
                                            <?php foreach ($imgs as $img): ?>
                                                <a href="<?= e(asset($img)) ?>" target="_blank" style="display:inline-block; border:1px solid var(--color-border); border-radius:2px; overflow:hidden;">
                                                    <img src="<?= e(asset($img)) ?>" alt="Review image" style="width:40px; height:40px; object-fit:cover;">
                                                </a>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; else: ?>
                                        <span style="color:var(--color-text-faint); font-size:11px;">None</span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding:16px 20px;">
                                    <span style="background:<?= $badgeColor ?>; color:#fff; font-size:11px; font-weight:700; padding:4px 8px; border-radius:4px; text-transform:uppercase;">
                                        <?= $status ?>
                                    </span>
                                </td>
                                <td style="padding:16px 20px; text-align:right;">
                                    <div style="display:inline-flex; gap:6px; flex-wrap:wrap; justify-content:flex-end;">
                                        
                                        <!-- Approve -->
                                        <?php if ($status !== 'approved'): ?>
                                            <form method="post" style="display:inline;" onsubmit="return confirm('Approve this review for public listing?');">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="approve">
                                                <input type="hidden" name="review_id" value="<?= $rev['id'] ?>">
                                                <button type="submit" class="btn btn-primary" style="padding:6px 12px; font-size:11px; border-radius:var(--radius-pill); border:none; background:#0ca678;"><i class="fas fa-check"></i> Approve</button>
                                            </form>
                                        <?php endif; ?>

                                        <!-- Reject -->
                                        <?php if ($status !== 'rejected'): ?>
                                            <form method="post" style="display:inline;" onsubmit="return confirm('Reject this review? It will be flagged.');">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="reject">
                                                <input type="hidden" name="review_id" value="<?= $rev['id'] ?>">
                                                <button type="submit" class="btn btn-secondary" style="padding:6px 12px; font-size:11px; border-radius:var(--radius-pill); border:none; background:#f03e3e; color:#fff;"><i class="fas fa-times"></i> Reject</button>
                                            </form>
                                        <?php endif; ?>

                                        <!-- Hide -->
                                        <?php if ($status !== 'hidden' && $status === 'approved'): ?>
                                            <form method="post" style="display:inline;" onsubmit="return confirm('Hide this review from the storefront catalog?');">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="hide">
                                                <input type="hidden" name="review_id" value="<?= $rev['id'] ?>">
                                                <button type="submit" class="btn btn-secondary" style="padding:6px 12px; font-size:11px; border-radius:var(--radius-pill); border:none; background:#adb5bd; color:#fff;"><i class="fas fa-eye-slash"></i> Hide</button>
                                            </form>
                                        <?php endif; ?>

                                        <!-- Delete -->
                                        <form method="post" style="display:inline;" onsubmit="return confirm('PERMANENTLY DELETE this review and remove its uploaded images? This action is irreversible.');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="review_id" value="<?= $rev['id'] ?>">
                                            <button type="submit" class="btn btn-secondary" style="padding:6px 12px; font-size:11px; border-radius:var(--radius-pill); border:none; background:#212529; color:#fff;"><i class="fas fa-trash-can"></i> Delete</button>
                                        </form>

                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="padding:32px; text-align:center; color:var(--color-text-muted);">No reviews match your search filters.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div style="display:flex; justify-content:center; padding:20px; background:var(--color-bg); border-top:1px solid var(--color-border); gap:6px;">
                <?php for ($p = 1; $p <= $totalPages; $p++): 
                    $paramsQuery = $_GET;
                    $paramsQuery['page'] = $p;
                    $url = '?' . http_build_query($paramsQuery);
                ?>
                    <a href="<?= $url ?>" class="pagination-link <?= $page === $p ? 'active' : '' ?>" style="display:inline-flex; width:36px; height:36px; align-items:center; justify-content:center; border:1px solid var(--color-border); border-radius:50%; text-decoration:none; font-size:12px; font-weight:700; <?= $page === $p ? 'background:var(--color-primary); color:#fff; border-color:var(--color-primary);' : 'color:var(--color-text);' ?>"><?= $p ?></a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    </div>

</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
</div>
