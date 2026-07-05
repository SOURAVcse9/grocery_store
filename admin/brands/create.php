<?php
/**
 * ==========================================================================
 * admin/brands/create.php — Create Brand Controller & Interface
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/../../public/dbconnect.php';
require_once __DIR__ . '/../middleware/auth_middleware.php';

require_admin_auth();
require_admin_permission('brands.manage');

$pdo = db();
$error = null;
$success = null;

if (method_is('post')) {
    if (!verify_csrf()) {
        $error = 'Invalid security request (CSRF check failed).';
    } else {
        $name = trim(input('name', ''));
        $slug = trim(input('slug', ''));
        $desc = trim(input('description', ''));
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if (empty($name) || empty($slug)) {
            $error = 'Brand Name and URL Slug are required fields.';
        } else {
            try {
                // Check unique slug
                $chk = $pdo->prepare("SELECT id FROM brands WHERE slug = :slug LIMIT 1");
                $chk->execute(['slug' => $slug]);
                if ($chk->fetch()) {
                    $error = 'The URL slug is already taken. Please choose another unique slug.';
                } else {
                    $logoName = null;
                    
                    // Handle logo upload
                    if (!empty($_FILES['logo']['name'])) {
                        $file = $_FILES['logo'];
                        if ($file['error'] === UPLOAD_ERR_OK) {
                            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                                if ($file['size'] <= 2 * 1024 * 1024) { // Max 2MB
                                    $uploadDir = __DIR__ . '/../../public/uploads/brands';
                                    if (!is_dir($uploadDir)) {
                                        mkdir($uploadDir, 0775, true);
                                    }
                                    
                                    $logoName = 'brand_' . uniqid('', true) . '.' . $ext;
                                    move_uploaded_file($file['tmp_name'], $uploadDir . '/' . $logoName);
                                } else {
                                    $error = 'Logo file size must be less than 2MB.';
                                }
                            } else {
                                $error = 'Only JPG, JPEG, PNG, and WebP logo formats are allowed.';
                            }
                        }
                    }

                    if ($error === null) {
                        $stmt = $pdo->prepare("
                            INSERT INTO brands (name, slug, description, logo, is_active)
                            VALUES (:name, :slug, :desc, :logo, :active)
                        ");
                        $stmt->execute([
                            'name'   => $name,
                            'slug'   => $slug,
                            'desc'   => $desc,
                            'logo'   => $logoName,
                            'active' => $isActive
                        ]);

                        log_admin_activity('brands.create', "Created brand: '{$name}'");
                        flash('brand_msg', "Brand '{$name}' created successfully!", 'success');
                        
                        header('Location: index.php');
                        exit;
                    }
                }
            } catch (PDOException $e) {
                error_log('[admin/brands/create] Save fail: ' . $e->getMessage());
                $error = 'Failed to create brand due to database error.';
            }
        }
    }
}

$pageTitle = 'Add Brand — GroCo Admin';
require_once __DIR__ . '/../layouts/dashboard_layout.php';
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-5);">
    <div>
        <h1 style="font-size:var(--fs-xl); font-weight:800; color:var(--color-text); margin:0;">Add Brand</h1>
        <p style="font-size:var(--fs-sm); color:var(--color-text-muted); margin:4px 0 0 0;">Register store product makers, upload brand logo images, and descriptions.</p>
    </div>
    <a href="index.php" class="btn btn-secondary" style="border-radius:var(--radius-pill); font-weight:700; padding:10px 20px;"><i class="fas fa-arrow-left"></i> Brands List</a>
</div>

<!-- Alert messages -->
<?php if ($error !== null): ?>
    <div style="background:#fff5f5; border:1px solid #ffe3e3; color:#e03131; padding:12px; border-radius:var(--radius-sm); font-size:var(--fs-sm); font-weight:600; margin-bottom:var(--space-4);">
        <i class="fas fa-circle-exclamation" style="margin-right:4px;"></i> <?= $error ?>
    </div>
<?php endif; ?>

<div class="dashboard-card" style="padding:var(--space-6); max-width: 600px; margin: 0 auto;">
    <form method="post" enctype="multipart/form-data" class="auth-form">
        <?= csrf_field() ?>

        <div class="form-field-group">
            <label for="brandName" style="font-weight:700;">Brand Name *</label>
            <input type="text" id="brandName" name="name" required placeholder="Enter brand name" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;" oninput="generateBrandSlug();">
        </div>

        <div class="form-field-group">
            <label for="brandSlug" style="font-weight:700;">URL Slug *</label>
            <input type="text" id="brandSlug" name="slug" required placeholder="url-friendly-slug" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
        </div>

        <div class="form-field-group">
            <label for="brandDesc" style="font-weight:700;">Brand Description</label>
            <textarea id="brandDesc" name="description" rows="3" placeholder="Enter short story or tagline details of the brand" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none; font-family:inherit; resize:vertical;"></textarea>
        </div>

        <div class="form-field-group">
            <label for="brandLogo" style="font-weight:700;">Brand Logo Image</label>
            <input type="file" id="brandLogo" name="logo" accept="image/*" style="font-size:12px; display:block; margin-top:6px;">
            <span class="field-help-text">JPG, PNG, WebP (max 2MB).</span>
        </div>

        <div class="form-field-group" style="display:flex; align-items:center; gap:8px;">
            <input type="checkbox" id="brandActive" name="is_active" value="1" checked style="width:14px; height:14px; accent-color:var(--color-primary); cursor:pointer;">
            <label for="brandActive" style="font-size:12px; color:var(--color-text-muted); cursor:pointer; font-weight:600; margin:0;">Active Status (displays in storefront brands filter)</label>
        </div>

        <div style="display:flex; gap:10px; margin-top:20px;">
            <button type="submit" class="btn btn-primary" style="flex:1; border:none; border-radius:var(--radius-pill); font-weight:700; padding:10px;">Create Brand</button>
            <a href="index.php" class="btn btn-secondary" style="flex:1; padding:10px; border-radius:var(--radius-pill); font-weight:700; text-align:center; display:block; text-decoration:none;">Cancel</a>
        </div>
    </form>
</div>

<script>
function generateBrandSlug() {
    const nameVal = document.getElementById('brandName');
    const slugVal = document.getElementById('brandSlug');
    if (nameVal && slugVal) {
        let slug = nameVal.value.toLowerCase();
        slug = slug.replace(/[^a-z0-9 -]/g, '')
                   .replace(/\s+/g, '-')
                   .replace(/-+/g, '-');
        slugVal.value = slug;
    }
}
</script>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
</div>
