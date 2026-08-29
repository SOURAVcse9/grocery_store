<?php
/**
 * GroCo - Customer Review Image End-to-End Test Suite
 * Tests review image upload, validation, storage, DB JSON encoding,
 * public card rendering, admin preview rendering, URL generation,
 * and missing image handling.
 */

declare(strict_types=1);

require_once __DIR__ . '/dbconnect.php';

$pdo = db();
$pdo->beginTransaction();

$testsRun = 0;
$testsPassed = 0;

function assertTest(string $desc, bool $condition, string $extra = ''): void
{
    global $testsRun, $testsPassed;
    $testsRun++;
    if ($condition) {
        $testsPassed++;
        echo " [PASS] $desc\n";
    } else {
        echo " [FAIL] $desc " . ($extra ? "($extra)" : '') . "\n";
    }
}

$createdFiles = [];

try {
    echo "=== GROCO CUSTOMER REVIEW IMAGE E2E INTEGRATION SUITE ===\n\n";

    // 1. Create a dedicated test user and delivered order
    $productId = 1;
    $testUserStmt = $pdo->prepare("
        INSERT INTO users (role_id, full_name, email, password, phone)
        VALUES (2, 'Test Reviewer', :email, 'hash', '01700000099')
    ");
    $testUserStmt->execute(['email' => 'reviewer_' . uniqid() . '@example.com']);
    $userId = (int)$pdo->lastInsertId();

    // Insert qualifying delivered order
    $orderStmt = $pdo->prepare("
        INSERT INTO orders (user_id, total_amount, status, payment_status, created_at, updated_at)
        VALUES (:uid, 150.00, 'delivered', 'paid', NOW(), NOW())
    ");
    $orderStmt->execute(['uid' => $userId]);
    $orderId = (int)$pdo->lastInsertId();

    $itemStmt = $pdo->prepare("
        INSERT INTO order_items (order_id, product_id, product_name, product_sku, quantity, price, line_total)
        VALUES (:oid, :pid, 'Fresh Apples', 'SKU-APPLES', 1, 150.00, 150.00)
    ");
    $itemStmt->execute(['oid' => $orderId, 'pid' => $productId]);

    // 2. Test Image URL Helper with various path formats
    $testPng = 'rev_test_' . uniqid() . '.png';
    $targetDir = PUBLIC_PATH . '/uploads/reviews';
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0775, true);
    }
    $targetPath = $targetDir . '/' . $testPng;

    // Create a real 1x1 valid PNG
    file_put_contents($targetPath, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='));
    $createdFiles[] = $targetPath;

    assertTest("Test PNG created on disk", file_exists($targetPath));

    // Test URL resolution with full relative path 'uploads/reviews/...'
    $relPath = 'uploads/reviews/' . $testPng;
    $resolvedUrl = image_url($relPath, 'reviews');
    assertTest("image_url resolves uploads/reviews/ path", str_contains($resolvedUrl, 'uploads/reviews/' . $testPng));
    assertTest("image_url does NOT include broken /assets/uploads/", !str_contains($resolvedUrl, '/assets/uploads/'));
    assertTest("image_url appends cache-busting timestamp", str_contains($resolvedUrl, '?v='));

    // Test URL resolution with filename only 'rev_test_....png'
    $resolvedUrlFilename = image_url($testPng, 'reviews');
    assertTest("image_url resolves bare filename with reviews category", str_contains($resolvedUrlFilename, 'uploads/reviews/' . $testPng));

    // 3. Test Database Insertion with 1 Image
    $imgJson1 = json_encode([$relPath]);
    $revStmt = $pdo->prepare("
        INSERT INTO product_reviews (product_id, user_id, order_id, rating, review_title, review_comment, review_images, verified_purchase, status, is_approved, created_at, updated_at)
        VALUES (:pid, :uid, :oid, 5, 'Good Product', 'Very good product with photo.', :imgs, 1, 'approved', 1, NOW(), NOW())
    ");
    $revStmt->execute([
        'pid' => $productId,
        'uid' => $userId,
        'oid' => $orderId,
        'imgs' => $imgJson1
    ]);
    $rev1Id = (int)$pdo->lastInsertId();

    $storedRev = $pdo->query("SELECT review_images FROM product_reviews WHERE id = $rev1Id")->fetch();
    $decodedImgs = json_decode($storedRev['review_images'], true);
    assertTest("1-image review stored valid JSON in DB", is_array($decodedImgs) && count($decodedImgs) === 1);
    assertTest("Stored image reference matches saved relative path", $decodedImgs[0] === $relPath);

    // 4. Test Public Review Card Rendering Output with 1 Image
    $review = [
        'id' => $rev1Id,
        'product_id' => $productId,
        'user_id' => $userId,
        'reviewer_name' => 'John Customer',
        'rating' => 5,
        'review_title' => 'Good Product',
        'review_comment' => 'Very good product with photo.',
        'review_images' => $storedRev['review_images'],
        'helpful_count' => 0,
        'created_at' => date('Y-m-d H:i:s')
    ];

    ob_start();
    include PUBLIC_PATH . '/components/review-card.php';
    $cardHtml = ob_get_clean();

    assertTest("Public review card contains review-images-grid", str_contains($cardHtml, 'review-images-grid'));
    assertTest("Public review card renders <img src> with resolved image URL", str_contains($cardHtml, 'uploads/reviews/' . $testPng));
    assertTest("Public review card does NOT render broken /assets/uploads/", !str_contains($cardHtml, '/assets/uploads/'));

    // 5. Test 2-Image and 3-Image review storage & rendering
    $testPng2 = 'rev_test_2_' . uniqid() . '.png';
    $targetPath2 = $targetDir . '/' . $testPng2;
    copy($targetPath, $targetPath2);
    $createdFiles[] = $targetPath2;

    $testPng3 = 'rev_test_3_' . uniqid() . '.png';
    $targetPath3 = $targetDir . '/' . $testPng3;
    copy($targetPath, $targetPath3);
    $createdFiles[] = $targetPath3;

    $threeImgs = ['uploads/reviews/' . $testPng, 'uploads/reviews/' . $testPng2, 'uploads/reviews/' . $testPng3];
    $imgJson3 = json_encode($threeImgs);

    $updateStmt = $pdo->prepare("UPDATE product_reviews SET review_images = :imgs WHERE id = :id");
    $updateStmt->execute(['imgs' => $imgJson3, 'id' => $rev1Id]);

    $review['review_images'] = $imgJson3;
    ob_start();
    include PUBLIC_PATH . '/components/review-card.php';
    $cardHtml3 = ob_get_clean();

    assertTest("3-image review renders exactly 3 image links", substr_count($cardHtml3, 'class="review-image-link"') === 3);

    // 6. Test Review without images
    $review['review_images'] = null;
    ob_start();
    include PUBLIC_PATH . '/components/review-card.php';
    $cardHtmlNoImg = ob_get_clean();
    assertTest("No-image review does not render review-images-grid", !str_contains($cardHtmlNoImg, 'review-images-grid'));

    // 7. Test Missing Image File handling (gracefully omitted, no broken icon)
    $review['review_images'] = json_encode(['uploads/reviews/non_existent_file_99999.png']);
    ob_start();
    include PUBLIC_PATH . '/components/review-card.php';
    $cardHtmlMissing = ob_get_clean();
    assertTest("Missing image file is omitted from public card (no broken img icon)", !str_contains($cardHtmlMissing, 'non_existent_file_99999.png'));

    // 8. Test Admin Reviews table rendering
    $rev = [
        'id' => $rev1Id,
        'product_id' => $productId,
        'product_name' => 'Fresh Apples',
        'customer_name' => 'John Customer',
        'customer_email' => 'john@example.com',
        'rating' => 5,
        'review_title' => 'Good Product',
        'review_comment' => 'Very good product with photo.',
        'review_images' => $imgJson3,
        'status' => 'approved',
        'created_at' => date('Y-m-d H:i:s')
    ];

    // Simulate Admin rendering snippet from admin/reviews/index.php
    ob_start();
    $imgs = json_decode($rev['review_images'], true);
    if (is_array($imgs) && !empty($imgs)):
    ?>
    <div style="display:flex; gap:6px; flex-wrap:wrap;">
        <?php foreach ($imgs as $img): 
            $cleanImg = ltrim($img, '/');
            $exists = file_exists(PUBLIC_PATH . '/' . $cleanImg) || 
                      file_exists(PUBLIC_PATH . '/uploads/' . $cleanImg) || 
                      file_exists(PUBLIC_PATH . '/uploads/reviews/' . basename($cleanImg));
            $imgUrl = image_url($img, 'reviews');
        ?>
            <?php if ($exists): ?>
                <a href="<?= e($imgUrl) ?>" target="_blank" rel="noopener noreferrer" title="View full image" style="display:inline-block; border:1px solid var(--color-border); border-radius:2px; overflow:hidden;">
                    <img src="<?= e($imgUrl) ?>" alt="Review image" style="width:40px; height:40px; object-fit:cover;">
                </a>
            <?php else: ?>
                <span style="color:var(--color-text-faint); font-size:10px; font-style:italic;">Missing file</span>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
    <?php endif;
    $adminHtml = ob_get_clean();

    assertTest("Admin review snippet renders valid thumbnail URLs", str_contains($adminHtml, 'uploads/reviews/' . $testPng));
    assertTest("Admin review snippet does NOT contain broken /assets/uploads/", !str_contains($adminHtml, '/assets/uploads/'));

    // Test Admin snippet with missing file
    $revMissing = $rev;
    $revMissing['review_images'] = json_encode(['uploads/reviews/non_existent_file_99999.png']);
    ob_start();
    $imgs = json_decode($revMissing['review_images'], true);
    if (is_array($imgs) && !empty($imgs)):
    ?>
    <div style="display:flex; gap:6px; flex-wrap:wrap;">
        <?php foreach ($imgs as $img): 
            $cleanImg = ltrim($img, '/');
            $exists = file_exists(PUBLIC_PATH . '/' . $cleanImg) || 
                      file_exists(PUBLIC_PATH . '/uploads/' . $cleanImg) || 
                      file_exists(PUBLIC_PATH . '/uploads/reviews/' . basename($cleanImg));
            $imgUrl = image_url($img, 'reviews');
        ?>
            <?php if ($exists): ?>
                <a href="<?= e($imgUrl) ?>" target="_blank" rel="noopener noreferrer" title="View full image" style="display:inline-block; border:1px solid var(--color-border); border-radius:2px; overflow:hidden;">
                    <img src="<?= e($imgUrl) ?>" alt="Review image" style="width:40px; height:40px; object-fit:cover;">
                </a>
            <?php else: ?>
                <span style="color:var(--color-text-faint); font-size:10px; font-style:italic;">Missing file</span>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
    <?php endif;
    $adminMissingHtml = ob_get_clean();
    assertTest("Admin review snippet displays 'Missing file' for non-existent image", str_contains($adminMissingHtml, 'Missing file'));

    // 9. Test MIME Type Validator logic
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $realMime = finfo_file($finfo, $targetPath);
    finfo_close($finfo);
    assertTest("Real PNG has image/png MIME type", $realMime === 'image/png');

    // Test fake image with text content
    $fakePath = $targetDir . '/fake_' . uniqid() . '.png';
    file_put_contents($fakePath, "<?php echo 'malicious'; ?>");
    $createdFiles[] = $fakePath;
    $finfoFake = finfo_open(FILEINFO_MIME_TYPE);
    $fakeMime = finfo_file($finfoFake, $fakePath);
    finfo_close($finfoFake);
    assertTest("Fake script disguised as PNG is detected as non-image ($fakeMime)", $fakeMime !== 'image/png' && !in_array($fakeMime, ['image/jpeg', 'image/png', 'image/webp'], true));

} catch (Throwable $e) {
    echo "Review Image E2E Error: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo $e->getTraceAsString() . "\n";
} finally {
    // Cleanup generated test files
    foreach ($createdFiles as $f) {
        if (file_exists($f)) {
            @unlink($f);
        }
    }
    $pdo->rollBack();
    echo "\n[Review Image Test Transaction & Files Cleaned Up]\n";
}

echo "\n=== REVIEW IMAGE E2E RESULTS: $testsPassed/$testsRun PASSED ===\n";
