<?php
/**
 * ==============================================================================
 * tools/migrate_images_to_cloudinary.php — Resumable Image Migration Pipeline
 * ==============================================================================
 * Scans MySQL catalog records (products, product_images, categories, brands)
 * identifying unmigrated local images. Extracts native dimensions and uploads
 * images to Cloudinary CDN, updating database metadata atomically.
 *
 * Supported CLI flags:
 *   --dry-run       Simulate migration without modifying files or database
 *   --limit=N       Process maximum N assets per execution (default: all)
 *   --type=TYPE     Scope to 'products', 'gallery', 'categories', 'brands', 'all'
 *   --force         Force re-upload of already migrated records
 *
 * Usage:
 *   php tools/migrate_images_to_cloudinary.php
 *   php tools/migrate_images_to_cloudinary.php --dry-run
 *   php tools/migrate_images_to_cloudinary.php --limit=50 --type=products
 * ==============================================================================
 */

declare(strict_types=1);

// CLI Guard and Test Mode flag
if (php_sapi_name() !== 'cli') {
    die("This tool can only be executed from the command line interface.\n");
}

define('GROCO_CLI_TEST_MODE', true);

require_once __DIR__ . '/../public/dbconnect.php';
require_once __DIR__ . '/../public/includes/cloudinary.php';
require_once __DIR__ . '/../public/includes/image.php';

// Parse CLI options
$options = getopt('', ['dry-run', 'limit:', 'type:', 'force']);
$dryRun  = isset($options['dry-run']);
$limit   = isset($options['limit']) ? max(1, (int)$options['limit']) : 0;
$scope   = isset($options['type']) ? strtolower((string)$options['type']) : 'all';
$force   = isset($options['force']);

echo "\n" . str_repeat('=', 70) . "\n";
echo " GroCo Grocery Store — Cloudinary Image Migration Pipeline\n";
echo str_repeat('=', 70) . "\n";

$isConfigured = CloudinaryService::isConfigured();
if (!$isConfigured) {
    echo " [!] NOTICE: Cloudinary API credentials not detected in .env.\n";
    echo "     Migration tool will run in LOCAL METADATA ENRICHMENT mode:\n";
    echo "     Extracting & populating width, height, and SEO alt attributes.\n";
    echo "     Configure CLOUDINARY_CLOUD_NAME, CLOUDINARY_API_KEY, and\n";
    echo "     CLOUDINARY_API_SECRET in your .env file to enable Cloudinary CDN.\n";
} else {
    echo " [✓] Cloudinary CDN configuration verified.\n";
}

if ($dryRun) {
    echo " [*] DRY-RUN MODE ACTIVE: No database changes will be written.\n";
}
if ($limit > 0) {
    echo " [*] Execution batch limit: {$limit} records.\n";
}
echo " [*] Target scope: {$scope}\n\n";

$pdo = db();
$processed = 0;
$successful = 0;
$skipped = 0;
$failed = 0;

$baseUploads = defined('PUBLIC_PATH') ? PUBLIC_PATH . '/uploads' : dirname(__DIR__) . '/public/uploads';

/**
 * Helper to resolve local disk path from relative filename
 */
function resolve_disk_path(string $baseUploads, string $subfolder, string $filename): ?string
{
    $clean = basename($filename);
    if (empty($clean)) return null;

    $candidates = [
        $baseUploads . '/' . $subfolder . '/' . $clean,
        $baseUploads . '/' . $clean,
        defined('PUBLIC_PATH') ? PUBLIC_PATH . '/assets/images/products/' . $clean : null
    ];

    foreach ($candidates as $cand) {
        if ($cand && file_exists($cand) && is_file($cand)) {
            return $cand;
        }
    }
    return null;
}

// --------------------------------------------------------------------------
// 1. Migrate Main Products Thumbnails
// --------------------------------------------------------------------------
if (in_array($scope, ['all', 'products'], true) && ($limit === 0 || $processed < $limit)) {
    echo "--- 1. Migrating Product Thumbnails ---\n";
    $where = $force ? "thumbnail IS NOT NULL AND thumbnail != ''" : "(image_public_id IS NULL OR image_public_id = '') AND thumbnail IS NOT NULL AND thumbnail != ''";
    $limitClause = ($limit > 0) ? "LIMIT " . ($limit - $processed) : "";
    
    $stmt = $pdo->query("SELECT id, name, slug, thumbnail, image_alt, image_public_id FROM products WHERE {$where} ORDER BY id ASC {$limitClause}");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Found " . count($rows) . " candidate product(s).\n";

    $updateStmt = $pdo->prepare("
        UPDATE products 
        SET image_public_id = :pub_id,
            image_alt       = :alt,
            image_width     = :w,
            image_height    = :h
        WHERE id = :id
    ");

    foreach ($rows as $row) {
        $processed++;
        $id   = (int)$row['id'];
        $name = (string)$row['name'];
        $slug = (string)($row['slug'] ?: 'product-' . $id);
        $file = (string)$row['thumbnail'];

        $localPath = resolve_disk_path($baseUploads, 'products', $file);

        if (!$localPath) {
            echo " [SKIP] Product #{$id} ({$name}): Local file '{$file}' not found on disk.\n";
            $skipped++;
            continue;
        }

        $info = @getimagesize($localPath);
        $width  = $info ? (int)$info[0] : 800;
        $height = $info ? (int)$info[1] : 800;
        $alt    = !empty($row['image_alt']) ? $row['image_alt'] : generate_image_alt($name);

        $publicId = null;

        if ($isConfigured) {
            try {
                if (!$dryRun) {
                    $mockFile = [
                        'name'     => basename($localPath),
                        'tmp_name' => $localPath,
                        'size'     => filesize($localPath),
                        'error'    => UPLOAD_ERR_OK
                    ];
                    $uploadRes = CloudinaryService::upload($mockFile, 'products', $slug);
                    $publicId  = $uploadRes['public_id'] ?? null;
                    $width     = $uploadRes['width'] ?? $width;
                    $height    = $uploadRes['height'] ?? $height;
                } else {
                    $publicId = 'groco/products/' . $slug . '-simulated';
                }
                echo " [OK] Product #{$id}: Uploaded to Cloudinary ({$publicId})\n";
            } catch (Exception $e) {
                echo " [ERR] Product #{$id}: Cloudinary upload error: " . $e->getMessage() . "\n";
                $failed++;
                continue;
            }
        } else {
            echo " [OK] Product #{$id}: Indexed local dimensions ({$width}x{$height})\n";
        }

        if (!$dryRun) {
            $updateStmt->execute([
                'pub_id' => $publicId,
                'alt'    => $alt,
                'w'      => $width,
                'h'      => $height,
                'id'     => $id
            ]);
        }
        $successful++;
    }
}

// --------------------------------------------------------------------------
// 2. Migrate Product Gallery Images
// --------------------------------------------------------------------------
if (in_array($scope, ['all', 'gallery'], true) && ($limit === 0 || $processed < $limit)) {
    echo "\n--- 2. Migrating Product Gallery Images ---\n";
    $where = $force ? "pi.image_url IS NOT NULL AND pi.image_url != ''" : "(pi.image_public_id IS NULL OR pi.image_public_id = '') AND pi.image_url IS NOT NULL AND pi.image_url != ''";
    $limitClause = ($limit > 0) ? "LIMIT " . ($limit - $processed) : "";
    
    $stmt = $pdo->query("
        SELECT pi.id, pi.product_id, pi.image_url, p.slug, p.name 
        FROM product_images pi
        LEFT JOIN products p ON p.id = pi.product_id
        WHERE {$where} 
        ORDER BY pi.id ASC {$limitClause}
    ");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Found " . count($rows) . " candidate gallery image(s).\n";

    $updateGal = $pdo->prepare("
        UPDATE product_images 
        SET image_public_id = :pub_id,
            image_width     = :w,
            image_height    = :h,
            image_alt       = :alt
        WHERE id = :id
    ");

    foreach ($rows as $row) {
        $processed++;
        $id   = (int)$row['id'];
        $pId  = (int)($row['product_id'] ?? 0);
        $name = (string)($row['name'] ?? 'Product');
        $slug = (string)($row['slug'] ?? 'product-' . $pId);
        $file = (string)$row['image_url'];

        $localPath = resolve_disk_path($baseUploads, 'products', $file);

        if (!$localPath) {
            echo " [SKIP] Gallery #{$id}: Local file '{$file}' not found.\n";
            $skipped++;
            continue;
        }

        $info   = @getimagesize($localPath);
        $width  = $info ? (int)$info[0] : 800;
        $height = $info ? (int)$info[1] : 800;
        $alt    = generate_image_alt($name);

        $publicId = null;

        if ($isConfigured) {
            try {
                if (!$dryRun) {
                    $mockFile = [
                        'name'     => basename($localPath),
                        'tmp_name' => $localPath,
                        'size'     => filesize($localPath),
                        'error'    => UPLOAD_ERR_OK
                    ];
                    $uploadRes = CloudinaryService::upload($mockFile, 'products', $slug . '-gal-' . $id);
                    $publicId  = $uploadRes['public_id'] ?? null;
                    $width     = $uploadRes['width'] ?? $width;
                    $height    = $uploadRes['height'] ?? $height;
                } else {
                    $publicId = 'groco/products/' . $slug . '-gal-' . $id . '-simulated';
                }
                echo " [OK] Gallery #{$id}: Uploaded ({$publicId})\n";
            } catch (Exception $e) {
                echo " [ERR] Gallery #{$id}: Upload error: " . $e->getMessage() . "\n";
                $failed++;
                continue;
            }
        } else {
            echo " [OK] Gallery #{$id}: Dimensions indexed ({$width}x{$height})\n";
        }

        if (!$dryRun) {
            $updateGal->execute([
                'pub_id' => $publicId,
                'w'      => $width,
                'h'      => $height,
                'alt'    => $alt,
                'id'     => $id
            ]);
        }
        $successful++;
    }
}

// --------------------------------------------------------------------------
// 3. Migrate Categories Images
// --------------------------------------------------------------------------
if (in_array($scope, ['all', 'categories'], true) && ($limit === 0 || $processed < $limit)) {
    echo "\n--- 3. Migrating Category Images ---\n";
    $where = $force ? "image IS NOT NULL AND image != ''" : "(image_public_id IS NULL OR image_public_id = '') AND image IS NOT NULL AND image != ''";
    $limitClause = ($limit > 0) ? "LIMIT " . ($limit - $processed) : "";
    
    $stmt = $pdo->query("SELECT id, name, slug, image FROM categories WHERE {$where} ORDER BY id ASC {$limitClause}");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Found " . count($rows) . " candidate category image(s).\n";

    $updateCat = $pdo->prepare("
        UPDATE categories 
        SET image_public_id = :pub_id,
            image_alt       = :alt
        WHERE id = :id
    ");

    foreach ($rows as $row) {
        $processed++;
        $id   = (int)$row['id'];
        $name = (string)$row['name'];
        $slug = (string)($row['slug'] ?: 'category-' . $id);
        $file = (string)$row['image'];

        $localPath = resolve_disk_path($baseUploads, 'categories', $file);

        if (!$localPath) {
            echo " [SKIP] Category #{$id} ({$name}): Local file '{$file}' not found.\n";
            $skipped++;
            continue;
        }

        $alt = $name . ' Grocery Category';
        $publicId = null;

        if ($isConfigured) {
            try {
                if (!$dryRun) {
                    $mockFile = [
                        'name'     => basename($localPath),
                        'tmp_name' => $localPath,
                        'size'     => filesize($localPath),
                        'error'    => UPLOAD_ERR_OK
                    ];
                    $uploadRes = CloudinaryService::upload($mockFile, 'categories', $slug);
                    $publicId  = $uploadRes['public_id'] ?? null;
                } else {
                    $publicId = 'groco/categories/' . $slug . '-simulated';
                }
                echo " [OK] Category #{$id}: Uploaded ({$publicId})\n";
            } catch (Exception $e) {
                echo " [ERR] Category #{$id}: Upload error: " . $e->getMessage() . "\n";
                $failed++;
                continue;
            }
        } else {
            echo " [OK] Category #{$id}: Metadata indexed\n";
        }

        if (!$dryRun) {
            $updateCat->execute([
                'pub_id' => $publicId,
                'alt'    => $alt,
                'id'     => $id
            ]);
        }
        $successful++;
    }
}

// --------------------------------------------------------------------------
// 4. Migrate Brands Logos
// --------------------------------------------------------------------------
if (in_array($scope, ['all', 'brands'], true) && ($limit === 0 || $processed < $limit)) {
    echo "\n--- 4. Migrating Brand Logos ---\n";
    $where = $force ? "logo IS NOT NULL AND logo != ''" : "(image_public_id IS NULL OR image_public_id = '') AND logo IS NOT NULL AND logo != ''";
    $limitClause = ($limit > 0) ? "LIMIT " . ($limit - $processed) : "";
    
    $stmt = $pdo->query("SELECT id, name, slug, logo FROM brands WHERE {$where} ORDER BY id ASC {$limitClause}");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Found " . count($rows) . " candidate brand logo(s).\n";

    $updateBrand = $pdo->prepare("
        UPDATE brands 
        SET image_public_id = :pub_id,
            image_alt       = :alt
        WHERE id = :id
    ");

    foreach ($rows as $row) {
        $processed++;
        $id   = (int)$row['id'];
        $name = (string)$row['name'];
        $slug = (string)($row['slug'] ?: 'brand-' . $id);
        $file = (string)$row['logo'];

        $localPath = resolve_disk_path($baseUploads, 'brands', $file);

        if (!$localPath) {
            echo " [SKIP] Brand #{$id} ({$name}): Local file '{$file}' not found.\n";
            $skipped++;
            continue;
        }

        $alt = $name . ' Brand';
        $publicId = null;

        if ($isConfigured) {
            try {
                if (!$dryRun) {
                    $mockFile = [
                        'name'     => basename($localPath),
                        'tmp_name' => $localPath,
                        'size'     => filesize($localPath),
                        'error'    => UPLOAD_ERR_OK
                    ];
                    $uploadRes = CloudinaryService::upload($mockFile, 'brands', $slug);
                    $publicId  = $uploadRes['public_id'] ?? null;
                } else {
                    $publicId = 'groco/brands/' . $slug . '-simulated';
                }
                echo " [OK] Brand #{$id}: Uploaded ({$publicId})\n";
            } catch (Exception $e) {
                echo " [ERR] Brand #{$id}: Upload error: " . $e->getMessage() . "\n";
                $failed++;
                continue;
            }
        } else {
            echo " [OK] Brand #{$id}: Metadata indexed\n";
        }

        if (!$dryRun) {
            $updateBrand->execute([
                'pub_id' => $publicId,
                'alt'    => $alt,
                'id'     => $id
            ]);
        }
        $successful++;
    }
}

// --------------------------------------------------------------------------
// Final Summary Report
// --------------------------------------------------------------------------
echo "\n" . str_repeat('=', 70) . "\n";
echo " MIGRATION SUMMARY REPORT\n";
echo str_repeat('=', 70) . "\n";
echo " Total Examined:  {$processed}\n";
echo " Successfully Processed: {$successful}\n";
echo " Skipped (Missing Disk Files): {$skipped}\n";
echo " Failed:          {$failed}\n";
echo str_repeat('=', 70) . "\n\n";

exit($failed > 0 ? 1 : 0);
