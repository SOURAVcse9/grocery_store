<?php
/**
 * ==========================================================================
 * admin/products/duplicate.php — Product Cloning Controller
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/../../public/dbconnect.php';
require_once __DIR__ . '/../middleware/auth_middleware.php';

require_admin_auth();
require_admin_permission('products.create');

$pdo = db();

$productId = (int) input('id', '0', 'get');
$token = input('csrf_token', '', 'get');

// Validate CSRF token in GET parameter
if (empty($token) || !hash_equals(csrf_token(), $token)) {
    flash('products_msg', 'Security key verification failed. Action aborted.', 'error');
    header('Location: index.php');
    exit;
}

if ($productId > 0) {
    try {
        // Fetch target product record
        $stmt = $pdo->prepare('SELECT * FROM products WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $productId]);
        $prod = $stmt->fetch();

        if ($prod) {
            $newName = $prod['name'] . ' (Copy)';
            $newSlug = $prod['slug'] . '-copy-' . rand(100, 999);
            $newSku = !empty($prod['sku']) ? $prod['sku'] . '-COPY-' . rand(10, 99) : 'SKU-' . rand(1000, 9999);

            // Insert new product record as Draft
            $ins = $pdo->prepare("
                INSERT INTO products (
                    category_id, brand_id, name, slug, description, short_description, 
                    sku, barcode, price, cost_price, discount_price, stock, min_stock, 
                    weight, unit, thumbnail, is_featured, is_trending, is_flash_sale, 
                    is_active, status, meta_title, meta_description, created_at, updated_at
                ) VALUES (
                    :category_id, :brand_id, :name, :slug, :description, :short_description, 
                    :sku, :barcode, :price, :cost_price, :discount_price, :stock, :min_stock, 
                    :weight, :unit, :thumbnail, :is_featured, :is_trending, :is_flash_sale, 
                    0, 'Draft', :meta_title, :meta_description, NOW(), NOW()
                )
            ");
            
            $ins->execute([
                'category_id'       => $prod['category_id'],
                'brand_id'           => $prod['brand_id'],
                'name'               => $newName,
                'slug'               => $newSlug,
                'description'        => $prod['description'],
                'short_description'  => $prod['short_description'],
                'sku'                => $newSku,
                'barcode'            => $prod['barcode'],
                'price'              => $prod['price'],
                'cost_price'         => $prod['cost_price'],
                'discount_price'     => $prod['discount_price'],
                'stock'              => $prod['stock'],
                'min_stock'          => $prod['min_stock'],
                'weight'             => $prod['weight'],
                'unit'               => $prod['unit'],
                'thumbnail'          => $prod['thumbnail'],
                'is_featured'        => $prod['is_featured'],
                'is_trending'        => $prod['is_trending'],
                'is_flash_sale'      => $prod['is_flash_sale'],
                'meta_title'         => $prod['meta_title'],
                'meta_description'   => $prod['meta_description']
            ]);

            $newProductId = (int) $pdo->lastInsertId();

            // Duplicate associated gallery images
            $galStmt = $pdo->prepare('SELECT image_url AS image_path, sort_order FROM product_images WHERE product_id = :pid');
            $galStmt->execute(['pid' => $productId]);
            $galImages = $galStmt->fetchAll();

            if (!empty($galImages)) {
                $insImg = $pdo->prepare("
                    INSERT INTO product_images (product_id, image_url, sort_order, created_at)
                    VALUES (:pid, :path, :sort, NOW())
                ");
                foreach ($galImages as $img) {
                    $insImg->execute([
                        'pid'  => $newProductId,
                        'path' => $img['image_path'],
                        'sort' => $img['sort_order']
                    ]);
                }
            }

            log_admin_activity('products.duplicate', "Cloned product #{$productId} into Draft copy: '{$newName}'");
            flash('products_msg', "Product copy created as draft: '{$newName}'", 'success');
        }
    } catch (PDOException $e) {
        error_log('[admin/products/duplicate] Fail: ' . $e->getMessage());
        flash('products_msg', 'Failed to duplicate product due to database error.', 'error');
    }
}

header('Location: index.php');
exit;
