<?php
/**
 * ==============================================================================
 * GroCo Enterprise POS — Inventory Integration Service
 * ==============================================================================
 * Concurrency-safe atomic inventory deductions, row locking, and movement logs.
 * Prevents race overselling between offline counters and online storefront.
 * ==============================================================================
 */

declare(strict_types=1);

namespace Groco\Pos;

use PDO;
use Exception;

class PosInventoryService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Lock and validate product stocks for cart items.
     *
     * @param array $items
     * @return array Map of [productId => productRow]
     * @throws Exception if any item has insufficient stock
     */
    public function lockAndValidateStock(array $items): array
    {
        $stmtLock = $this->pdo->prepare("
            SELECT id, name, sku, barcode, price, discount_price, stock, unit, is_active 
            FROM products 
            WHERE id = ? AND deleted_at IS NULL 
            FOR UPDATE
        ");

        $productDetails = [];

        foreach ($items as $item) {
            $pid = (int)$item['id'];
            $qty = (float)$item['quantity'];

            $stmtLock->execute([$pid]);
            $prod = $stmtLock->fetch(PDO::FETCH_ASSOC);

            if (!$prod) {
                throw new Exception("Product ID #{$pid} was not found in catalog or is deleted.");
            }

            if ((int)$prod['is_active'] === 0) {
                throw new Exception("Product '{$prod['name']}' is currently disabled/inactive.");
            }

            $currentStock = (float)$prod['stock'];
            if ($currentStock < $qty) {
                throw new Exception("Insufficient stock for '{$prod['name']}'. In stock: {$currentStock}, Requested: {$qty}");
            }

            $productDetails[$pid] = $prod;
        }

        return $productDetails;
    }

    /**
     * Deduct stock levels and write inventory audit movement log.
     */
    public function deductStock(
        int $productId,
        float $quantity,
        int $adminId,
        string $referenceNote,
        ?int $storeId = 1
    ): float {
        $stmtUpdate = $this->pdo->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
        $stmtUpdate->execute([$quantity, $productId]);

        // Get remaining stock after deduction
        $stmtStock = $this->pdo->prepare("SELECT stock FROM products WHERE id = ?");
        $stmtStock->execute([$productId]);
        $remainingStock = (float)$stmtStock->fetchColumn();

        // Write movement audit log
        $stmtLog = $this->pdo->prepare("
            INSERT INTO inventory_logs (
                product_id, admin_id, type, quantity, remaining_stock, note, created_at
            ) VALUES (
                :pid, :admin_id, 'stock_out', :qty, :rem, :note, NOW()
            )
        ");

        $stmtLog->execute([
            'pid'      => $productId,
            'admin_id' => $adminId,
            'qty'      => -$quantity,
            'rem'      => (int)$remainingStock,
            'note'     => $referenceNote
        ]);

        return $remainingStock;
    }

    /**
     * Restore stock levels (for returns, exchanges, voids).
     */
    public function restock(
        int $productId,
        float $quantity,
        int $adminId,
        string $referenceNote,
        ?int $storeId = 1
    ): float {
        $stmtUpdate = $this->pdo->prepare("UPDATE products SET stock = stock + ? WHERE id = ?");
        $stmtUpdate->execute([$quantity, $productId]);

        $stmtStock = $this->pdo->prepare("SELECT stock FROM products WHERE id = ?");
        $stmtStock->execute([$productId]);
        $remainingStock = (float)$stmtStock->fetchColumn();

        $stmtLog = $this->pdo->prepare("
            INSERT INTO inventory_logs (
                product_id, admin_id, type, quantity, remaining_stock, note, created_at
            ) VALUES (
                :pid, :admin_id, 'stock_in', :qty, :rem, :note, NOW()
            )
        ");

        $stmtLog->execute([
            'pid'      => $productId,
            'admin_id' => $adminId,
            'qty'      => $quantity,
            'rem'      => (int)$remainingStock,
            'note'     => $referenceNote
        ]);

        return $remainingStock;
    }
}
