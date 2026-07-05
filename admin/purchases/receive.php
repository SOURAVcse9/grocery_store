<?php
/**
 * ==========================================================================
 * admin/purchases/receive.php — Goods Received Note (GRN) Receiver
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/../../public/dbconnect.php';
require_once __DIR__ . '/../middleware/auth_middleware.php';

require_admin_auth();
require_admin_permission('purchases.manage');

$pdo = db();
$poId = (int) input('id', '0', 'get');

if ($poId > 0) {
    try {
        $pdo->beginTransaction();

        // Fetch purchase order details
        $stmtPO = $pdo->prepare("SELECT * FROM purchase_orders WHERE id = :id FOR UPDATE");
        $stmtPO->execute(['id' => $poId]);
        $po = $stmtPO->fetch();

        if ($po && $po['status'] === 'pending') {
            // Update PO status
            $pdo->prepare("UPDATE purchase_orders SET status = 'received' WHERE id = ?")->execute([$poId]);

            // Fetch items
            $stmtItems = $pdo->prepare("SELECT * FROM purchase_order_items WHERE purchase_order_id = ?");
            $stmtItems->execute([$poId]);
            $items = $stmtItems->fetchAll();

            $stmtProd = $pdo->prepare("SELECT stock, name FROM products WHERE id = :id FOR UPDATE");
            $stmtUpdateStock = $pdo->prepare("UPDATE products SET stock = :new_stock WHERE id = :id");
            $stmtLog = $pdo->prepare("
                INSERT INTO inventory_logs (product_id, admin_id, type, quantity, remaining_stock, note, created_at)
                VALUES (:pid, :admin_id, 'stock_in', :qty, :rem_stock, :note, NOW())
            ");

            foreach ($items as $item) {
                $prodId = (int) $item['product_id'];
                $qty = (int) $item['quantity'];

                // Get current stock
                $stmtProd->execute(['id' => $prodId]);
                $prod = $stmtProd->fetch();
                
                if ($prod) {
                    $oldStock = (int) $prod['stock'];
                    $newStock = $oldStock + $qty;

                    // Update product stock
                    $stmtUpdateStock->execute(['new_stock' => $newStock, 'id' => $prodId]);

                    // Log stock movement
                    $stmtLog->execute([
                        'pid'      => $prodId,
                        'admin_id' => current_admin_id(),
                        'qty'      => $qty,
                        'rem_stock' => $newStock,
                        'note'     => "Procurement Goods Received Note (GRN) for PO #{$po['order_number']}"
                    ]);
                }
            }

            $pdo->commit();
            log_admin_activity('purchases.receive', "Processed GRN receiver and stock adjustments for PO: '{$po['order_number']}'");
            flash('purchase_msg', "Purchase Order '{$po['order_number']}' successfully received and inventory updated!", 'success');
        } else {
            $pdo->rollBack();
            flash('purchase_msg', 'Order is not in a pending state or does not exist.', 'error');
        }

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('[admin/purchases/receive] GRN processing failed: ' . $e->getMessage());
        flash('purchase_msg', 'Failed to process stock intake due to database transaction error.', 'error');
    }
}

header('Location: index.php');
exit;
