<?php
/**
 * ==========================================================================
 * public/ajax/wishlist.php
 * ==========================================================================
 * AJAX mutations controller for wishlists (Add, Remove, Clear).
 * Supports logged-in DB storage and guest session fallback, returning JSON.
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/../dbconnect.php';

// Only POST allowed
require_method('POST');

// Verify CSRF
verify_csrf_or_fail(true);

$action = input('action', '');
$productId = (int) input('product_id', '0');

try {
    $pdo = db();

    // 1. Resolve Product existence for 'add' action
    if ($action === 'add') {
        if ($productId <= 0) {
            json_response(false, 'Invalid product selection.', [], 400);
        }
        $prodCheck = $pdo->prepare('SELECT id FROM products WHERE id = :id AND is_active = 1 LIMIT 1');
        $prodCheck->execute(['id' => $productId]);
        if (!$prodCheck->fetch()) {
            json_response(false, 'Product not found or unavailable.', [], 404);
        }
    }

    // 2. Perform actions based on login status
    if (is_logged_in()) {
        $userId = current_user_id();

        if ($action === 'add') {
            $stmt = $pdo->prepare('INSERT IGNORE INTO wishlists (user_id, product_id, created_at) VALUES (:uid, :pid, NOW())');
            $stmt->execute(['uid' => $userId, 'pid' => $productId]);
            
            $count = wishlist_item_count();
            json_response(true, 'Product added to your wishlist.', ['wishlist_count' => $count]);
        } 
        
        elseif ($action === 'remove') {
            $stmt = $pdo->prepare('DELETE FROM wishlists WHERE user_id = :uid AND product_id = :pid');
            $stmt->execute(['uid' => $userId, 'pid' => $productId]);
            
            $count = wishlist_item_count();
            json_response(true, 'Product removed from wishlist.', ['wishlist_count' => $count]);
        } 
        
        elseif ($action === 'clear') {
            $stmt = $pdo->prepare('DELETE FROM wishlists WHERE user_id = :uid');
            $stmt->execute(['uid' => $userId]);
            
            json_response(true, 'Wishlist cleared successfully.', ['wishlist_count' => 0]);
        }
    } else {
        // Guest session fallback
        if (empty($_SESSION['wishlist'])) {
            $_SESSION['wishlist'] = [];
        }

        if ($action === 'add') {
            $_SESSION['wishlist'][$productId] = true;
            $count = count($_SESSION['wishlist']);
            json_response(true, 'Product added to wishlist.', ['wishlist_count' => $count]);
        } 
        
        elseif ($action === 'remove') {
            if (isset($_SESSION['wishlist'][$productId])) {
                unset($_SESSION['wishlist'][$productId]);
            }
            $count = count($_SESSION['wishlist']);
            json_response(true, 'Product removed from wishlist.', ['wishlist_count' => $count]);
        } 
        
        elseif ($action === 'clear') {
            $_SESSION['wishlist'] = [];
            json_response(true, 'Wishlist cleared.', ['wishlist_count' => 0]);
        }
    }

    json_response(false, 'Invalid wishlist action.', [], 400);

} catch (PDOException $e) {
    error_log('[ajax/wishlist.php] Error: ' . $e->getMessage());
    json_response(false, 'Failed to update wishlist due to database error.', [], 500);
}
