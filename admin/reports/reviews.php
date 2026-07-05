<?php
/**
 * ==========================================================================
 * admin/reports/reviews.php — Review and Feedback Analytics
 * ==========================================================================
 */

declare(strict_types=1);

$pageTitle = 'Feedback Report — GroCo Admin';
require_once __DIR__ . '/../layouts/dashboard_layout.php';
require_admin_permission('reports.view');

$pdo = db();

try {
    // Reviews details grouped by rating stars
    $ratings = $pdo->query("
        SELECT rating, COUNT(*) AS count
        FROM product_reviews
        GROUP BY rating
        ORDER BY rating DESC
    ")->fetchAll();

    // Average rating
    $avgRating = $pdo->query("SELECT COALESCE(ROUND(AVG(rating), 2), 0) FROM product_reviews")->fetchColumn();
    $totalReviews = $pdo->query("SELECT COUNT(*) FROM product_reviews")->fetchColumn();

} catch (PDOException $e) {
    error_log('[admin/reports/reviews] failed: ' . $e->getMessage());
    $ratings = [];
    $avgRating = $totalReviews = 0;
}
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-5); flex-wrap:wrap; gap:16px;">
    <div>
        <h1 style="font-size:var(--fs-xl); font-weight:800; color:var(--color-text); margin:0;">Reviews & Ratings Report</h1>
        <p style="font-size:var(--fs-sm); color:var(--color-text-muted); margin:4px 0 0 0;">Inspect satisfaction trends, ratings volume breakdowns, and overall average scores.</p>
    </div>
    <a href="dashboard.php" class="btn btn-secondary" style="border-radius:var(--radius-pill); font-weight:700;"><i class="fas fa-arrow-left"></i> Reports Hub</a>
</div>

<!-- Stats row -->
<div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px; margin-bottom:var(--space-5);" class="stats-row">
    <div class="dashboard-card" style="margin:0; padding:var(--space-4); border-left: 4px solid var(--color-primary); text-align:center;">
        <span style="font-size:10px; font-weight:700; color:var(--color-text-faint); text-transform:uppercase;">Overall Average Rating</span>
        <h2 style="margin:4px 0 0 0; font-size:24px; font-weight:800; color:#f08c00;"><i class="fas fa-star"></i> <?= $avgRating ?> / 5.00</h2>
    </div>
    <div class="dashboard-card" style="margin:0; padding:var(--space-4); border-left: 4px solid #339af0; text-align:center;">
        <span style="font-size:10px; font-weight:700; color:var(--color-text-faint); text-transform:uppercase;">Total Feedbacks Logged</span>
        <h2 style="margin:4px 0 0 0; font-size:24px; font-weight:800; color:var(--color-text);"><?= $totalReviews ?></h2>
    </div>
</div>

<div class="dashboard-card" style="padding:0; overflow:hidden;">
    <div class="admin-table-wrapper" style="border:none;">
        <table class="admin-data-table" style="font-size:13px;">
            <thead>
                <tr>
                    <th style="padding:16px 20px;">Rating Stars</th>
                    <th style="padding:16px 20px; text-align:center; width:220px;">Feedbacks Count</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($ratings)): ?>
                    <?php foreach ($ratings as $row): ?>
                        <tr style="border-bottom:1px solid var(--color-border); vertical-align:middle;">
                            <td style="padding:12px 20px; color:#f08c00; font-weight:700;">
                                <?= str_repeat('★', (int)$row['rating']) ?><?= str_repeat('☆', 5 - (int)$row['rating']) ?> (<?= $row['rating'] ?> Stars)
                            </td>
                            <td style="padding:12px 20px; text-align:center; font-weight:700;"><?= $row['count'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="2" style="padding:32px; text-align:center; color:var(--color-text-faint);">No product reviews logged.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
</div>
