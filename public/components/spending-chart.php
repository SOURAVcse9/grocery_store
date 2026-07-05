<?php
/**
 * ==========================================================================
 * public/components/spending-chart.php
 * ==========================================================================
 * spending Timeline Analysis component.
 * Outputs canvas structure for Chart.js.
 * ==========================================================================
 */

declare(strict_types=1);
?>
<div class="analytics-chart-card">
    <h3 class="chart-card-heading">Monthly Spending Timeline</h3>
    <div class="chart-canvas-wrapper" style="position:relative; height:240px; width:100%;">
        <canvas id="spendingTimelineChart"></canvas>
    </div>
</div>
