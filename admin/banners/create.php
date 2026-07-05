<?php
/**
 * ==========================================================================
 * admin/banners/create.php — Upload & Create Storefront Banner
 * ==========================================================================
 */

declare(strict_types=1);

$pageTitle = 'Add Banner — GroCo Admin';
require_once __DIR__ . '/../layouts/dashboard_layout.php';
require_admin_permission('banners.manage');

$pdo = db();
$error = null;

if (method_is('post')) {
    if (!verify_csrf()) {
        $error = 'Invalid security request (CSRF check failed).';
    } else {
        $title = trim(input('title', ''));
        $type = trim(input('type', 'desktop'));
        $linkUrl = trim(input('link_url', ''));
        $priority = (int) input('priority', '0');
        
        $startsAt = trim(input('starts_at', ''));
        $endsAt = trim(input('ends_at', ''));
        $isActive = (int) input('is_active', '1');

        // Parse schedules
        $dbStarts = !empty($startsAt) ? $startsAt : null;
        $dbEnds = !empty($endsAt) ? $endsAt : null;

        // Image file validation
        $file = $_FILES['image'] ?? null;
        
        if (empty($title) || !$file || $file['error'] !== UPLOAD_ERR_OK) {
            $error = 'Banner Title and Image file are required fields.';
        } else {
            $allowedMimes = ['image/png', 'image/jpeg', 'image/jpg', 'image/webp'];
            $fileType = mime_content_type($file['tmp_name']);
            $fileSize = $file['size'];

            if (!in_array($fileType, $allowedMimes, true)) {
                $error = 'Invalid file type. Only PNG, JPEG, JPG, and WEBP formats are allowed.';
            } elseif ($fileSize > 3 * 1024 * 1024) {
                $error = 'File size is too large. Maximum allowed size is 3MB.';
            } else {
                try {
                    // Save file to destination directory
                    $uploadDir = __DIR__ . '/../../public/uploads/banners';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0775, true);
                    }

                    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $fileName = 'banner_' . uniqid('', true) . '.' . $ext;
                    $destPath = $uploadDir . '/' . $fileName;

                    if (move_uploaded_file($file['tmp_name'], $destPath)) {
                        $stmt = $pdo->prepare("
                            INSERT INTO banners (
                                title, type, image_path, link_url, 
                                priority, starts_at, ends_at, is_active, created_at
                            ) VALUES (
                                :title, :type, :img, :link, 
                                :priority, :starts, :ends, :active, NOW()
                            )
                        ");
                        $stmt->execute([
                            'title'    => $title,
                            'type'     => $type,
                            'img'      => $fileName,
                            'link'     => !empty($linkUrl) ? $linkUrl : null,
                            'priority' => $priority,
                            'starts'   => $dbStarts,
                            'ends'     => $dbEnds,
                            'active'   => $isActive
                        ]);

                        log_admin_activity('banners.create', "Uploaded new store banner: '{$title}'");
                        flash('banner_msg', 'Banner uploaded and scheduled successfully!', 'success');
                        header('Location: index.php');
                        exit;
                    } else {
                        $error = 'Failed to save the uploaded image file to the destination directory.';
                    }
                } catch (PDOException $e) {
                    error_log('[admin/banners/create] Database save failed: ' . $e->getMessage());
                    $error = 'Failed to create banner record due to database error.';
                }
            }
        }
    }
}
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-5);">
    <div>
        <h1 style="font-size:var(--fs-xl); font-weight:800; color:var(--color-text); margin:0;">Upload Banner Card</h1>
        <p style="font-size:var(--fs-sm); color:var(--color-text-muted); margin:4px 0 0 0;">Add a new image banner card or slideshow asset.</p>
    </div>
    <a href="index.php" class="btn btn-secondary" style="border-radius:var(--radius-pill); font-weight:700; padding:10px 20px;"><i class="fas fa-arrow-left"></i> Banners list</a>
</div>

<!-- Errors display -->
<?php if ($error !== null): ?>
    <div style="background:#fff5f5; border:1px solid #ffe3e3; color:#e03131; padding:12px; border-radius:var(--radius-sm); font-size:var(--fs-sm); font-weight:600; margin-bottom:var(--space-4);">
        <i class="fas fa-circle-exclamation" style="margin-right:4px;"></i> <?= $error ?>
    </div>
<?php endif; ?>

<div class="dashboard-card" style="padding:var(--space-6); max-width: 700px;">
    <form method="post" enctype="multipart/form-data" class="auth-form">
        <?= csrf_field() ?>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;" class="grid-2">
            <div class="form-field-group">
                <label for="bannerTitle" style="font-weight:700;">Banner Title *</label>
                <input type="text" id="bannerTitle" name="title" required placeholder="E.g. Mega Summer Discount Sale" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
            </div>
            <div class="form-field-group">
                <label for="bannerType" style="font-weight:700;">Banner Display Type *</label>
                <select id="bannerType" name="type" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none; background:#fff;">
                    <option value="desktop">Desktop Banner (Hero Slider)</option>
                    <option value="tablet">Tablet Slider</option>
                    <option value="mobile">Mobile Slider</option>
                    <option value="popup">Popup Announcement</option>
                    <option value="sidebar">Sidebar Card Banner</option>
                    <option value="category">Category Header Banner</option>
                </select>
            </div>
        </div>

        <div class="form-field-group">
            <label for="bannerImage" style="font-weight:700;">Select Banner Image File *</label>
            <input type="file" id="bannerImage" name="image" required accept="image/*" style="width:100%; padding:6px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none; background:#fff;">
            <span class="field-help-text">PNG, JPG, JPEG, or WEBP. Max file size: 3MB.</span>
        </div>

        <div style="display:grid; grid-template-columns:1.5fr 1fr; gap:12px;" class="grid-2">
            <div class="form-field-group">
                <label for="bannerLink" style="font-weight:700;">Target Redirect Link URL</label>
                <input type="text" id="bannerLink" name="link_url" placeholder="E.g. /products.php?category=organic-food" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
            </div>
            <div class="form-field-group">
                <label for="bannerPriority" style="font-weight:700;">Sorting Priority</label>
                <input type="number" id="bannerPriority" name="priority" min="0" value="0" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
            </div>
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;" class="grid-2">
            <div class="form-field-group">
                <label for="bannerStart" style="font-weight:700;">Starts At (Scheduling)</label>
                <input type="datetime-local" id="bannerStart" name="starts_at" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
            </div>
            <div class="form-field-group">
                <label for="bannerEnd" style="font-weight:700;">Ends At (Scheduling)</label>
                <input type="datetime-local" id="bannerEnd" name="ends_at" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
            </div>
        </div>

        <div class="form-field-group">
            <label for="bannerStatus" style="font-weight:700;">Active Status</label>
            <select id="bannerStatus" name="is_active" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none; background:#fff;">
                <option value="1">Enabled (Visible)</option>
                <option value="0">Disabled (Hidden)</option>
            </select>
        </div>

        <button type="submit" class="btn btn-primary" style="width:100%; border:none; border-radius:var(--radius-pill); font-weight:700; padding:12px; margin-top:12px; font-size:13px;"><i class="fas fa-plus"></i> Upload Banner</button>
    </form>
</div>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
</div>
