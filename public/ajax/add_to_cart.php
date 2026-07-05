<?php
/**
 * ==========================================================================
 * public/ajax/add_to_cart.php
 * ==========================================================================
 * AJAX endpoint to add a product to the cart.
 * Responds with JSON.
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/../dbconnect.php';

// Only POST allowed
require_method('POST');

// Verify CSRF
verify_csrf_or_fail(true);

// Read inputs
$productId = (int) input('product_id', '0');
$quantity = (int) input('quantity', '1');

// Validate
if ($productId <= 0) {
    json_response(false, 'Invalid product selection.', [], 400);
}

if ($quantity <= 0) {
    json_response(false, 'Quantity must be at least 1.', [], 400);
}

try {
    $pdo = db();

    // Check if product exists, is active, and check stock
    $stmt = $pdo->prepare('SELECT id, name, price, stock, is_active FROM products WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $productId]);
    $product = $stmt->fetch();

    if (!$product || (int) $product['is_active'] === 0) {
        json_response(false, 'Product not found or unavailable.', [], 404);
    }

    $stockAvailable = (int) $product['stock'];
    if ($stockAvailable <= 0) {
        json_response(false, 'Product is currently out of stock.', [], 422);
    }

    $cartId = current_cart_id();

    // Check if item already exists in the cart to check combined stock limit
    $itemStmt = $pdo->prepare('SELECT id, quantity FROM cart_items WHERE cart_id = :cart_id AND product_id = :product_id LIMIT 1');
    $itemStmt->execute(['cart_id' => $cartId, 'product_id' => $productId]);
    $existingItem = $itemStmt->fetch();

    $newQty = $quantity;
    if ($existingItem) {
        $newQty += (int) $existingItem['quantity'];
    }

    if ($newQty > $stockAvailable) {
        json_response(false, "Cannot add more items. Only {$stockAvailable} units are in stock.", [], 422);
    }

    // Upsert into cart_items
    if ($existingItem) {
        $updateStmt = $pdo->prepare('UPDATE cart_items SET quantity = :quantity, price = :price, updated_at = NOW() WHERE id = :id');
        $updateStmt->execute([
            'quantity' => $newQty,
            'price' => $product['price'],
            'id' => $existingItem['id']
        ]);
    } else {
        $insertStmt = $pdo->prepare('INSERT INTO cart_items (cart_id, product_id, quantity, price) VALUES (:cart_id, :product_id, :quantity, :price)');
        $insertStmt->execute([
            'cart_id' => $cartId,
            'product_id' => $productId,
            'quantity' => $newQty,
            'price' => $product['price']
        ]);
    }

    // Get updated total item count
    $cartCount = cart_item_count();

    json_response(true, sprintf('Added %s to your cart successfully.', $product['name']), [
        'cart_count' => $cartCount
    ]);

} catch (PDOException $e) {
    error_log('[add_to_cart.php] Error: ' . $e->getMessage());
    json_response(false, 'An error occurred while adding the item to the cart. Please try again.', [], 500);
}
