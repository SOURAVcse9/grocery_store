<?php
/**
 * ==========================================================================
 * public/components/stat-card.php
 * ==========================================================================
 * Reusable Numeric KPI Stats Card widget.
 * Expects:
 *   - $icon (string): FontAwesome icon class
 *   - $title (string): Short label (e.g. Total Orders)
 *   - $value (string/float): Large numerical figure
 *   - $context (string): Small helper note underneath
 *   - $color (string): Highlight accent class (e.g., primary, success, danger)
 * ==========================================================================
 */

declare(strict_types=1);

$icon = $icon ?? 'fa-circle-info';
$title = $title ?? 'Label';
$value = $value ?? '0';
$context = $context ?? '';
$color = $color ?? 'primary';
?>
<div class="analytics-stat-card border-<?= $color ?>">
    <div class="stat-card-body">
        <div class="stat-card-details">
            <h4 class="stat-card-title"><?= e($title) ?></h4>
            <div class="stat-card-value"><?= e($value) ?></div>
            <?php if (!empty($context)): ?>
                <span class="stat-card-context"><?= e($context) ?></span>
            <?php endif; ?>
        </div>
        <div class="stat-card-icon-wrapper accent-<?= $color ?>">
            <i class="fas <?= $icon ?>"></i>
        </div>
    </div>
</div>
