<?php
/**
 * ==========================================================================
 * admin/products/delete.php — Soft & Hard Product Deletions Controller
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/../../public/dbconnect.php';
require_once __DIR__ . '/../middleware/auth_middleware.php';

require_admin_auth();
require_admin_permission('products.delete');

$pdo = db();

$action = input('action', '', 'get');
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
        // Fetch product info first
        $stmt = $pdo->prepare('SELECT name, thumbnail FROM products WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $productId]);
        $product = $stmt->fetch();

        if ($product) {
            if ($action === 'soft') {
                $up = $pdo->prepare('UPDATE products SET deleted_at = NOW() WHERE id = :id');
                $up->execute(['id' => $productId]);
                log_admin_activity('products.delete_soft', "Soft-deleted product: '{$product['name']}'");
                flash('products_msg', "Product '{$product['name']}' moved to trash bin.", 'success');
            } elseif ($action === 'restore') {
                $up = $pdo->prepare('UPDATE products SET deleted_at = NULL WHERE id = :id');
                $up->execute(['id' => $productId]);
                log_admin_activity('products.restore', "Restored product: '{$product['name']}'");
                flash('products_msg', "Product '{$product['name']}' restored successfully.", 'success');
            } elseif ($action === 'permanent') {
                // 1. Delete main thumbnail from disk
                if (!empty($product['thumbnail'])) {
                    $thumbPath = __DIR__ . '/../../public/uploads/products/' . $product['thumbnail'];
                    if (file_exists($thumbPath)) {
                        @unlink($thumbPath);
                    }
                }

                // 2. Fetch gallery images and delete
                $galStmt = $pdo->prepare('SELECT image_path FROM product_images WHERE product_id = :pid');
                $galStmt->execute(['pid' => $productId]);
                $galImages = $galStmt->fetchAll();
                
                foreach ($galImages as $img) {
                    $imgPath = __DIR__ . '/../../public/uploads/products/' . $img['image_path'];
                    if (file_exists($imgPath)) {
                        @unlink($imgPath);
                    }
                }

                // 3. Delete from DB (foreign keys handle cascading product_images if RESTRICT is not set, but let's clear it explicitly)
                $pdo->prepare('DELETE FROM product_images WHERE product_id = :pid')->execute(['pid' => $productId]);
                $pdo->prepare('DELETE FROM products WHERE id = :id')->execute(['id' => $productId]);

                log_admin_activity('products.delete_permanent', "Permanently destroyed product: '{$product['name']}'");
                flash('products_msg', "Product '{$product['name']}' permanently deleted from system.", 'success');
            }
        }
    } catch (PDOException $e) {
        error_log('[admin/products/delete] Fail: ' . $e->getMessage());
        flash('products_msg', 'Failed to perform delete action due to database error.', 'error');
    }
}

$redirectTo = ($action === 'permanent' || $action === 'restore') ? 'index.php?view=trash' : 'index.php';
header('Location: ' . $redirectTo);
exit;
