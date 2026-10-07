<?php
/**
 * admin/includes/dashboard_ops_panel.php — "Operations" panel for the dashboard.
 * Real data only. Date range: ?range=today|yesterday|7d|30d|custom&from=YYYY-MM-DD&to=YYYY-MM-DD
 * Included by admin/index.php (needs $pdo, e(), has_admin_permission()).
 */

declare(strict_types=1);

require_once __DIR__ . '/pos_lib.php';

$opsRange = (string) input('range', 'today', 'get');
$today = new DateTimeImmutable('today');
switch ($opsRange) {
    case 'yesterday': $opsFrom = $today->modify('-1 day'); $opsTo = $today; break;
    case '7d':        $opsFrom = $today->modify('-6 days'); $opsTo = $today->modify('+1 day'); break;
    case '30d':       $opsFrom = $today->modify('-29 days'); $opsTo = $today->modify('+1 day'); break;
    case 'custom':
        $f = DateTimeImmutable::createFromFormat('!Y-m-d', (string) input('from', '', 'get'));
        $t = DateTimeImmutable::createFromFormat('!Y-m-d', (string) input('to', '', 'get'));
        if ($f && $t && $f <= $t && $t->diff($f)->days <= 366) {
            $opsFrom = $f; $opsTo = $t->modify('+1 day');
            break;
        }
        $opsRange = 'today'; // fall through to default on invalid input
    default:          $opsRange = 'today'; $opsFrom = $today; $opsTo = $today->modify('+1 day');
}
$opsP = [':f' => $opsFrom->format('Y-m-d H:i:s'), ':t' => $opsTo->format('Y-m-d H:i:s')];
$ops = ['pos_sales' => 0.0, 'pos_count' => 0, 'online_sales' => 0.0, 'online_count' => 0, 'discounts' => 0.0, 'voids' => 0, 'void_total' => 0.0,
        'refunds' => 0.0, 'refund_count' => 0, 'open_shifts' => [], 'pay' => ['cash' => 0.0, 'card' => 0.0, 'bkash' => 0.0, 'bank' => 0.0, 'wallet' => 0.0],
        'top' => [], 'low' => 0, 'out' => 0, 'pending_delivery' => 0, 'cashiers' => []];
$opsError = null;
try {
    $st = $pdo->prepare("SELECT order_number, total_amount, discount_amount, status, note FROM orders WHERE created_at >= :f AND created_at < :t");
    $st->execute($opsP);
    foreach ($st->fetchAll() as $o) {
        $isPos = strpos((string) $o['order_number'], 'POS-') === 0;
        if ($o['status'] === 'cancelled') {
            if ($isPos) { $ops['voids']++; $ops['void_total'] += (float) $o['total_amount']; }
            continue;
        }
        if ($isPos) {
            $ops['pos_sales'] += (float) $o['total_amount'];
            $ops['pos_count']++;
            $ops['discounts'] += (float) $o['discount_amount'];
            $mk = pos_parse_marker($o['note']);
            if ($mk) {
                foreach (array_keys($ops['pay']) as $k) { $ops['pay'][$k] += (float) ($mk[$k] ?? 0); }
                $a = (int) ($mk['admin'] ?? 0);
                if ($a > 0) {
                    $ops['cashiers'][$a]['sales'] = ($ops['cashiers'][$a]['sales'] ?? 0.0) + (float) $o['total_amount'];
                    $ops['cashiers'][$a]['count'] = ($ops['cashiers'][$a]['count'] ?? 0) + 1;
                }
            }
        } else {
            $ops['online_sales'] += (float) $o['total_amount'];
            $ops['online_count']++;
        }
    }
    if ($ops['cashiers']) {
        $ids = array_keys($ops['cashiers']);
        $names = $pdo->query('SELECT id, COALESCE(NULLIF(full_name, \'\'), username) AS n FROM admins WHERE id IN (' . implode(',', array_map('intval', $ids)) . ')')->fetchAll(PDO::FETCH_KEY_PAIR);
        foreach ($ops['cashiers'] as $id => &$row) { $row['name'] = $names[$id] ?? ('#' . $id); }
        unset($row);
        uasort($ops['cashiers'], static fn($a, $b) => $b['sales'] <=> $a['sales']);
    }
    if (pos_table_exists($pdo, 'pos_returns')) {
        $st = $pdo->prepare('SELECT COALESCE(SUM(refund_amount),0), COUNT(*) FROM pos_returns WHERE created_at >= :f AND created_at < :t');
        $st->execute($opsP);
        [$ops['refunds'], $ops['refund_count']] = array_map('floatval', $st->fetch(PDO::FETCH_NUM));
    }
    $ops['open_shifts'] = $pdo->query("SELECT s.id, s.opening_cash, s.start_time, COALESCE(NULLIF(a.full_name,''), a.username) AS cashier FROM pos_shifts s LEFT JOIN admins a ON a.id = s.admin_id WHERE s.status = 'open' ORDER BY s.id DESC LIMIT 10")->fetchAll();
    $st = $pdo->prepare("SELECT oi.product_id, oi.product_name, SUM(oi.quantity) AS qty, SUM(oi.line_total) AS revenue
                         FROM order_items oi JOIN orders o ON o.id = oi.order_id
                         WHERE o.created_at >= :f AND o.created_at < :t AND o.status <> 'cancelled'
                         GROUP BY oi.product_id, oi.product_name ORDER BY qty DESC LIMIT 8");
    $st->execute($opsP);
    $ops['top'] = $st->fetchAll();
    // Stock alerts use each product's OWN min_stock threshold (the old widget hardcoded 5)
    $ops['out'] = (int) $pdo->query('SELECT COUNT(*) FROM products WHERE is_active = 1 AND deleted_at IS NULL AND stock <= 0')->fetchColumn();
    $ops['low'] = (int) $pdo->query('SELECT COUNT(*) FROM products WHERE is_active = 1 AND deleted_at IS NULL AND stock > 0 AND stock <= min_stock')->fetchColumn();
    $ops['pending_delivery'] = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE order_number NOT LIKE 'POS-%' AND status IN ('confirmed','processing','shipped','out_for_delivery')")->fetchColumn();
} catch (Throwable $ex) {
    error_log('[admin/dashboard_ops_panel] ' . $ex->getMessage());
    $opsError = 'Some operations figures could not be loaded.';
}
$opsLabel = ['today' => 'Today', 'yesterday' => 'Yesterday', '7d' => 'Last 7 days', '30d' => 'Last 30 days', 'custom' => 'Custom'];
$opsCard = 'background:var(--color-surface); border:1px solid var(--color-border); border-radius:var(--radius-md); padding:14px;';
$tk = static fn($v) => '৳' . number_format((float) $v, 2);
?>
<section aria-label="Operations overview" style="margin:var(--space-5) 0;">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:12px;">
        <h2 style="font-size:var(--fs-lg); font-weight:800; margin:0; color:var(--color-text);">Operations &mdash; <?= e($opsLabel[$opsRange]) ?></h2>
        <form method="get" style="display:flex; gap:6px; align-items:center; flex-wrap:wrap;">
            <?php foreach (['today', 'yesterday', '7d', '30d'] as $r): ?>
                <a href="?range=<?= $r ?>" class="btn <?= $opsRange === $r ? 'btn-primary' : 'btn-secondary' ?>" style="padding:6px 12px; font-size:12px; border-radius:var(--radius-pill);"><?= e($opsLabel[$r]) ?></a>
            <?php endforeach; ?>
            <input type="hidden" name="range" value="custom">
            <label class="sr-only" for="opsFrom" style="position:absolute; left:-9999px;">From date</label>
            <input id="opsFrom" type="date" name="from" value="<?= e($opsFrom->format('Y-m-d')) ?>" style="padding:5px; border:1px solid var(--color-border); border-radius:6px; background:var(--color-surface); color:var(--color-text);">
            <label class="sr-only" for="opsTo" style="position:absolute; left:-9999px;">To date</label>
            <input id="opsTo" type="date" name="to" value="<?= e($opsTo->modify('-1 day')->format('Y-m-d')) ?>" style="padding:5px; border:1px solid var(--color-border); border-radius:6px; background:var(--color-surface); color:var(--color-text);">
            <button type="submit" class="btn btn-secondary" style="padding:6px 12px; font-size:12px; border-radius:var(--radius-pill);">Apply</button>
        </form>
    </div>
    <?php if ($opsError): ?><p role="alert" style="color:#e03131; font-size:12px;"><?= e($opsError) ?></p><?php endif; ?>

    <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(170px,1fr)); gap:12px;">
        <div style="<?= $opsCard ?>"><div style="font-size:11px; color:var(--color-text-muted);">POS sales (<?= (int) $ops['pos_count'] ?>)</div><strong style="font-size:18px;"><?= $tk($ops['pos_sales']) ?></strong></div>
        <div style="<?= $opsCard ?>"><div style="font-size:11px; color:var(--color-text-muted);">Online sales (<?= (int) $ops['online_count'] ?>)</div><strong style="font-size:18px;"><?= $tk($ops['online_sales']) ?></strong></div>
        <div style="<?= $opsCard ?>"><div style="font-size:11px; color:var(--color-text-muted);">Discounts given (POS)</div><strong style="font-size:18px;"><?= $tk($ops['discounts']) ?></strong></div>
        <div style="<?= $opsCard ?>"><div style="font-size:11px; color:var(--color-text-muted);">Refunds (<?= (int) $ops['refund_count'] ?>)</div><strong style="font-size:18px; color:#e03131;"><?= $tk($ops['refunds']) ?></strong></div>
        <div style="<?= $opsCard ?>"><div style="font-size:11px; color:var(--color-text-muted);">Voided sales (<?= (int) $ops['voids'] ?>)</div><strong style="font-size:18px;"><?= $tk($ops['void_total']) ?></strong></div>
        <div style="<?= $opsCard ?>"><div style="font-size:11px; color:var(--color-text-muted);">Pending delivery</div><strong style="font-size:18px;"><?= (int) $ops['pending_delivery'] ?></strong></div>
        <a href="<?= e(url_for('admin/inventory/index.php')) ?>" style="<?= $opsCard ?> text-decoration:none; color:inherit;"><div style="font-size:11px; color:var(--color-text-muted);">Low stock (own min level)</div><strong style="font-size:18px; color:#f08c00;"><?= (int) $ops['low'] ?></strong></a>
        <a href="<?= e(url_for('admin/inventory/index.php')) ?>" style="<?= $opsCard ?> text-decoration:none; color:inherit;"><div style="font-size:11px; color:var(--color-text-muted);">Out of stock</div><strong style="font-size:18px; color:#e03131;"><?= (int) $ops['out'] ?></strong></a>
    </div>

    <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(260px,1fr)); gap:12px; margin-top:12px;">
        <div style="<?= $opsCard ?>">
            <h3 style="font-size:13px; margin:0 0 8px;">POS payment breakdown</h3>
            <?php foreach (['cash' => 'Cash', 'card' => 'Card', 'bkash' => 'bKash / Mobile', 'bank' => 'Bank', 'wallet' => 'Wallet'] as $k => $lbl): ?>
                <div style="display:flex; justify-content:space-between; font-size:12px; padding:2px 0;"><span><?= e($lbl) ?></span><strong><?= $tk($ops['pay'][$k]) ?></strong></div>
            <?php endforeach; ?>
            <p style="font-size:10px; color:var(--color-text-faint); margin:6px 0 0;">Sales made before this upgrade carry no split data and are not included here.</p>
        </div>
        <div style="<?= $opsCard ?>">
            <h3 style="font-size:13px; margin:0 0 8px;">Open POS shifts (<?= count($ops['open_shifts']) ?>)</h3>
            <?php if (!$ops['open_shifts']): ?><p style="font-size:12px; color:var(--color-text-faint); margin:0;">No shift is open.</p><?php endif; ?>
            <?php foreach ($ops['open_shifts'] as $sh): ?>
                <div style="font-size:12px; padding:2px 0;">#<?= (int) $sh['id'] ?> &bull; <?= e($sh['cashier'] ?? '') ?> &bull; since <?= e(date('M d H:i', strtotime((string) $sh['start_time']))) ?> &bull; float <?= $tk($sh['opening_cash']) ?></div>
            <?php endforeach; ?>
        </div>
        <div style="<?= $opsCard ?>">
            <h3 style="font-size:13px; margin:0 0 8px;">Cashier performance</h3>
            <?php if (!$ops['cashiers']): ?><p style="font-size:12px; color:var(--color-text-faint); margin:0;">No POS sales in this period.</p><?php endif; ?>
            <?php foreach ($ops['cashiers'] as $c): ?>
                <div style="display:flex; justify-content:space-between; font-size:12px; padding:2px 0;"><span><?= e($c['name']) ?> (<?= (int) $c['count'] ?>)</span><strong><?= $tk($c['sales']) ?></strong></div>
            <?php endforeach; ?>
        </div>
        <div style="<?= $opsCard ?>">
            <h3 style="font-size:13px; margin:0 0 8px;">Best-selling products</h3>
            <?php if (!$ops['top']): ?><p style="font-size:12px; color:var(--color-text-faint); margin:0;">No sales in this period.</p><?php endif; ?>
            <?php foreach ($ops['top'] as $tp): ?>
                <div style="display:flex; justify-content:space-between; font-size:12px; padding:2px 0; gap:8px;"><span style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"><?= e($tp['product_name']) ?></span><strong><?= e(pos_fmt_qty((float) $tp['qty'])) ?></strong></div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
