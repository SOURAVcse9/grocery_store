<?php
/**
 * ==========================================================================
 * admin/categories/create.php — Create Category Controller & Interface
 * ==========================================================================
 */

declare(strict_types=1);

$pageTitle = 'Add Category — GroCo Admin';
require_once __DIR__ . '/../layouts/dashboard_layout.php';
require_admin_permission('categories.manage');

$pdo = db();
$error = null;
$success = null;

if (method_is('post')) {
    if (!verify_csrf()) {
        $error = 'Invalid security request (CSRF check failed).';
    } else {
        $name = trim(input('name', ''));
        $slug = trim(input('slug', ''));
        $parentId = input('parent_id', '');
        $icon = trim(input('icon', ''));
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if (empty($name) || empty($slug)) {
            $error = 'Category Name and URL Slug are required fields.';
        } else {
            try {
                // Check unique slug
                $chk = $pdo->prepare("SELECT id FROM categories WHERE slug = :slug LIMIT 1");
                $chk->execute(['slug' => $slug]);
                if ($chk->fetch()) {
                    $error = 'The URL slug is already taken. Please choose another unique slug.';
                } else {
                    $imageName = null;
                    
                    // Handle image upload
                    if (!empty($_FILES['image']['name'])) {
                        $file = $_FILES['image'];
                        if ($file['error'] === UPLOAD_ERR_OK) {
                            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                                if ($file['size'] <= 2 * 1024 * 1024) { // Max 2MB
                                    $uploadDir = __DIR__ . '/../../public/uploads/categories';
                                    if (!is_dir($uploadDir)) {
                                        mkdir($uploadDir, 0775, true);
                                    }
                                    
                                    $imageName = 'cat_' . uniqid('', true) . '.' . $ext;
                                    move_uploaded_file($file['tmp_name'], $uploadDir . '/' . $imageName);
                                } else {
                                    $error = 'Image file size must be less than 2MB.';
                                }
                            } else {
                                $error = 'Only JPG, JPEG, PNG, and WebP image formats are allowed.';
                            }
                        }
                    }

                    if ($error === null) {
                        $pVal = (!empty($parentId) && (int)$parentId > 0) ? (int)$parentId : null;
                        
                        $stmt = $pdo->prepare("
                            INSERT INTO categories (name, slug, parent_id, image, icon, is_active)
                            VALUES (:name, :slug, :parent, :image, :icon, :active)
                        ");
                        $stmt->execute([
                            'name'   => $name,
                            'slug'   => $slug,
                            'parent' => $pVal,
                            'image'  => $imageName,
                            'icon'   => $icon,
                            'active' => $isActive
                        ]);

                        log_admin_activity('categories.create', "Created category: '{$name}'");
                        flash('cat_msg', "Category '{$name}' created successfully!", 'success');
                        
                        header('Location: index.php');
                        exit;
                    }
                }
            } catch (PDOException $e) {
                error_log('[admin/categories/create] Save fail: ' . $e->getMessage());
                $error = 'Failed to create category due to database error.';
            }
        }
    }
}

// Fetch potential parent categories
try {
    $parentCategories = $pdo->query("SELECT id, name FROM categories WHERE parent_id IS NULL ORDER BY name ASC")->fetchAll();
} catch (PDOException $e) {
    $parentCategories = [];
}
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-5);">
    <div>
        <h1 style="font-size:var(--fs-xl); font-weight:800; color:var(--color-text); margin:0;">Add Category</h1>
        <p style="font-size:var(--fs-sm); color:var(--color-text-muted); margin:4px 0 0 0;">Create root or nested child categories for products mapping.</p>
    </div>
    <a href="index.php" class="btn btn-secondary" style="border-radius:var(--radius-pill); font-weight:700; padding:10px 20px;"><i class="fas fa-arrow-left"></i> Categories List</a>
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
            <label for="catName" style="font-weight:700;">Category Name *</label>
            <input type="text" id="catName" name="name" required placeholder="Enter category name" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;" oninput="generateCategorySlug();">
        </div>

        <div class="form-field-group">
            <label for="catSlug" style="font-weight:700;">URL Slug *</label>
            <input type="text" id="catSlug" name="slug" required placeholder="url-friendly-slug" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
        </div>

        <div class="form-field-group">
            <label for="catParent" style="font-weight:700;">Parent Category (Leave empty for root)</label>
            <select id="catParent" name="parent_id" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none; background:#fff;">
                <option value="">None (Root Category)</option>
                <?php foreach ($parentCategories as $parent): ?>
                    <option value="<?= $parent['id'] ?>"><?= e($parent['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-field-group">
            <label for="catIcon" style="font-weight:700;">Icon Class (FontAwesome)</label>
            <input type="text" id="catIcon" name="icon" placeholder="fa-solid fa-carrot" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
            <span class="field-help-text">Input standard CSS icon classes, e.g., <code>fa-solid fa-carrot</code></span>
        </div>

        <div class="form-field-group">
            <label for="catImage" style="font-weight:700;">Category Banner Image</label>
            <input type="file" id="catImage" name="image" accept="image/*" style="font-size:12px; display:block; margin-top:6px;">
            <span class="field-help-text">JPG, PNG, WebP (max 2MB).</span>
        </div>

        <div class="form-field-group" style="display:flex; align-items:center; gap:8px;">
            <input type="checkbox" id="catActive" name="is_active" value="1" checked style="width:14px; height:14px; accent-color:var(--color-primary); cursor:pointer;">
            <label for="catActive" style="font-size:12px; color:var(--color-text-muted); cursor:pointer; font-weight:600; margin:0;">Active Status (displays in storefront catalogs)</label>
        </div>

        <div style="display:flex; gap:10px; margin-top:20px;">
            <button type="submit" class="btn btn-primary" style="flex:1; border:none; border-radius:var(--radius-pill); font-weight:700; padding:10px;">Create Category</button>
            <a href="index.php" class="btn btn-secondary" style="flex:1; padding:10px; border-radius:var(--radius-pill); font-weight:700; text-align:center; display:block; text-decoration:none;">Cancel</a>
        </div>
    </form>
</div>

<script>
function generateCategorySlug() {
    const nameVal = document.getElementById('catName');
    const slugVal = document.getElementById('catSlug');
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
