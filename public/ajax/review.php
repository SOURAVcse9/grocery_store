<?php
/**
 * ==========================================================================
 * public/ajax/review.php
 * ==========================================================================
 * AJAX mutations endpoint for product reviews (Add, Edit, Delete).
 * Responds with JSON.
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/../dbconnect.php';

// Only POST allowed
require_method('POST');

// Verify CSRF
verify_csrf_or_fail(true);

if (!is_logged_in()) {
    json_response(false, 'You must be logged in to review products.', [], 401);
}

$userId = current_user_id();
$action = input('action', '');

/**
 * sync_product_rating_stats()
 * Recalculates and updates avg_rating and review_count in products table.
 */
function sync_product_rating_stats(int $productId, PDO $pdo): void
{
    $stmt = $pdo->prepare("
        UPDATE products SET 
            avg_rating = COALESCE((SELECT ROUND(AVG(rating), 2) FROM product_reviews WHERE product_id = :pid AND status = 'approved'), 0.00),
            review_count = (SELECT COUNT(*) FROM product_reviews WHERE product_id = :pid2 AND status = 'approved')
        WHERE id = :pid3
    ");
    $stmt->execute([
        'pid' => $productId,
        'pid2' => $productId,
        'pid3' => $productId
    ]);
}

try {
    $pdo = db();

    // ---- A. Add Review ----
    if ($action === 'add') {
        $productId = (int) input('product_id', '0');
        $rating = (int) input('rating', '0');
        $reviewTitle = trim(input('review_title', ''));
        $reviewComment = trim(input('review', '')); // 'review' is sent from textarea

        if ($productId <= 0) {
            json_response(false, 'Invalid product selection.', [], 400);
        }

        // 1. Enforce verified purchaser validation rule
        $purchaseStmt = $pdo->prepare('
            SELECT o.id 
            FROM orders o
            JOIN order_items oi ON oi.order_id = o.id
            WHERE o.user_id = :uid 
              AND oi.product_id = :pid 
              AND o.status = \'delivered\'
            LIMIT 1
        ');
        $purchaseStmt->execute(['uid' => $userId, 'pid' => $productId]);
        $orderId = $purchaseStmt->fetchColumn();

        if ($orderId === false) {
            json_response(false, 'Only verified customers who purchased and received this product can review it.', [], 403);
        }
        $orderId = (int) $orderId;

        // 2. Prevent duplicate reviews (one review per product per user)
        $dupStmt = $pdo->prepare('SELECT id FROM product_reviews WHERE product_id = :pid AND user_id = :uid LIMIT 1');
        $dupStmt->execute(['pid' => $productId, 'uid' => $userId]);
        if ($dupStmt->fetch()) {
            json_response(false, 'You have already reviewed this product. You can edit your existing review instead.', [], 422);
        }

        // 3. Validation checks
        $v = new Validator();
        $v->custom('rating', $rating >= 1 && $rating <= 5, 'Please select a rating between 1 and 5 stars.')
          ->required('review', $reviewComment, 'Review comment is required.')
          ->length('review', $reviewComment, 10, 1000, 'Review comment must be between 10 and 1000 characters.');

        if ($v->hasErrors()) {
            json_response(false, $v->first(), [], 422);
        }

        // 4. Handle Optional Images Upload (max 3 images, max 5MB each)
        $uploadedImages = [];
        if (!empty($_FILES['review_images']['name'][0])) {
            $filesCount = count($_FILES['review_images']['name']);
            if ($filesCount > 3) {
                json_response(false, 'You can upload a maximum of 3 images.', [], 422);
            }

            $uploadDir = __DIR__ . '/../../storage/uploads/reviews';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0775, true);
            }

            for ($i = 0; $i < $filesCount; $i++) {
                if ($_FILES['review_images']['error'][$i] !== UPLOAD_ERR_OK) {
                    continue;
                }

                $tmpName = $_FILES['review_images']['tmp_name'][$i];
                $name = $_FILES['review_images']['name'][$i];
                $size = $_FILES['review_images']['size'][$i];
                
                if ($size > 5 * 1024 * 1024) {
                    json_response(false, 'Each image must be smaller than 5MB.', [], 422);
                }

                $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                    json_response(false, 'Only JPG, JPEG, PNG, and WebP images are allowed.', [], 422);
                }

                if (!@getimagesize($tmpName)) {
                    json_response(false, 'Uploaded file is not a valid image.', [], 422);
                }

                $uniqueName = 'rev_' . uniqid('', true) . '.' . $ext;
                $targetFile = $uploadDir . '/' . $uniqueName;

                if (move_uploaded_file($tmpName, $targetFile)) {
                    $uploadedImages[] = 'storage/uploads/reviews/' . $uniqueName;
                }
            }
        }

        $imagesJson = !empty($uploadedImages) ? json_encode($uploadedImages) : null;

        // 5. Save review
        $insert = $pdo->prepare('
            INSERT INTO product_reviews (product_id, user_id, order_id, rating, review_title, review_comment, review_images, verified_purchase, status, created_at, updated_at)
            VALUES (:pid, :uid, :oid, :rating, :title, :comment, :images, 1, \'approved\', NOW(), NOW())
        ');
        $insert->execute([
            'pid'     => $productId,
            'uid'     => $userId,
            'oid'     => $orderId,
            'rating'  => $rating,
            'title'   => $reviewTitle,
            'comment' => $reviewComment,
            'images'  => $imagesJson
        ]);

        // Sync product counts
        sync_product_rating_stats($productId, $pdo);

        json_response(true, 'Review submitted successfully!');
    }

    // ---- B. Edit Review ----
    if ($action === 'edit') {
        $reviewId = (int) input('review_id', '0');
        $rating = (int) input('rating', '0');
        $reviewTitle = trim(input('review_title', ''));
        $reviewComment = trim(input('review', ''));

        if ($reviewId <= 0) {
            json_response(false, 'Invalid review selection.', [], 400);
        }

        // Verify review exists and belongs to this user
        $check = $pdo->prepare('SELECT * FROM product_reviews WHERE id = :id AND user_id = :uid LIMIT 1');
        $check->execute(['id' => $reviewId, 'uid' => $userId]);
        $existingReview = $check->fetch();

        if (!$existingReview) {
            json_response(false, 'Review not found or unauthorized.', [], 403);
        }

        $productId = (int) $existingReview['product_id'];

        // Validation
        $v = new Validator();
        $v->custom('rating', $rating >= 1 && $rating <= 5, 'Please select a rating between 1 and 5 stars.')
          ->required('review', $reviewComment, 'Review comment is required.')
          ->length('review', $reviewComment, 10, 1000, 'Review comment must be between 10 and 1000 characters.');

        if ($v->hasErrors()) {
            json_response(false, $v->first(), [], 422);
        }

        // Handle Optional Images Upload (deletes old ones if new ones uploaded)
        $uploadedImages = [];
        if (!empty($_FILES['review_images']['name'][0])) {
            $filesCount = count($_FILES['review_images']['name']);
            if ($filesCount > 3) {
                json_response(false, 'You can upload a maximum of 3 images.', [], 422);
            }

            $uploadDir = __DIR__ . '/../../storage/uploads/reviews';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0775, true);
            }

            for ($i = 0; $i < $filesCount; $i++) {
                if ($_FILES['review_images']['error'][$i] !== UPLOAD_ERR_OK) {
                    continue;
                }

                $tmpName = $_FILES['review_images']['tmp_name'][$i];
                $name = $_FILES['review_images']['name'][$i];
                $size = $_FILES['review_images']['size'][$i];
                
                if ($size > 5 * 1024 * 1024) {
                    json_response(false, 'Each image must be smaller than 5MB.', [], 422);
                }

                $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                    json_response(false, 'Only JPG, JPEG, PNG, and WebP images are allowed.', [], 422);
                }

                if (!@getimagesize($tmpName)) {
                    json_response(false, 'Uploaded file is not a valid image.', [], 422);
                }

                $uniqueName = 'rev_' . uniqid('', true) . '.' . $ext;
                $targetFile = $uploadDir . '/' . $uniqueName;

                if (move_uploaded_file($tmpName, $targetFile)) {
                    $uploadedImages[] = 'storage/uploads/reviews/' . $uniqueName;
                }
            }
        }

        $imagesJson = !empty($uploadedImages) ? json_encode($uploadedImages) : null;

        if ($imagesJson !== null) {
            // Delete old files from server
            if (!empty($existingReview['review_images'])) {
                $oldImages = json_decode($existingReview['review_images'], true);
                if (is_array($oldImages)) {
                    foreach ($oldImages as $oldImg) {
                        $fullPath = __DIR__ . '/../../' . $oldImg;
                        if (file_exists($fullPath)) {
                            @unlink($fullPath);
                        }
                    }
                }
            }

            $update = $pdo->prepare('
                UPDATE product_reviews 
                SET rating = :rating, review_title = :title, review_comment = :comment, review_images = :images, updated_at = NOW() 
                WHERE id = :id
            ');
            $update->execute([
                'rating'  => $rating,
                'title'   => $reviewTitle,
                'comment' => $reviewComment,
                'images'  => $imagesJson,
                'id'      => $reviewId
            ]);
        } else {
            $update = $pdo->prepare('
                UPDATE product_reviews 
                SET rating = :rating, review_title = :title, review_comment = :comment, updated_at = NOW() 
                WHERE id = :id
            ');
            $update->execute([
                'rating'  => $rating,
                'title'   => $reviewTitle,
                'comment' => $reviewComment,
                'id'      => $reviewId
            ]);
        }

        // Sync product counts
        sync_product_rating_stats($productId, $pdo);

        json_response(true, 'Review updated successfully!');
    }

    // ---- C. Delete Review ----
    if ($action === 'delete') {
        $reviewId = (int) input('review_id', '0');

        if ($reviewId <= 0) {
            json_response(false, 'Invalid review selection.', [], 400);
        }

        // Verify review ownership
        $check = $pdo->prepare('SELECT product_id, review_images FROM product_reviews WHERE id = :id AND user_id = :uid LIMIT 1');
        $check->execute(['id' => $reviewId, 'uid' => $userId]);
        $existingReview = $check->fetch();

        if (!$existingReview) {
            json_response(false, 'Review not found or unauthorized.', [], 403);
        }

        $productId = (int) $existingReview['product_id'];

        // Delete files from server
        if (!empty($existingReview['review_images'])) {
            $oldImages = json_decode($existingReview['review_images'], true);
            if (is_array($oldImages)) {
                foreach ($oldImages as $oldImg) {
                    $fullPath = __DIR__ . '/../../' . $oldImg;
                    if (file_exists($fullPath)) {
                        @unlink($fullPath);
                    }
                }
            }
        }

        // Delete review row
        $delete = $pdo->prepare('DELETE FROM product_reviews WHERE id = :id');
        $delete->execute(['id' => $reviewId]);

        // Sync product counts
        sync_product_rating_stats($productId, $pdo);

        json_response(true, 'Review deleted successfully.');
    }

    // Invalid action fallback
    json_response(false, 'Invalid action specified.', [], 400);

} catch (PDOException $e) {
    error_log('[ajax/review.php] Mutation failed: ' . $e->getMessage());
    json_response(false, 'Failed to process review due to database error.', [], 500);
}
