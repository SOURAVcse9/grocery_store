<?php
/**
 * ==========================================================================
 * public/components/top-products.php
 * ==========================================================================
 * Frequently purchased products listing component.
 * Expects:
 *   - $topProducts (array): Array containing items purchased
 * ==========================================================================
 */

declare(strict_types=1);

if (!isset($topProducts) || !is_array($topProducts)) {
    return;
}
?>
<div class="analytics-chart-card">
    <h3 class="chart-card-heading"><i class="fas fa-crown" style="color:var(--color-warning); margin-right:4px;"></i> My Frequently Purchased Products</h3>
    <div style="display:flex; flex-direction:column; gap:var(--space-3); padding:4px 0;">
        <?php if (!empty($topProducts)): ?>
            <?php foreach ($topProducts as $idx => $p): ?>
                <div style="display:flex; align-items:center; gap:var(--space-3); border-bottom:1px solid rgba(0,0,0,0.03); padding-bottom:10px; margin-bottom:2px;">
                    <div style="font-size:12px; font-weight:800; color:var(--color-text-faint); width:18px;">#<?= $idx + 1 ?></div>
                    <div style="flex-grow:1; min-width:0;">
                        <h4 style="font-size:12px; font-weight:700; color:var(--color-text); margin:0 0 2px 0; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?= e($p['product_name']) ?></h4>
                        <span style="font-size:9px; color:var(--color-text-faint); font-weight:600; text-transform:uppercase;">Purchased <?= (int) $p['qty_sum'] ?> times</span>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="font-size:var(--fs-xs); color:var(--color-text-faint); text-align:center; margin:var(--space-4) 0;">No purchase records found.</p>
        <?php endif; ?>
    </div>
</div>
