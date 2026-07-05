<?php
/**
 * ==========================================================================
 * admin/faq/edit.php — Modify Existing FAQ Accordion
 * ==========================================================================
 */

declare(strict_types=1);

$pageTitle = 'Edit FAQ — GroCo Admin';
require_once __DIR__ . '/../layouts/dashboard_layout.php';
require_admin_permission('faq.manage');

$pdo = db();
$faqId = (int) input('id', '0', 'get');

if ($faqId <= 0) {
    header('Location: index.php');
    exit;
}

$error = null;

try {
    $stmt = $pdo->prepare("SELECT * FROM faqs WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $faqId]);
    $faq = $stmt->fetch();

    if (!$faq) {
        flash('faq_msg', 'FAQ question details not found.', 'error');
        header('Location: index.php');
        exit;
    }
} catch (PDOException $e) {
    error_log('[admin/faq/edit] load failed: ' . $e->getMessage());
    header('Location: index.php');
    exit;
}

if (method_is('post')) {
    if (!verify_csrf()) {
        $error = 'Invalid security request (CSRF check failed).';
    } else {
        $category = trim(input('category', ''));
        $question = trim(input('question', ''));
        $answer = trim(input('answer', ''));
        $sortOrder = (int) input('sort_order', '0');
        $isActive = (int) input('is_active', '1');

        if (empty($category) || empty($question) || empty($answer)) {
            $error = 'Category, Question, and Answer are required fields.';
        } else {
            try {
                $up = $pdo->prepare("
                    UPDATE faqs 
                    SET category = :category, question = :question, answer = :answer, 
                        sort_order = :sort, is_active = :active
                    WHERE id = :id
                ");
                $up->execute([
                    'category' => $category,
                    'question' => $question,
                    'answer'   => $answer,
                    'sort'     => $sortOrder,
                    'active'   => $isActive,
                    'id'       => $faqId
                ]);

                log_admin_activity('faq.edit', "Updated FAQ accordion: '{$question}'");
                flash('faq_msg', 'FAQ updated successfully!', 'success');
                header('Location: index.php');
                exit;
            } catch (PDOException $e) {
                error_log('[admin/faq/edit] Save failed: ' . $e->getMessage());
                $error = 'Failed to update FAQ record due to database error.';
            }
        }
    }
}
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-5);">
    <div>
        <h1 style="font-size:var(--fs-xl); font-weight:800; color:var(--color-text); margin:0;">Edit FAQ Accordion</h1>
        <p style="font-size:var(--fs-sm); color:var(--color-text-muted); margin:4px 0 0 0;">Update accordion question details and sort indexing.</p>
    </div>
    <a href="index.php" class="btn btn-secondary" style="border-radius:var(--radius-pill); font-weight:700; padding:10px 20px;"><i class="fas fa-arrow-left"></i> FAQs List</a>
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

        <div class="form-field-group">
            <label for="faqCategory" style="font-weight:700;">FAQ Category *</label>
            <select id="faqCategory" name="category" required style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none; background:#fff;">
                <option value="">Select Category</option>
                <option value="General" <?= $faq['category'] === 'General' ? 'selected' : '' ?>>General</option>
                <option value="Ordering" <?= $faq['category'] === 'Ordering' ? 'selected' : '' ?>>Ordering</option>
                <option value="Delivery" <?= $faq['category'] === 'Delivery' ? 'selected' : '' ?>>Delivery</option>
                <option value="Payments" <?= $faq['category'] === 'Payments' ? 'selected' : '' ?>>Payments & Refunds</option>
            </select>
        </div>

        <div class="form-field-group">
            <label for="faqQuestion" style="font-weight:700;">Question *</label>
            <input type="text" id="faqQuestion" name="question" required value="<?= e($faq['question']) ?>" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
        </div>

        <div class="form-field-group">
            <label for="faqAnswer" style="font-weight:700;">Answer *</label>
            <textarea id="faqAnswer" name="answer" required rows="4" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none; font-family:inherit; resize:vertical;"><?= e($faq['answer']) ?></textarea>
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;" class="grid-2">
            <div class="form-field-group">
                <label for="faqSort" style="font-weight:700;">Sort Order</label>
                <input type="number" id="faqSort" name="sort_order" min="0" value="<?= (int)$faq['sort_order'] ?>" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
            </div>
            <div class="form-field-group">
                <label for="faqStatus" style="font-weight:700;">Visibility Status</label>
                <select id="faqStatus" name="is_active" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none; background:#fff;">
                    <option value="1" <?= (int)$faq['is_active'] === 1 ? 'selected' : '' ?>>Active (Visible)</option>
                    <option value="0" <?= (int)$faq['is_active'] === 0 ? 'selected' : '' ?>>Disabled (Hidden)</option>
                </select>
            </div>
        </div>

        <button type="submit" class="btn btn-primary" style="width:100%; border:none; border-radius:var(--radius-pill); font-weight:700; padding:12px; margin-top:12px; font-size:13px;"><i class="fas fa-check"></i> Save Changes</button>
    </form>
</div>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
</div>
