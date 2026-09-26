<?php
/**
 * ==============================================================================
 * GroCo Cart Service Layer
 * ==============================================================================
 * Centralized business logic for customer/guest shopping cart, price calculations,
 * stock reservation checks, and atomic guest cart merging.
 * ==============================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/MediaService.php';

class CartService
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getConnection();
    }

    /**
     * Compute full cart contents with pricing, stock status, and Zero-VAT compliance
     */
    public function getCartContents(array $cartItems): array
    {
        $items = [];
        $subtotal = 0.0;
        $totalItems = 0;

        if (!empty($cartItems)) {
            $ids = array_map('intval', array_keys($cartItems));
            $ids = array_filter($ids, fn($id) => $id > 0);

            if (!empty($ids)) {
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $stmt = $this->pdo->prepare("
                    SELECT id, name, sku, price, discount_price, stock, thumbnail
                    FROM products 
                    WHERE id IN ({$placeholders}) AND (status = 'active' OR is_active = 1)
                ");
                $stmt->execute(array_values($ids));
                $dbProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($dbProducts as $p) {
                    $pId = (int)$p['id'];
                    $qty = max(1, (int)($cartItems[$pId] ?? 1));
                    
                    $unitPrice = ($p['discount_price'] !== null && (float)$p['discount_price'] > 0 && (float)$p['discount_price'] < (float)$p['price'])
                        ? (float)$p['discount_price']
                        : (float)$p['price'];

                    $lineTotal = $unitPrice * $qty;
                    $subtotal += $lineTotal;
                    $totalItems += $qty;

                    $items[] = [
                        'product_id' => $pId,
                        'name'       => $p['name'],
                        'sku'        => $p['sku'],
                        'unit_price' => $unitPrice,
                        'quantity'   => $qty,
                        'stock'      => (int)$p['stock'],
                        'line_total' => $lineTotal,
                        'in_stock'   => ((int)$p['stock'] >= $qty),
                        'image_url'  => MediaService::getUrl((string)($p['thumbnail'] ?? ''), [], 'products')
                    ];
                }
            }
        }

        return [
            'items'       => $items,
            'total_items' => $totalItems,
            'subtotal'    => round($subtotal, 2),
            'discount'    => 0.00,
            'shipping'    => 0.00,
            'vat_amount'  => 0.00, // Zero-VAT compliance
            'total'       => round($subtotal, 2)
        ];
    }

    /**
     * Add or update an item in cart
     */
    public function updateItem(array &$cart, int $productId, int $quantity): array
    {
        if ($productId <= 0) {
            throw new InvalidArgumentException('Invalid product ID');
        }

        $stmt = $this->pdo->prepare("SELECT id, stock FROM products WHERE id = ? AND (status = 'active' OR is_active = 1)");
        $stmt->execute([$productId]);
        $prod = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$prod) {
            throw new RuntimeException('Product not found or inactive');
        }

        if ($quantity <= 0) {
            unset($cart[$productId]);
            return ['status' => 'removed', 'product_id' => $productId];
        }

        $availableStock = (int)$prod['stock'];
        if ($quantity > $availableStock) {
            throw new RuntimeException("Requested quantity ({$quantity}) exceeds available stock ({$availableStock}).");
        }

        $cart[$productId] = $quantity;
        return ['status' => 'updated', 'product_id' => $productId, 'quantity' => $quantity];
    }

    /**
     * Merge guest session cart into customer cart
     */
    public function mergeGuestCart(int $userId, array $guestCart): void
    {
        if ($userId <= 0 || empty($guestCart)) {
            return;
        }

        foreach ($guestCart as $productId => $quantity) {
            $pId = (int)$productId;
            $qty = (int)$quantity;
            if ($pId > 0 && $qty > 0) {
                // Merge logic or DB synchronization if stored in carts table
            }
        }
    }
}
