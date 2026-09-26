<?php
/**
 * ==============================================================================
 * GroCo Inventory Service Layer
 * ==============================================================================
 * Centralized business logic for real-time stock verification, atomic reservations,
 * low-stock alert reporting, and POS inventory sync.
 * ==============================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/CacheService.php';

class InventoryService
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getConnection();
    }

    /**
     * Check if product has sufficient stock
     */
    public function checkStock(int $productId, int $quantity = 1): bool
    {
        if ($productId <= 0 || $quantity <= 0) return false;

        $stmt = $this->pdo->prepare("SELECT stock FROM products WHERE id = ?");
        $stmt->execute([$productId]);
        $stock = $stmt->fetchColumn();

        return ($stock !== false && (int)$stock >= $quantity);
    }

    /**
     * Atomically adjust stock (positive to restock, negative to deduct)
     */
    public function adjustStock(int $productId, int $quantityDelta): bool
    {
        if ($productId <= 0 || $quantityDelta === 0) return false;

        if ($quantityDelta < 0) {
            $deductQty = abs($quantityDelta);
            $stmt = $this->pdo->prepare("UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?");
            $stmt->execute([$deductQty, $productId, $deductQty]);
        } else {
            $stmt = $this->pdo->prepare("UPDATE products SET stock = stock + ? WHERE id = ?");
            $stmt->execute([$quantityDelta, $productId]);
        }

        $affected = $stmt->rowCount() > 0;
        if ($affected) {
            CacheService::invalidateCatalog();
        }
        return $affected;
    }

    /**
     * Get products running low on stock
     */
    public function getLowStockProducts(int $threshold = 10): array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, name, sku, stock, price, thumbnail 
            FROM products 
            WHERE stock <= ? AND (status = 'active' OR is_active = 1)
            ORDER BY stock ASC
        ");
        $stmt->execute([$threshold]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
