<?php
/**
 * ==========================================================================
 * admin/testimonials/edit.php — Modify Existing Customer Testimonial Feedback
 * ==========================================================================
 */

declare(strict_types=1);

$pageTitle = 'Edit Testimonial — GroCo Admin';
require_once __DIR__ . '/../layouts/dashboard_layout.php';
require_admin_permission('testimonials.manage');

$pdo = db();
$testiId = (int) input('id', '0', 'get');

if ($testiId <= 0) {
    header('Location: index.php');
    exit;
}

$error = null;

try {
    $stmt = $pdo->prepare("SELECT * FROM testimonials WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $testiId]);
    $t = $stmt->fetch();

    if (!$t) {
        flash('testi_msg', 'Testimonial details not found.', 'error');
        header('Location: index.php');
        exit;
    }
} catch (PDOException $e) {
    error_log('[admin/testimonials/edit] load failed: ' . $e->getMessage());
    header('Location: index.php');
    exit;
}

if (method_is('post')) {
    if (!verify_csrf()) {
        $error = 'Invalid security request (CSRF check failed).';
    } else {
        $name = trim(input('name', ''));
        $designation = trim(input('designation', ''));
        $rating = (int) input('rating', '5');
        $comment = trim(input('comment', ''));
        $isActive = (int) input('is_active', '1');

        if (empty($name) || empty($comment)) {
            $error = 'Customer Name and Comment are required fields.';
        } else {
            try {
                $up = $pdo->prepare("
                    UPDATE testimonials 
                    SET name = :name, designation = :desig, rating = :rating, 
                        comment = :comment, is_active = :active
                    WHERE id = :id
                ");
                $up->execute([
                    'name'    => $name,
                    'desig'   => $designation,
                    'rating'  => $rating,
                    'comment' => $comment,
                    'active'  => $isActive,
                    'id'      => $testiId
                ]);

                log_admin_activity('testimonials.edit', "Updated testimonial feedback for: '{$name}'");
                flash('testi_msg', 'Testimonial feedback updated successfully!', 'success');
                header('Location: index.php');
                exit;
            } catch (PDOException $e) {
                error_log('[admin/testimonials/edit] Save failed: ' . $e->getMessage());
                $error = 'Failed to update testimonial due to database error.';
            }
        }
    }
}
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-5);">
    <div>
        <h1 style="font-size:var(--fs-xl); font-weight:800; color:var(--color-text); margin:0;">Edit Testimonial</h1>
        <p style="font-size:var(--fs-sm); color:var(--color-text-muted); margin:4px 0 0 0;">Update testimonial detail card configs.</p>
    </div>
    <a href="index.php" class="btn btn-secondary" style="border-radius:var(--radius-pill); font-weight:700; padding:10px 20px;"><i class="fas fa-arrow-left"></i> Testimonials List</a>
</div>

<!-- Errors display -->
<?php if ($error !== null): ?>
    <div style="background:#fff5f5; border:1px solid #ffe3e3; color:#e03131; padding:12px; border-radius:var(--radius-sm); font-size:var(--fs-sm); font-weight:600; margin-bottom:var(--space-4);">
        <i class="fas fa-circle-exclamation" style="margin-right:4px;"></i> <?= $error ?>
    </div>
<?php endif; ?>

<div class="dashboard-card" style="padding:var(--space-6); max-width: 600px;">
    <form method="post" class="auth-form">
        <?= csrf_field() ?>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;" class="grid-2">
            <div class="form-field-group">
                <label for="testiName" style="font-weight:700;">Customer Name *</label>
                <input type="text" id="testiName" name="name" required value="<?= e($t['name']) ?>" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
            </div>
            <div class="form-field-group">
                <label for="testiDesig" style="font-weight:700;">Designation / Title</label>
                <input type="text" id="testiDesig" name="designation" value="<?= e($t['designation'] ?? '') ?>" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
            </div>
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;" class="grid-2">
            <div class="form-field-group">
                <label for="testiRating" style="font-weight:700;">Rating Rating (1-5 Stars)</label>
                <select id="testiRating" name="rating" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none; background:#fff;">
                    <option value="5" <?= (int)$t['rating'] === 5 ? 'selected' : '' ?>>5 Stars</option>
                    <option value="4" <?= (int)$t['rating'] === 4 ? 'selected' : '' ?>>4 Stars</option>
                    <option value="3" <?= (int)$t['rating'] === 3 ? 'selected' : '' ?>>3 Stars</option>
                    <option value="2" <?= (int)$t['rating'] === 2 ? 'selected' : '' ?>>2 Stars</option>
                    <option value="1" <?= (int)$t['rating'] === 1 ? 'selected' : '' ?>>1 Star</option>
                </select>
            </div>
            <div class="form-field-group">
                <label for="testiStatus" style="font-weight:700;">Display status</label>
                <select id="testiStatus" name="is_active" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none; background:#fff;">
                    <option value="1" <?= (int)$t['is_active'] === 1 ? 'selected' : '' ?>>Visible (Active)</option>
                    <option value="0" <?= (int)$t['is_active'] === 0 ? 'selected' : '' ?>>Hidden (Disabled)</option>
                </select>
            </div>
        </div>

        <div class="form-field-group">
            <label for="testiComment" style="font-weight:700;">Comment Review *</label>
            <textarea id="testiComment" name="comment" required rows="4" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none; font-family:inherit; resize:vertical;"><?= e($t['comment']) ?></textarea>
        </div>

        <button type="submit" class="btn btn-primary" style="width:100%; border:none; border-radius:var(--radius-pill); font-weight:700; padding:12px; margin-top:12px; font-size:13px;"><i class="fas fa-check"></i> Save Changes</button>
    </form>
</div>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
</div>
