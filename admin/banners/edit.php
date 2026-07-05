<?php
/**
 * ==========================================================================
 * admin/banners/edit.php — Modify Existing Banner card Details
 * ==========================================================================
 */

declare(strict_types=1);

$pageTitle = 'Edit Banner — GroCo Admin';
require_once __DIR__ . '/../layouts/dashboard_layout.php';
require_admin_permission('banners.manage');

$pdo = db();
$bannerId = (int) input('id', '0', 'get');

if ($bannerId <= 0) {
    header('Location: index.php');
    exit;
}

$error = null;

try {
    $stmt = $pdo->prepare("SELECT * FROM banners WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $bannerId]);
    $b = $stmt->fetch();

    if (!$b) {
        flash('banner_msg', 'Banner details not found.', 'error');
        header('Location: index.php');
        exit;
    }
} catch (PDOException $e) {
    error_log('[admin/banners/edit] load failed: ' . $e->getMessage());
    header('Location: index.php');
    exit;
}

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

        $dbStarts = !empty($startsAt) ? $startsAt : null;
        $dbEnds = !empty($endsAt) ? $endsAt : null;

        // Check if new image is uploaded
        $file = $_FILES['image'] ?? null;
        $fileName = $b['image_path']; // keep existing
        $fileUploadSuccess = true;

        if ($file && $file['error'] === UPLOAD_ERR_OK) {
            $allowedMimes = ['image/png', 'image/jpeg', 'image/jpg', 'image/webp'];
            $fileType = mime_content_type($file['tmp_name']);
            $fileSize = $file['size'];

            if (!in_array($fileType, $allowedMimes, true)) {
                $error = 'Invalid file type. Only PNG, JPEG, JPG, and WEBP formats are allowed.';
                $fileUploadSuccess = false;
            } elseif ($fileSize > 3 * 1024 * 1024) {
                $error = 'File size is too large. Maximum allowed size is 3MB.';
                $fileUploadSuccess = false;
            } else {
                $uploadDir = __DIR__ . '/../../public/uploads/banners';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0775, true);
                }

                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $newFileName = 'banner_' . uniqid('', true) . '.' . $ext;
                $destPath = $uploadDir . '/' . $newFileName;

                if (move_uploaded_file($file['tmp_name'], $destPath)) {
                    // Safe delete old image from disk
                    $oldFilePath = $uploadDir . '/' . $b['image_path'];
                    if (file_exists($oldFilePath) && is_file($oldFilePath)) {
                        unlink($oldFilePath);
                    }
                    $fileName = $newFileName;
                } else {
                    $error = 'Failed to save the newly uploaded image file.';
                    $fileUploadSuccess = false;
                }
            }
        }

        if (empty($title)) {
            $error = 'Banner Title is a required field.';
        } elseif ($fileUploadSuccess) {
            try {
                $stmt = $pdo->prepare("
                    UPDATE banners SET
                        title = :title, type = :type, image_path = :img, 
                        link_url = :link, priority = :priority, 
                        starts_at = :starts, ends_at = :ends, is_active = :active
                    WHERE id = :id
                ");
                $stmt->execute([
                    'title'    => $title,
                    'type'     => $type,
                    'img'      => $fileName,
                    'link'     => !empty($linkUrl) ? $linkUrl : null,
                    'priority' => $priority,
                    'starts'   => $dbStarts,
                    'ends'     => $dbEnds,
                    'active'   => $isActive,
                    'id'       => $bannerId
                ]);

                log_admin_activity('banners.edit', "Updated store banner: '{$title}'");
                flash('banner_msg', 'Banner updated and saved successfully!', 'success');
                header('Location: index.php');
                exit;
            } catch (PDOException $e) {
                error_log('[admin/banners/edit] Save failed: ' . $e->getMessage());
                $error = 'Failed to update banner record due to database error.';
            }
        }
    }
}
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-5);">
    <div>
        <h1 style="font-size:var(--fs-xl); font-weight:800; color:var(--color-text); margin:0;">Edit Banner Card</h1>
        <p style="font-size:var(--fs-sm); color:var(--color-text-muted); margin:4px 0 0 0;">Update display schedules and replace banner sliders.</p>
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
                <input type="text" id="bannerTitle" name="title" required value="<?= e($b['title']) ?>" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
            </div>
            <div class="form-field-group">
                <label for="bannerType" style="font-weight:700;">Banner Display Type *</label>
                <select id="bannerType" name="type" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none; background:#fff;">
                    <option value="desktop" <?= $b['type'] === 'desktop' ? 'selected' : '' ?>>Desktop Banner (Hero Slider)</option>
                    <option value="tablet" <?= $b['type'] === 'tablet' ? 'selected' : '' ?>>Tablet Slider</option>
                    <option value="mobile" <?= $b['type'] === 'mobile' ? 'selected' : '' ?>>Mobile Slider</option>
                    <option value="popup" <?= $b['type'] === 'popup' ? 'selected' : '' ?>>Popup Announcement</option>
                    <option value="sidebar" <?= $b['type'] === 'sidebar' ? 'selected' : '' ?>>Sidebar Card Banner</option>
                    <option value="category" <?= $b['type'] === 'category' ? 'selected' : '' ?>>Category Header Banner</option>
                </select>
            </div>
        </div>

        <div class="form-field-group">
            <label for="bannerImage" style="font-weight:700;">Replace Banner Image File</label>
            <input type="file" id="bannerImage" name="image" accept="image/*" style="width:100%; padding:6px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none; background:#fff;">
            <span class="field-help-text">Leave empty if you wish to keep the current image file below.</span>
            
            <div style="margin-top: 10px;">
                <span style="font-size:10px; font-weight:700; color:var(--color-text-faint); text-transform:uppercase; display:block; margin-bottom:4px;">Current Image Preview:</span>
                <img src="<?= asset('uploads/banners/' . $b['image_path']) ?>" alt="" style="max-width: 240px; max-height: 120px; border-radius:var(--radius-sm); border:1px solid var(--color-border); object-fit:cover;">
            </div>
        </div>

        <div style="display:grid; grid-template-columns:1.5fr 1fr; gap:12px;" class="grid-2">
            <div class="form-field-group">
                <label for="bannerLink" style="font-weight:700;">Target Redirect Link URL</label>
                <input type="text" id="bannerLink" name="link_url" value="<?= e($b['link_url'] ?? '') ?>" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
            </div>
            <div class="form-field-group">
                <label for="bannerPriority" style="font-weight:700;">Sorting Priority</label>
                <input type="number" id="bannerPriority" name="priority" min="0" value="<?= (int)$b['priority'] ?>" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
            </div>
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;" class="grid-2">
            <div class="form-field-group">
                <label for="bannerStart" style="font-weight:700;">Starts At (Scheduling)</label>
                <input type="datetime-local" id="bannerStart" name="starts_at" value="<?= $b['starts_at'] ? date('Y-m-d\TH:i', strtotime($b['starts_at'])) : '' ?>" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
            </div>
            <div class="form-field-group">
                <label for="bannerEnd" style="font-weight:700;">Ends At (Scheduling)</label>
                <input type="datetime-local" id="bannerEnd" name="ends_at" value="<?= $b['ends_at'] ? date('Y-m-d\TH:i', strtotime($b['ends_at'])) : '' ?>" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
            </div>
        </div>

        <div class="form-field-group">
            <label for="bannerStatus" style="font-weight:700;">Active Status</label>
            <select id="bannerStatus" name="is_active" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none; background:#fff;">
                <option value="1" <?= (int)$b['is_active'] === 1 ? 'selected' : '' ?>>Enabled (Visible)</option>
                <option value="0" <?= (int)$b['is_active'] === 0 ? 'selected' : '' ?>>Disabled (Hidden)</option>
            </select>
        </div>

        <button type="submit" class="btn btn-primary" style="width:100%; border:none; border-radius:var(--radius-pill); font-weight:700; padding:12px; margin-top:12px; font-size:13px;"><i class="fas fa-check"></i> Save Changes</button>
    </form>
</div>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
</div>
