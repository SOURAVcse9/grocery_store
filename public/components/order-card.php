<?php
/**
 * ==========================================================================
 * public/components/order-card.php
 * ==========================================================================
 * Reusable Order Summary Card component.
 * Expects:
 *   - $order (array): order record data.
 *   - $orderItems (array): list of items belonging to this order.
 * ==========================================================================
 */

declare(strict_types=1);

if (!isset($order) || !is_array($order)) {
    return;
}

$orderId = (int) $order['id'];
$orderNumber = $order['order_number'];
$date = date('M d, Y', strtotime($order['created_at']));
$total = (float) $order['total_amount'];
$status = strtolower($order['status']);
$itemsList = $orderItems[$orderId] ?? [];
$itemCount = count($itemsList);

$statusClass = $status;
$statusLabel = ucfirst($status);
?>
<div class="order-summary-card" data-order-id="<?= $orderId ?>">
    <!-- Card Header -->
    <div class="order-card-header">
        <div class="order-header-meta">
            <div class="order-meta-col">
                <span class="meta-label">Order Placed</span>
                <span class="meta-val"><?= $date ?></span>
            </div>
            <div class="order-meta-col">
                <span class="meta-label">Total Amount</span>
                <span class="meta-val"><?= format_price($total) ?></span>
            </div>
            <div class="order-meta-col">
                <span class="meta-label">Payment Method</span>
                <span class="meta-val" style="text-transform:uppercase;"><?= e($order['payment_method']) ?></span>
            </div>
        </div>
        <div class="order-header-number">
            <span class="meta-label">Order # <?= e($orderNumber) ?></span>
            <a href="<?= url_for('order-details.php?id=' . $orderId) ?>" class="order-detail-link">View Details</a>
        </div>
    </div>

    <!-- Card Body -->
    <div class="order-card-body">
        <div class="order-status-row">
            <span class="order-badge <?= $statusClass ?>"><?= $statusLabel ?></span>
            <span class="order-status-desc">
                <?php if ($status === 'pending'): ?>
                    Awaiting verification and processing.
                <?php elseif ($status === 'delivered'): ?>
                    Groceries were delivered safely.
                <?php elseif ($status === 'cancelled'): ?>
                    This order was cancelled.
                <?php else: ?>
                    Your parcel is currently <?= $status ?>.
                <?php endif; ?>
            </span>
        </div>

        <!-- Thumbnail Gallery Preview -->
        <div class="order-gallery-wrapper">
            <div class="order-gallery">
                <?php 
                $shown = 0;
                foreach ($itemsList as $item): 
                    if ($shown >= 4) break;
                    $thumbUrl = image_url($item['thumbnail'], 'products');
                    $shown++;
                ?>
                    <a href="<?= url_for('product.php?slug=' . e($item['slug'])) ?>" class="order-gallery-img" title="<?= e($item['product_name']) ?>">
                        <img src="<?= e($thumbUrl) ?>" alt="<?= e($item['product_name']) ?>">
                        <?php if ($item['quantity'] > 1): ?>
                            <span class="order-gallery-qty-badge"><?= (int)$item['quantity'] ?></span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
                
                <?php if ($itemCount > 4): ?>
                    <div class="order-gallery-more">
                        +<?= $itemCount - 4 ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Action buttons column -->
            <div class="order-card-actions">
                <button type="button" class="btn btn-secondary btn-reorder-products" data-order-id="<?= $orderId ?>" style="font-size:11px; padding:8px 16px; border-radius:var(--radius-pill); border:none;">
                    <i class="fas fa-arrows-rotate"></i> Reorder Items
                </button>
                <a href="<?= url_for('order-details.php?id=' . $orderId) ?>" class="btn btn-primary" style="font-size:11px; padding:8px 20px; border-radius:var(--radius-pill); border:none; text-align:center; display:inline-block;">
                    Track Package
                </a>
            </div>
        </div>
    </div>
</div>
