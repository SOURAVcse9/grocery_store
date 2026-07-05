<?php
/**
 * ==========================================================================
 * public/components/order-summary.php
 * ==========================================================================
 * Detailed Order status breakdown dashboard component.
 * Expects:
 *   - $statusBreakdown (array): Array containing status metrics
 * ==========================================================================
 */

declare(strict_types=1);

if (!isset($statusBreakdown) || !is_array($statusBreakdown)) {
    return;
}

$totalOrders = (int) array_sum(array_column($statusBreakdown, 'count'));
?>
<div class="analytics-chart-card">
    <h3 class="chart-card-heading">Order Status Timeline Distribution</h3>
    <div style="display:flex; flex-direction:column; gap:var(--space-3); padding:4px 0;">
        <?php if ($totalOrders > 0): ?>
            <?php foreach ($statusBreakdown as $row): 
                $status = strtolower($row['status']);
                $count = (int) $row['count'];
                $pct = $totalOrders > 0 ? round(($count / $totalOrders) * 100) : 0;
                
                // Color mapping
                $color = '#6c757d'; // gray default
                if ($status === 'delivered') $color = '#2b8a3e'; // green
                elseif ($status === 'pending') $color = '#e67e22'; // orange
                elseif ($status === 'processing' || $status === 'confirmed') $color = '#228be6'; // blue
                elseif ($status === 'cancelled') $color = '#fa5252'; // red
            ?>
                <div>
                    <div style="display:flex; justify-content:space-between; font-size:var(--fs-xs); font-weight:700; margin-bottom:4px;">
                        <span style="text-transform:capitalize;"><i class="fas fa-circle" style="color:<?= $color ?>; font-size:8px; margin-right:6px; vertical-align:middle;"></i> <?= e($status) ?></span>
                        <span style="color:var(--color-text-muted);"><?= $count ?> (<?= $pct ?>%)</span>
                    </div>
                    <div class="analytics-progress-bar-bg" style="background:#e9ecef; height:6px; border-radius:var(--radius-pill); overflow:hidden;">
                        <div class="analytics-progress-bar-fill" style="background:<?= $color ?>; width:<?= $pct ?>%; height:100%; border-radius:var(--radius-pill);"></div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="font-size:var(--fs-xs); color:var(--color-text-faint); text-align:center; margin:var(--space-4) 0;">No order history found.</p>
        <?php endif; ?>
    </div>
</div>
