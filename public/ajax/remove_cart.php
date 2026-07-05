<?php
/**
 * ==========================================================================
 * public/ajax/remove_cart.php
 * ==========================================================================
 * AJAX endpoint to remove an item from the cart.
 * Responds with JSON.
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/../dbconnect.php';

// Only POST allowed
require_method('POST');

// Verify CSRF
verify_csrf_or_fail(true);

$productId = (int) input('product_id', '0');

if ($productId <= 0) {
    json_response(false, 'Invalid product selection.', [], 400);
}

try {
    $pdo = db();
    $cartId = current_cart_id();

    // Check if item exists in the cart
    $itemStmt = $pdo->prepare('SELECT id FROM cart_items WHERE cart_id = :cart_id AND product_id = :product_id LIMIT 1');
    $itemStmt->execute(['cart_id' => $cartId, 'product_id' => $productId]);
    $item = $itemStmt->fetch();

    if (!$item) {
        json_response(false, 'Item not found in your cart.', [], 404);
    }

    // Delete item from cart
    $deleteStmt = $pdo->prepare('DELETE FROM cart_items WHERE id = :id');
    $deleteStmt->execute(['id' => $item['id']]);

    // Recalculate cart counts for badge
    $cartCount = cart_item_count();

    json_response(true, 'Item removed from cart.', [
        'cart_count' => $cartCount
    ]);

} catch (PDOException $e) {
    error_log('[remove_cart.php] Error: ' . $e->getMessage());
    json_response(false, 'An error occurred. Please try again.', [], 500);
}
