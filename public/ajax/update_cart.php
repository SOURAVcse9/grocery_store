<?php
/**
 * ==========================================================================
 * public/ajax/update_cart.php
 * ==========================================================================
 * AJAX endpoint to update a cart item's quantity.
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
$quantity = (int) input('quantity', '0');

if ($productId <= 0) {
    json_response(false, 'Invalid product selection.', [], 400);
}

if ($quantity <= 0) {
    json_response(false, 'Quantity must be at least 1. To remove, click the delete button.', [], 400);
}

try {
    $pdo = db();
    $cartId = current_cart_id();

    // Fetch product stock and details
    $prodStmt = $pdo->prepare('SELECT name, stock, price, is_active FROM products WHERE id = :id LIMIT 1');
    $prodStmt->execute(['id' => $productId]);
    $product = $prodStmt->fetch();

    if (!$product || (int) $product['is_active'] === 0) {
        json_response(false, 'Product is currently unavailable.', [], 404);
    }

    $stockAvailable = (int) $product['stock'];
    if ($quantity > $stockAvailable) {
        json_response(false, "Cannot update. Only {$stockAvailable} units are available in stock.", [], 422);
    }

    // Check if item exists in the cart
    $itemStmt = $pdo->prepare('SELECT id FROM cart_items WHERE cart_id = :cart_id AND product_id = :product_id LIMIT 1');
    $itemStmt->execute(['cart_id' => $cartId, 'product_id' => $productId]);
    $item = $itemStmt->fetch();

    if (!$item) {
        json_response(false, 'Item not found in your cart.', [], 404);
    }

    // Update cart item quantity
    $updateStmt = $pdo->prepare('UPDATE cart_items SET quantity = :quantity, price = :price, updated_at = NOW() WHERE id = :id');
    $updateStmt->execute([
        'quantity' => $quantity,
        'price' => $product['price'],
        'id' => $item['id']
    ]);

    // Recalculate cart counts for badge
    $cartCount = cart_item_count();

    json_response(true, 'Cart updated successfully.', [
        'cart_count' => $cartCount
    ]);

} catch (PDOException $e) {
    error_log('[update_cart.php] Error: ' . $e->getMessage());
    json_response(false, 'An error occurred while updating the cart. Please try again.', [], 500);
}
