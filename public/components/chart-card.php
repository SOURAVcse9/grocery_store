<?php
/**
 * ==========================================================================
 * public/components/chart-card.php
 * ==========================================================================
 * Reusable Chart.js Canvas Container Card widget.
 * Expects:
 *   - $chartId (string): Unique DOM ID for canvas element
 *   - $chartTitle (string): Header title of the chart card
 * ==========================================================================
 */

declare(strict_types=1);

$chartId = $chartId ?? 'chartCanvas';
$chartTitle = $chartTitle ?? 'Chart Visualization';
?>
<div class="analytics-chart-card">
    <h3 class="chart-card-heading"><?= e($chartTitle) ?></h3>
    <div class="chart-canvas-wrapper">
        <canvas id="<?= e($chartId) ?>"></canvas>
    </div>
</div>
