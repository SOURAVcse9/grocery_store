<?php
/**
 * ==========================================================================
 * admin/products/create.php — Product Creation Interface & Logic
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/../../public/dbconnect.php';
require_once __DIR__ . '/../middleware/auth_middleware.php';

require_admin_auth();
require_admin_permission('products.create');

$pdo = db();
$error = null;
$success = null;

// Process POST submission
if (method_is('post')) {
    if (!verify_csrf()) {
        $error = 'Invalid security request (CSRF check failed).';
    } else {
        $name = trim(input('name', ''));
        $slug = trim(input('slug', ''));
        $sku = trim(input('sku', ''));
        $barcode = trim(input('barcode', ''));
        $categoryId = (int) input('category_id', '0');
        $brandId = (int) input('brand_id', '0');
        
        $price = (float) input('price', '0.00');
        $costPrice = (float) input('cost_price', '0.00');
        $discountPrice = (float) input('discount_price', '0.00');
        
        $stock = (int) input('stock', '0');
        $minStock = (int) input('min_stock', '5');
        $weight = (float) input('weight', '0.00');
        $unit = trim(input('unit', 'pcs'));
        
        $shortDesc = trim(input('short_description', ''));
        $longDesc = trim(input('description', ''));
        
        $status = trim(input('status', 'Published'));
        $isFeatured = isset($_POST['is_featured']) ? 1 : 0;
        $isTrending = isset($_POST['is_trending']) ? 1 : 0;
        $isFlashSale = isset($_POST['is_flash_sale']) ? 1 : 0;
        
        $metaTitle = trim(input('meta_title', ''));
        $metaDesc = trim(input('meta_description', ''));

        // Valdations
        if (empty($name) || empty($slug) || $price <= 0) {
            $error = 'Product Name, URL Slug, and a valid Price are required fields.';
        } else {
            try {
                // Verify unique Slug
                $slugCheck = $pdo->prepare("SELECT id FROM products WHERE slug = :slug LIMIT 1");
                $slugCheck->execute(['slug' => $slug]);
                if ($slugCheck->fetch()) {
                    $error = 'This URL slug is already taken. Please choose another unique slug.';
                } else {
                    $thumbnailName = null;
                    
                    // Handle Main Thumbnail Upload
                    if (!empty($_FILES['thumbnail']['name'])) {
                        $file = $_FILES['thumbnail'];
                        if ($file['error'] === UPLOAD_ERR_OK) {
                            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                                if ($file['size'] <= 3 * 1024 * 1024) { // Max 3MB
                                    $uploadDir = __DIR__ . '/../../public/uploads/products';
                                    if (!is_dir($uploadDir)) {
                                        mkdir($uploadDir, 0775, true);
                                    }
                                    
                                    $thumbnailName = 'prod_' . uniqid('', true) . '.' . $ext;
                                    move_uploaded_file($file['tmp_name'], $uploadDir . '/' . $thumbnailName);
                                } else {
                                    $error = 'Thumbnail file size must be less than 3MB.';
                                }
                            } else {
                                $error = 'Only JPG, JPEG, PNG, and WebP thumbnail formats are allowed.';
                            }
                        }
                    }

                    if ($error === null) {
                        $is_active = ($status === 'Published') ? 1 : 0;

                        // Insert product row
                        $stmt = $pdo->prepare("
                            INSERT INTO products (
                                category_id, brand_id, name, slug, description, short_description, 
                                sku, barcode, price, cost_price, discount_price, stock, min_stock, 
                                weight, unit, thumbnail, is_featured, is_trending, is_flash_sale, 
                                is_active, status, meta_title, meta_description, created_at, updated_at
                            ) VALUES (
                                :category_id, :brand_id, :name, :slug, :description, :short_description, 
                                :sku, :barcode, :price, :cost_price, :discount_price, :stock, :min_stock, 
                                :weight, :unit, :thumbnail, :is_featured, :is_trending, :is_flash_sale, 
                                :is_active, :status, :meta_title, :meta_description, NOW(), NOW()
                            )
                        ");

                        $stmt->execute([
                            'category_id'       => $categoryId > 0 ? $categoryId : null,
                            'brand_id'           => $brandId > 0 ? $brandId : null,
                            'name'               => $name,
                            'slug'               => $slug,
                            'description'        => $longDesc,
                            'short_description'  => $shortDesc,
                            'sku'                => $sku,
                            'barcode'            => $barcode,
                            'price'              => $price,
                            'cost_price'         => $costPrice > 0 ? $costPrice : null,
                            'discount_price'     => $discountPrice > 0 ? $discountPrice : null,
                            'stock'              => $stock,
                            'min_stock'          => $minStock,
                            'weight'             => $weight > 0 ? $weight : null,
                            'unit'               => $unit,
                            'thumbnail'          => $thumbnailName,
                            'is_featured'        => $isFeatured,
                            'is_trending'        => $isTrending,
                            'is_flash_sale'      => $isFlashSale,
                            'is_active'          => $is_active,
                            'status'             => $status,
                            'meta_title'         => $metaTitle,
                            'meta_description'   => $metaDesc
                        ]);

                        $newProductId = (int) $pdo->lastInsertId();

                        // Write Initial Inventory Log
                        $logStmt = $pdo->prepare("
                            INSERT INTO inventory_logs (product_id, admin_id, type, quantity, remaining_stock, note)
                            VALUES (:pid, :aid, 'initial', :qty, :rem, 'Initial stock entry upon creation')
                        ");
                        $logStmt->execute([
                            'pid' => $newProductId,
                            'aid' => current_admin_id(),
                            'qty' => $stock,
                            'rem' => $stock
                        ]);

                        // Handle Multiple Gallery Images
                        if (!empty($_FILES['gallery']['name'][0])) {
                            $files = $_FILES['gallery'];
                            $uploadDir = __DIR__ . '/../../public/uploads/products';
                            
                            $insGal = $pdo->prepare("
                                INSERT INTO product_images (product_id, image_url, sort_order, created_at)
                                VALUES (:pid, :path, :sort, NOW())
                            ");

                            for ($i = 0; $i < count($files['name']); $i++) {
                                if ($files['error'][$i] === UPLOAD_ERR_OK) {
                                    $ext = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
                                    if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                                        $galName = 'gal_' . uniqid('', true) . '.' . $ext;
                                        if (move_uploaded_file($files['tmp_name'][$i], $uploadDir . '/' . $galName)) {
                                            $insGal->execute([
                                                'pid'  => $newProductId,
                                                'path' => $galName,
                                                'sort' => $i
                                            ]);
                                        }
                                    }
                                }
                            }
                        }

                        log_admin_activity('products.create', "Created new product: '{$name}' with initial stock: {$stock}");
                        flash('products_msg', "Product '{$name}' added successfully!", 'success');
                        
                        header('Location: index.php');
                        exit;
                    }
                }
            } catch (PDOException $e) {
                error_log('[admin/products/create] Fail: ' . $e->getMessage());
                $error = 'Failed to create product due to database error.';
            }
        }
    }
}

$pageTitle = 'Add Product — GroCo Admin';
require_once __DIR__ . '/../layouts/dashboard_layout.php';

// Fetch categories and brands list
try {
    $categories = $pdo->query("SELECT id, name, parent_id FROM categories ORDER BY name ASC")->fetchAll();
    $brands = $pdo->query("SELECT id, name FROM brands ORDER BY name ASC")->fetchAll();
    
    // Group categories hierarchy
    $parents = [];
    $subs = [];
    foreach ($categories as $cat) {
        if ($cat['parent_id'] === null) {
            $parents[] = $cat;
        } else {
            $subs[(int)$cat['parent_id']][] = $cat;
        }
    }
} catch (PDOException $e) {
    $parents = $brands = [];
}
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-5);">
    <div>
        <h1 style="font-size:var(--fs-xl); font-weight:800; color:var(--color-text); margin:0;">Add Product</h1>
        <p style="font-size:var(--fs-sm); color:var(--color-text-muted); margin:4px 0 0 0;">Create a new catalog item, set stock pricing, and upload product gallery folders.</p>
    </div>
    <a href="index.php" class="btn btn-secondary" style="border-radius:var(--radius-pill); font-weight:700; padding:10px 20px;"><i class="fas fa-arrow-left"></i> Product Grid</a>
</div>

<!-- Alert panel -->
<?php if ($error !== null): ?>
    <div style="background:#fff5f5; border:1px solid #ffe3e3; color:#e03131; padding:12px; border-radius:var(--radius-sm); font-size:var(--fs-sm); font-weight:600; margin-bottom:var(--space-4);">
        <i class="fas fa-circle-exclamation" style="margin-right:4px;"></i> <?= $error ?>
    </div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" class="auth-form" style="display:grid; grid-template-columns: 2fr 1fr; gap:var(--space-6); align-items:start;" class="admin-dashboard-layout">
    <?= csrf_field() ?>
    
    <!-- Main Left Form details -->
    <div style="display:flex; flex-direction:column; gap:var(--space-4);">
        
        <!-- General Info card -->
        <div class="dashboard-card" style="margin-bottom:0; padding:var(--space-6);">
            <h2 style="font-size:14px; font-weight:800; color:var(--color-text); margin-bottom:var(--space-4); border-bottom:1px solid var(--color-border); padding-bottom:8px;">Basic Details</h2>
            
            <div class="form-field-group">
                <label for="prodNameInput" style="font-weight:700;">Product Name *</label>
                <input type="text" id="prodNameInput" name="name" required placeholder="Enter product name" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;" oninput="generateProductUrlSlug();">
            </div>

            <div class="form-field-group">
                <label for="prodSlugInput" style="font-weight:700;">URL Slug *</label>
                <input type="text" id="prodSlugInput" name="slug" required placeholder="url-friendly-slug-text" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;" class="grid-2">
                <div class="form-field-group">
                    <label for="prodSku" style="font-weight:700;">SKU Code</label>
                    <input type="text" id="prodSku" name="sku" placeholder="SKU code" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
                </div>
                <div class="form-field-group">
                    <label for="prodBarcode" style="font-weight:700;">Barcode / EAN</label>
                    <input type="text" id="prodBarcode" name="barcode" placeholder="Barcode value" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
                </div>
            </div>

            <div class="form-field-group">
                <label for="prodShortDesc" style="font-weight:700;">Short Summary Description</label>
                <textarea id="prodShortDesc" name="short_description" rows="2" placeholder="Brief tagline or quick sales pitch summary" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none; font-family:inherit; resize:vertical;"></textarea>
            </div>

            <div class="form-field-group">
                <label for="prodLongDesc" style="font-weight:700;">Detailed Specifications Description</label>
                <textarea id="prodLongDesc" name="description" rows="5" placeholder="Full product specifications details" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none; font-family:inherit; resize:vertical;"></textarea>
            </div>
        </div>

        <!-- Prices & Inventory stocks -->
        <div class="dashboard-card" style="margin-bottom:0; padding:var(--space-6);">
            <h2 style="font-size:14px; font-weight:800; color:var(--color-text); margin-bottom:var(--space-4); border-bottom:1px solid var(--color-border); padding-bottom:8px;">Pricing & Inventory Stock Control</h2>
            
            <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:12px;" class="grid-3">
                <div class="form-field-group">
                    <label for="prodPrice" style="font-weight:700;">Selling Price (৳) *</label>
                    <input type="number" id="prodPrice" name="price" step="0.01" required value="0.00" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
                </div>
                <div class="form-field-group">
                    <label for="prodDiscount" style="font-weight:700;">Discount Price (৳)</label>
                    <input type="number" id="prodDiscount" name="discount_price" step="0.01" value="0.00" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
                </div>
                <div class="form-field-group">
                    <label for="prodCost" style="font-weight:700;">Cost Price (৳)</label>
                    <input type="number" id="prodCost" name="cost_price" step="0.01" value="0.00" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
                </div>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr 1fr 1fr; gap:12px;" class="grid-4">
                <div class="form-field-group">
                    <label for="prodStock" style="font-weight:700;">In Stock Quantity</label>
                    <input type="number" id="prodStock" name="stock" value="0" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
                </div>
                <div class="form-field-group">
                    <label for="prodMinStock" style="font-weight:700;">Low Stock Limit</label>
                    <input type="number" id="prodMinStock" name="min_stock" value="5" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
                </div>
                <div class="form-field-group">
                    <label for="prodWeight" style="font-weight:700;">Weight (kg/g)</label>
                    <input type="number" id="prodWeight" name="weight" step="0.01" value="0.00" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
                </div>
                <div class="form-field-group">
                    <label for="prodUnit" style="font-weight:700;">Measurement Unit</label>
                    <input type="text" id="prodUnit" name="unit" value="pcs" placeholder="kg, pcs, pack" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
                </div>
            </div>
        </div>

        <!-- Product Image files uploads -->
        <div class="dashboard-card" style="margin-bottom:0; padding:var(--space-6);">
            <h2 style="font-size:14px; font-weight:800; color:var(--color-text); margin-bottom:var(--space-4); border-bottom:1px solid var(--color-border); padding-bottom:8px;">Images Upload</h2>
            
            <div class="form-field-group">
                <label for="prodThumb" style="font-weight:700;">Main Listing Thumbnail *</label>
                <input type="file" id="prodThumb" name="thumbnail" accept="image/*" style="font-size:12px; display:block; margin-top:6px;">
                <span class="field-help-text">Thumbnail displays in search catalog listings. JPG, WebP, PNG (max 3MB).</span>
            </div>

            <div class="form-field-group">
                <label for="prodGallery" style="font-weight:700;">Gallery Images (Multiple Uploads)</label>
                <input type="file" id="prodGallery" name="gallery[]" accept="image/*" multiple style="font-size:12px; display:block; margin-top:6px;">
                <span class="field-help-text">Detailed view carousel pictures. You can hold CTRL to select multiple files.</span>
            </div>
        </div>

        <!-- SEO meta tags -->
        <div class="dashboard-card" style="margin-bottom:0; padding:var(--space-6);">
            <h2 style="font-size:14px; font-weight:800; color:var(--color-text); margin-bottom:var(--space-4); border-bottom:1px solid var(--color-border); padding-bottom:8px;">Search Engine Optimization (SEO)</h2>
            
            <div class="form-field-group">
                <label for="seoTitle" style="font-weight:700;">SEO Meta Title</label>
                <input type="text" id="seoTitle" name="meta_title" placeholder="Descriptive title for Google search results" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
            </div>

            <div class="form-field-group">
                <label for="seoDesc" style="font-weight:700;">SEO Meta Description</label>
                <textarea id="seoDesc" name="meta_description" rows="3" placeholder="Compelling summary snippet showing under the URL link" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none; font-family:inherit; resize:vertical;"></textarea>
            </div>
        </div>

    </div>

    <!-- Right Sidebar Form details -->
    <div style="display:flex; flex-direction:column; gap:var(--space-4);">
        
        <!-- Status & Flags -->
        <div class="dashboard-card" style="margin-bottom:0; padding:var(--space-5);">
            <h2 style="font-size:13px; font-weight:800; color:var(--color-text); margin-bottom:var(--space-3); border-bottom:1px solid var(--color-border); padding-bottom:6px;">Status & Flags</h2>
            
            <div class="form-field-group">
                <label for="prodStatus" style="font-weight:700;">Listing Status</label>
                <select id="prodStatus" name="status" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none; background:#fff;">
                    <option value="Published">Published</option>
                    <option value="Draft">Draft</option>
                    <option value="Hidden">Hidden</option>
                    <option value="Archived">Archived</option>
                </select>
            </div>

            <div style="display:flex; flex-direction:column; gap:10px; margin-top:16px;">
                <label style="display:flex; align-items:center; gap:8px; font-size:12px; font-weight:600; cursor:pointer;">
                    <input type="checkbox" name="is_featured" style="width:14px; height:14px; accent-color:var(--color-primary);"> Featured Product
                </label>
                <label style="display:flex; align-items:center; gap:8px; font-size:12px; font-weight:600; cursor:pointer;">
                    <input type="checkbox" name="is_trending" style="width:14px; height:14px; accent-color:var(--color-primary);"> Trending Product
                </label>
                <label style="display:flex; align-items:center; gap:8px; font-size:12px; font-weight:600; cursor:pointer;">
                    <input type="checkbox" name="is_flash_sale" style="width:14px; height:14px; accent-color:var(--color-primary);"> Flash Sale
                </label>
            </div>
        </div>

        <!-- Categorization -->
        <div class="dashboard-card" style="margin-bottom:0; padding:var(--space-5);">
            <h2 style="font-size:13px; font-weight:800; color:var(--color-text); margin-bottom:var(--space-3); border-bottom:1px solid var(--color-border); padding-bottom:6px;">Categorization</h2>
            
            <div class="form-field-group">
                <label for="prodCat" style="font-weight:700;">Parent Category *</label>
                <select id="prodCat" name="category_id" required style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none; background:#fff;">
                    <option value="">Select Category</option>
                    <?php foreach ($parents as $pCat): ?>
                        <option value="<?= $pCat['id'] ?>" style="font-weight:700;"><?= e($pCat['name']) ?></option>
                        <?php if (isset($subs[(int)$pCat['id']])): ?>
                            <?php foreach ($subs[(int)$pCat['id']] as $sCat): ?>
                                <option value="<?= $sCat['id'] ?>">&nbsp;&nbsp;&nbsp;&nbsp;— <?= e($sCat['name']) ?></option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-field-group">
                <label for="prodBrand" style="font-weight:700;">Brand / Maker</label>
                <select id="prodBrand" name="brand_id" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none; background:#fff;">
                    <option value="">Select Brand</option>
                    <?php foreach ($brands as $b): ?>
                        <option value="<?= $b['id'] ?>"><?= e($b['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- Save Button -->
        <button type="submit" class="btn btn-primary" style="width:100%; border:none; border-radius:var(--radius-pill); font-weight:700; padding:12px; font-size:13px;"><i class="fas fa-check"></i> Add Catalog Item</button>
        <a href="index.php" class="btn btn-secondary" style="width:100%; padding:12px; border-radius:var(--radius-pill); font-weight:700; text-align:center; display:block; text-decoration:none; font-size:13px;">Cancel Changes</a>

    </div>

</form>

<script>
// Auto generation of clean URL friendly slugs
function generateProductUrlSlug() {
    const nameInput = document.getElementById('prodNameInput');
    const slugInput = document.getElementById('prodSlugInput');
    if (nameInput && slugInput) {
        let slug = nameInput.value.toLowerCase();
        // replace special characters
        slug = slug.replace(/[^a-z0-9 -]/g, '') // remove invalid chars
                   .replace(/\s+/g, '-')       // replace spaces with -
                   .replace(/-+/g, '-');       // collapse multiple -
        slugInput.value = slug;
    }
}
</script>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
</div>
