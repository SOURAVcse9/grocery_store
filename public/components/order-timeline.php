<?php
/**
 * ==========================================================================
 * public/components/order-timeline.php
 * ==========================================================================
 * Reusable order progress tracking timeline.
 * Expects:
 *   - $order (array): order details row.
 *   - $history (array): order status history rows.
 * ==========================================================================
 */

declare(strict_types=1);

if (!isset($order) || !is_array($order)) {
    return;
}

$currentStatus = strtolower($order['status'] ?? 'pending');

// Check if order is cancelled or refunded
$isCancelled = in_array($currentStatus, ['cancelled', 'refunded'], true);

// Standard stages for successful flow
$stages = [
    'pending'    => ['title' => 'Placed', 'icon' => 'fa-receipt', 'desc' => 'Order received'],
    'processing' => ['title' => 'Processing', 'icon' => 'fa-arrows-spin', 'desc' => 'Preparing items'],
    'shipped'    => ['title' => 'Shipped', 'icon' => 'fa-truck-fast', 'desc' => 'In transit'],
    'delivered'  => ['title' => 'Delivered', 'icon' => 'fa-box-open', 'desc' => 'Delivered successfully']
];

// Map history logs by status for exact date tooltips
$historyMap = [];
if (isset($history) && is_array($history)) {
    foreach ($history as $h) {
        $statusKey = strtolower($h['status']);
        // Store first occurrence date
        if (!isset($historyMap[$statusKey])) {
            $historyMap[$statusKey] = $h['created_at'];
        }
    }
}

// Determine active step index
$stageKeys = array_keys($stages);
$activeIdx = 0;

if ($currentStatus === 'delivered') {
    $activeIdx = 3;
} elseif ($currentStatus === 'shipped') {
    $activeIdx = 2;
} elseif (in_array($currentStatus, ['processing', 'packed', 'confirmed'], true)) {
    $activeIdx = 1;
}
?>

<div class="timeline-container">
    <?php if ($isCancelled): ?>
        <!-- Cancelled / Refunded Timeline State -->
        <div class="timeline-cancelled-alert">
            <div class="timeline-cancelled-icon"><i class="fas fa-ban"></i></div>
            <div class="timeline-cancelled-details">
                <h4>Order <?= ucfirst($currentStatus) ?></h4>
                <p>This order was <?= $currentStatus ?> on <?= isset($historyMap[$currentStatus]) ? date('M d, Y \a\t h:i A', strtotime($historyMap[$currentStatus])) : 'unknown date' ?>.</p>
            </div>
        </div>
    <?php else: ?>
        <!-- Progress Steps -->
        <div class="timeline-steps">
            <?php foreach ($stages as $key => $stage): 
                $index = array_search($key, $stageKeys, true);
                $isCompleted = $index <= $activeIdx;
                $isActive = $index === $activeIdx;
                
                $class = $isCompleted ? 'completed' : '';
                if ($isActive) {
                    $class .= ' active';
                }

                // Resolve matching history timestamp
                $timestamp = null;
                if ($key === 'pending') {
                    $timestamp = $historyMap['pending'] ?? $order['created_at'];
                } elseif ($key === 'processing') {
                    $timestamp = $historyMap['processing'] ?? $historyMap['packed'] ?? $historyMap['confirmed'] ?? null;
                } else {
                    $timestamp = $historyMap[$key] ?? null;
                }
            ?>
                <div class="timeline-step <?= $class ?>">
                    <div class="timeline-icon-box">
                        <i class="fas <?= e($stage['icon']) ?>"></i>
                    </div>
                    <div class="timeline-label">
                        <span class="timeline-title"><?= e($stage['title']) ?></span>
                        <span class="timeline-desc"><?= e($stage['desc']) ?></span>
                        <?php if ($timestamp): ?>
                            <span class="timeline-date"><?= date('M d, g:i A', strtotime($timestamp)) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
