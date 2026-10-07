<?php
/**
 * ==========================================================================
 * tests/verify_image_types.php
 * ==========================================================================
 * Verifies that all category and product images in the database are
 * correctly analyzed for dimensions, aspect ratios, file extensions,
 * and URL resolutions.
 * ==========================================================================
 */

declare(strict_types=1);

define('GROCO_CLI_TEST_MODE', true);
require_once __DIR__ . '/../public/dbconnect.php';

echo "\n=======================================================\n";
echo " Verifying Image Types, Formats, and Orientations\n";
echo "=======================================================\n\n";

$pdo = db();

// 1. Audit Categories
echo "--- Category Images Audit ---\n";
$catStmt = $pdo->query("SELECT id, name, slug, image FROM categories WHERE is_active = 1 ORDER BY name ASC LIMIT 15");
$categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($categories as $cat) {
    $img = $cat['image'];
    $url = image_url($img, 'categories');
    $extension = $img ? pathinfo($img, PATHINFO_EXTENSION) : 'none';
    $localFile = $img ? PUBLIC_PATH . '/uploads/categories/' . $img : null;
    $dimensions = 'N/A';
    $orientation = 'none';

    if ($localFile && file_exists($localFile)) {
        $size = @getimagesize($localFile);
        if ($size) {
            $w = $size[0];
            $h = $size[1];
            $dimensions = "{$w}x{$h}";
            if ($w === $h) {
                $orientation = 'square (1:1)';
            } elseif ($w > $h) {
                $orientation = 'landscape (' . round($w / $h, 2) . ':1)';
            } else {
                $orientation = 'portrait (1:' . round($h / $w, 2) . ')';
            }
        }
    }

    echo sprintf("  %-25s | ext: %-5s | dim: %-10s | %-18s\n", 
        substr($cat['name'], 0, 25), 
        $extension, 
        $dimensions, 
        $orientation
    );
}

// 2. Audit Products
echo "\n--- Product Images Audit ---\n";
$prodStmt = $pdo->query("SELECT id, name, slug, thumbnail FROM products WHERE is_active = 1 AND deleted_at IS NULL ORDER BY id ASC LIMIT 15");
$products = $prodStmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($products as $prod) {
    $img = $prod['thumbnail'];
    $url = image_url($img, 'products');
    $extension = $img ? pathinfo($img, PATHINFO_EXTENSION) : 'none';
    $localFile = $img ? PUBLIC_PATH . '/uploads/products/' . $img : null;
    $dimensions = 'N/A';
    $orientation = 'none';

    if ($localFile && file_exists($localFile)) {
        $size = @getimagesize($localFile);
        if ($size) {
            $w = $size[0];
            $h = $size[1];
            $dimensions = "{$w}x{$h}";
            if ($w === $h) {
                $orientation = 'square (1:1)';
            } elseif ($w > $h) {
                $orientation = 'landscape (' . round($w / $h, 2) . ':1)';
            } else {
                $orientation = 'portrait (1:' . round($h / $w, 2) . ')';
            }
        }
    }

    echo sprintf("  %-25s | ext: %-5s | dim: %-10s | %-18s\n", 
        substr($prod['name'], 0, 25), 
        $extension, 
        $dimensions, 
        $orientation
    );
}

echo "\nVerification of image types completed.\n";
