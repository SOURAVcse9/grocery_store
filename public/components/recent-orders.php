<?php
/**
 * ==========================================================================
 * public/components/recent-orders.php
 * ==========================================================================
 * Dashboard Recent Orders Table component.
 * Expects:
 *   - $recentOrders (array): latest orders list
 * ==========================================================================
 */

declare(strict_types=1);

if (!isset($recentOrders) || !is_array($recentOrders)) {
    return;
}
?>
<div class="analytics-chart-card" style="grid-column: 1 / -1;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-3);">
        <h3 class="chart-card-heading" style="margin-bottom:0;">Recent Orders</h3>
        <a href="<?= url_for('orders.php') ?>" style="font-size:11px; font-weight:800; color:var(--color-primary); text-decoration:none;">View All Orders <i class="fas fa-chevron-right" style="font-size:9px;"></i></a>
    </div>

    <div class="table-responsive" style="overflow-x:auto;">
        <table class="dashboard-table" style="width:100%; border-collapse:collapse; text-align:left; font-size:var(--fs-xs);">
            <thead>
                <tr style="border-bottom:2px solid var(--color-border); color:var(--color-text-muted);">
                    <th style="padding:12px 8px; font-weight:700;">Order No</th>
                    <th style="padding:12px 8px; font-weight:700;">Date</th>
                    <th style="padding:12px 8px; font-weight:700;">Payment Method</th>
                    <th style="padding:12px 8px; font-weight:700;">Status</th>
                    <th style="padding:12px 8px; font-weight:700; text-align:right;">Amount</th>
                    <th style="padding:12px 8px; font-weight:700; text-align:right;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($recentOrders)): ?>
                    <?php foreach ($recentOrders as $order): 
                        $status = strtolower($order['status']);
                        $badgeClass = 'badge-pending';
                        if ($status === 'delivered') $badgeClass = 'badge-success';
                        elseif ($status === 'cancelled') $badgeClass = 'badge-danger';
                        elseif ($status === 'processing' || $status === 'confirmed') $badgeClass = 'badge-info';
                    ?>
                        <tr style="border-bottom:1px solid var(--color-border); transition:background 150ms ease;">
                            <td style="padding:12px 8px; font-family:var(--font-mono, monospace); font-weight:700; color:var(--color-text);"><?= e($order['order_number']) ?></td>
                            <td style="padding:12px 8px; color:var(--color-text-muted);"><?= date('M d, Y', strtotime($order['created_at'])) ?></td>
                            <td style="padding:12px 8px; text-transform:uppercase; color:var(--color-text-muted);"><?= e($order['payment_method']) ?></td>
                            <td style="padding:12px 8px;">
                                <span class="status-badge <?= $badgeClass ?>" style="padding:4px 8px; border-radius:var(--radius-pill); font-size:10px; font-weight:800; text-transform:capitalize;">
                                    <?= e($status) ?>
                                </span>
                            </td>
                            <td style="padding:12px 8px; text-align:right; font-weight:700; color:var(--color-primary);"><?= format_price((float)$order['total_amount']) ?></td>
                            <td style="padding:12px 8px; text-align:right;">
                                <a href="<?= url_for('order-details.php?id=' . $order['id']) ?>" class="btn btn-secondary" style="font-size:10px; padding:6px 12px; border-radius:var(--radius-pill); border:none; text-decoration:none;">View</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" style="padding:24px; text-align:center; color:var(--color-text-faint);">No order history found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
