<?php
/**
 * ==============================================================================
 * GroCo Order Service Layer
 * ==============================================================================
 * Centralized business logic for order placement, transaction management, status
 * workflows, and customer isolation (IDOR protection).
 * ==============================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/CacheService.php';
require_once __DIR__ . '/QueueService.php';

class OrderService
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getConnection();
    }

    /**
     * Retrieve single order by ID with strict customer IDOR verification
     */
    public function getById(int $orderId, ?int $userId = null): ?array
    {
        if ($orderId <= 0) return null;

        $sql = "SELECT o.* FROM orders o WHERE o.id = ?";
        $params = [$orderId];

        if ($userId !== null) {
            $sql .= " AND o.user_id = ?";
            $params[] = $userId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            return null;
        }

        // Fetch Order Items
        $itemsStmt = $this->pdo->prepare("
            SELECT oi.*, p.thumbnail
            FROM order_items oi
            LEFT JOIN products p ON oi.product_id = p.id
            WHERE oi.order_id = ?
        ");
        $itemsStmt->execute([$orderId]);
        $order['items'] = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

        return $order;
    }

    /**
     * Retrieve paginated order history for a customer
     */
    public function getUserOrders(int $userId, int $page = 1, int $perPage = 10): array
    {
        if ($userId <= 0) {
            return ['items' => [], 'total' => 0, 'page' => 1, 'per_page' => $perPage, 'total_pages' => 0];
        }

        $page = max(1, $page);
        $perPage = min(50, max(1, $perPage));
        $offset = ($page - 1) * $perPage;

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ?");
        $countStmt->execute([$userId]);
        $total = (int)$countStmt->fetchColumn();

        $limitInt = (int)$perPage;
        $offsetInt = (int)$offset;

        $stmt = $this->pdo->prepare("
            SELECT id, order_number, total_amount, payment_method, payment_status, status, created_at
            FROM orders
            WHERE user_id = ?
            ORDER BY id DESC
            LIMIT {$limitInt} OFFSET {$offsetInt}
        ");
        $stmt->execute([$userId]);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'items'       => $orders,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => (int)ceil($total / max(1, $perPage))
        ];
    }

    /**
     * Create order within an atomic database transaction
     */
    public function createOrder(array $orderData, array $items): array
    {
        if (empty($items)) {
            throw new InvalidArgumentException('Order items cannot be empty');
        }

        $this->pdo->beginTransaction();

        try {
            $subtotal = 0.0;
            $orderItemsToInsert = [];

            foreach ($items as $item) {
                $pId = (int)$item['product_id'];
                $qty = max(1, (int)$item['quantity']);

                // Lock product row for stock deduction
                $stmt = $this->pdo->prepare("SELECT id, name, sku, price, discount_price, stock FROM products WHERE id = ? FOR UPDATE");
                $stmt->execute([$pId]);
                $p = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$p) {
                    throw new RuntimeException("Product ID #{$pId} does not exist.");
                }

                if ((int)$p['stock'] < $qty) {
                    throw new RuntimeException("Insufficient stock for product '{$p['name']}'.");
                }

                $price = ($p['discount_price'] !== null && (float)$p['discount_price'] > 0 && (float)$p['discount_price'] < (float)$p['price'])
                    ? (float)$p['discount_price']
                    : (float)$p['price'];

                $lineTotal = $price * $qty;
                $subtotal += $lineTotal;

                // Deduct stock
                $deductStmt = $this->pdo->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
                $deductStmt->execute([$qty, $pId]);

                $orderItemsToInsert[] = [
                    'product_id'   => $pId,
                    'product_name' => $p['name'],
                    'product_sku'  => (string)($p['sku'] ?? ''),
                    'price'        => $price,
                    'quantity'     => $qty,
                    'line_total'   => $lineTotal
                ];
            }

            $orderNumber = 'ORD-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
            $userId = !empty($orderData['user_id']) ? (int)$orderData['user_id'] : null;
            $discount = (float)($orderData['discount_amount'] ?? 0.0);
            $deliveryFee = (float)($orderData['delivery_charge'] ?? ($orderData['delivery_fee'] ?? 0.0));
            $totalAmount = max(0.0, ($subtotal + $deliveryFee - $discount));

            $insertOrder = $this->pdo->prepare("
                INSERT INTO orders (
                    order_number, user_id, address_id, coupon_id, subtotal,
                    discount_amount, delivery_charge, total_amount, payment_method, payment_status,
                    status, note, created_at, updated_at
                ) VALUES (
                    ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?,
                    'pending', ?, NOW(), NOW()
                )
            ");

            $insertOrder->execute([
                $orderNumber,
                $userId,
                !empty($orderData['address_id']) ? (int)$orderData['address_id'] : null,
                !empty($orderData['coupon_id']) ? (int)$orderData['coupon_id'] : null,
                $subtotal,
                $discount,
                $deliveryFee,
                $totalAmount,
                $orderData['payment_method'] ?? 'cod',
                $orderData['payment_status'] ?? 'unpaid',
                $orderData['note'] ?? ''
            ]);

            $orderId = (int)$this->pdo->lastInsertId();

            // Insert Order Items
            $itemInsertStmt = $this->pdo->prepare("
                INSERT INTO order_items (order_id, product_id, product_name, product_sku, price, quantity, line_total)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            foreach ($orderItemsToInsert as $oi) {
                $itemInsertStmt->execute([
                    $orderId,
                    $oi['product_id'],
                    $oi['product_name'],
                    $oi['product_sku'],
                    $oi['price'],
                    $oi['quantity'],
                    $oi['line_total']
                ]);
            }

            $this->pdo->commit();
            CacheService::invalidateCatalog();

            // Push confirmation email to async queue if email provided
            if (!empty($orderData['customer_email'])) {
                QueueService::push('send_email', [
                    'to'      => $orderData['customer_email'],
                    'subject' => "Order Confirmation #{$orderNumber} — GroCo Grocery Store",
                    'body'    => "<p>Thank you for your order #<strong>{$orderNumber}</strong>. Total: \${$totalAmount}</p>"
                ]);
            }

            return [
                'order_id'     => $orderId,
                'order_number' => $orderNumber,
                'total_amount' => $totalAmount
            ];
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}
