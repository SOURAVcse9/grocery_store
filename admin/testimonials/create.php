<?php
/**
 * ==========================================================================
 * admin/testimonials/create.php — Create Customer Testimonial Feedback
 * ==========================================================================
 */

declare(strict_types=1);

$pageTitle = 'Add Testimonial — GroCo Admin';
require_once __DIR__ . '/../layouts/dashboard_layout.php';
require_admin_permission('testimonials.manage');

$pdo = db();
$error = null;

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
                $stmt = $pdo->prepare("
                    INSERT INTO testimonials (name, designation, rating, comment, is_active)
                    VALUES (:name, :desig, :rating, :comment, :active)
                ");
                $stmt->execute([
                    'name'    => $name,
                    'desig'   => $designation,
                    'rating'  => $rating,
                    'comment' => $comment,
                    'active'  => $isActive
                ]);

                log_admin_activity('testimonials.create', "Created testimonial feedback for: '{$name}'");
                flash('testi_msg', 'Testimonial feedback added successfully!', 'success');
                header('Location: index.php');
                exit;
            } catch (PDOException $e) {
                error_log('[admin/testimonials/create] Save failed: ' . $e->getMessage());
                $error = 'Failed to create testimonial due to database error.';
            }
        }
    }
}
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-5);">
    <div>
        <h1 style="font-size:var(--fs-xl); font-weight:800; color:var(--color-text); margin:0;">Add Testimonial</h1>
        <p style="font-size:var(--fs-sm); color:var(--color-text-muted); margin:4px 0 0 0;">Add a new client rating review.</p>
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
                <input type="text" id="testiName" name="name" required placeholder="E.g. Sajeeb Rahman" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
            </div>
            <div class="form-field-group">
                <label for="testiDesig" style="font-weight:700;">Designation / Title</label>
                <input type="text" id="testiDesig" name="designation" placeholder="E.g. Premium Buyer, Chef..." style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
            </div>
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;" class="grid-2">
            <div class="form-field-group">
                <label for="testiRating" style="font-weight:700;">Rating Rating (1-5 Stars)</label>
                <select id="testiRating" name="rating" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none; background:#fff;">
                    <option value="5">5 Stars</option>
                    <option value="4">4 Stars</option>
                    <option value="3">3 Stars</option>
                    <option value="2">2 Stars</option>
                    <option value="1">1 Star</option>
                </select>
            </div>
            <div class="form-field-group">
                <label for="testiStatus" style="font-weight:700;">Display status</label>
                <select id="testiStatus" name="is_active" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none; background:#fff;">
                    <option value="1">Visible (Active)</option>
                    <option value="0">Hidden (Disabled)</option>
                </select>
            </div>
        </div>

        <div class="form-field-group">
            <label for="testiComment" style="font-weight:700;">Comment Review *</label>
            <textarea id="testiComment" name="comment" required rows="4" placeholder="Client reviews details..." style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none; font-family:inherit; resize:vertical;"></textarea>
        </div>

        <button type="submit" class="btn btn-primary" style="width:100%; border:none; border-radius:var(--radius-pill); font-weight:700; padding:12px; margin-top:12px; font-size:13px;"><i class="fas fa-plus"></i> Create Testimonial</button>
    </form>
</div>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
</div>
