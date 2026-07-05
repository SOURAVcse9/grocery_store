<?php
/**
 * ==========================================================================
 * admin/faq/create.php — Create New FAQ
 * ==========================================================================
 */

declare(strict_types=1);

$pageTitle = 'Add FAQ — GroCo Admin';
require_once __DIR__ . '/../layouts/dashboard_layout.php';
require_admin_permission('faq.manage');

$pdo = db();
$error = null;

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
                $stmt = $pdo->prepare("
                    INSERT INTO faqs (category, question, answer, sort_order, is_active)
                    VALUES (:category, :question, :answer, :sort, :active)
                ");
                $stmt->execute([
                    'category' => $category,
                    'question' => $question,
                    'answer'   => $answer,
                    'sort'     => $sortOrder,
                    'active'   => $isActive
                ]);

                log_admin_activity('faq.create', "Created FAQ accordion: '{$question}'");
                flash('faq_msg', 'FAQ question added successfully!', 'success');
                header('Location: index.php');
                exit;
            } catch (PDOException $e) {
                error_log('[admin/faq/create] Save failed: ' . $e->getMessage());
                $error = 'Failed to create FAQ record due to database error.';
            }
        }
    }
}
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-5);">
    <div>
        <h1 style="font-size:var(--fs-xl); font-weight:800; color:var(--color-text); margin:0;">Add FAQ Accordion</h1>
        <p style="font-size:var(--fs-sm); color:var(--color-text-muted); margin:4px 0 0 0;">Create a new frequently asked question and answer.</p>
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
                <option value="General">General</option>
                <option value="Ordering">Ordering</option>
                <option value="Delivery">Delivery</option>
                <option value="Payments">Payments & Refunds</option>
            </select>
        </div>

        <div class="form-field-group">
            <label for="faqQuestion" style="font-weight:700;">Question *</label>
            <input type="text" id="faqQuestion" name="question" required placeholder="E.g. What is your return policy?" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
        </div>

        <div class="form-field-group">
            <label for="faqAnswer" style="font-weight:700;">Answer *</label>
            <textarea id="faqAnswer" name="answer" required rows="4" placeholder="Detailed response answer visible to customer..." style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none; font-family:inherit; resize:vertical;"></textarea>
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;" class="grid-2">
            <div class="form-field-group">
                <label for="faqSort" style="font-weight:700;">Sort Order</label>
                <input type="number" id="faqSort" name="sort_order" min="0" value="0" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
            </div>
            <div class="form-field-group">
                <label for="faqStatus" style="font-weight:700;">Visibility Status</label>
                <select id="faqStatus" name="is_active" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none; background:#fff;">
                    <option value="1">Active (Visible)</option>
                    <option value="0">Disabled (Hidden)</option>
                </select>
            </div>
        </div>

        <button type="submit" class="btn btn-primary" style="width:100%; border:none; border-radius:var(--radius-pill); font-weight:700; padding:12px; margin-top:12px; font-size:13px;"><i class="fas fa-plus"></i> Create FAQ</button>
    </form>
</div>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
</div>
