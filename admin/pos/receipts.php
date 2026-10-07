<?php
/**
 * ==========================================================================
 * admin/pos/receipts.php — POS receipt printing (58mm / 80mm / A4) + invoice directory
 * ==========================================================================
 *  ?id=ORDER_ID[&w=58|80|a4]   print one receipt (reprinting NEVER creates a sale)
 *  (no id)                     searchable invoice directory
 *
 *  - Store name / address / phone / currency come from the settings table (nothing hardcoded)
 *  - First print is logged as pos.receipt_print; every later print is logged as pos.reprint
 *    and the paper is stamped "DUPLICATE COPY"
 *  - Only POS counter sales are printable here; cashiers may reprint their OWN sales,
 *    managers (pos.manage / pos.override) may reprint any
 */

declare(strict_types=1);

require_once __DIR__ . '/../../public/dbconnect.php';
require_once __DIR__ . '/../middleware/auth_middleware.php';
require_once __DIR__ . '/../includes/auth_helpers.php';
require_once __DIR__ . '/../includes/pos_lib.php';

require_admin_auth();
require_admin_permission('pos.access');

$pdo = db();
$orderId = (int) input('id', '0', 'get');

if ($orderId > 0) {
    $adminId = (int) current_admin_id();
    $isManager = has_admin_permission('pos.manage') || has_admin_permission('pos.override');
    try {
        $stmt = $pdo->prepare("SELECT o.*, u.full_name AS customer_name, u.phone AS customer_phone FROM orders o LEFT JOIN users u ON u.id = o.user_id WHERE o.id = ? AND o.order_number LIKE 'POS-%' LIMIT 1");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();
        if (!$order) {
            http_response_code(404);
            echo 'POS receipt not found.';
            exit;
        }
        $mk = pos_parse_marker($order['note']) ?? [];
        if (!$isManager && isset($mk['admin']) && (int) $mk['admin'] !== $adminId) {
            log_admin_activity('permission_denied', "Attempted to print POS receipt {$order['order_number']} of another cashier");
            http_response_code(403);
            echo 'You can only print receipts for your own sales.';
            exit;
        }

        $stmtItems = $pdo->prepare('SELECT oi.*, COALESCE(oi.product_name, p.name, \'Product\') AS product_name FROM order_items oi LEFT JOIN products p ON p.id = oi.product_id WHERE oi.order_id = ? ORDER BY oi.id ASC');
        $stmtItems->execute([$orderId]);
        $items = $stmtItems->fetchAll();

        $cashierName = '';
        if (!empty($mk['admin'])) {
            $c = $pdo->prepare('SELECT COALESCE(NULLIF(full_name, \'\'), username) FROM admins WHERE id = ?');
            $c->execute([(int) $mk['admin']]);
            $cashierName = (string) $c->fetchColumn();
        }

        // Was this receipt printed before? (activity log is the audit trail; no schema change needed)
        $prev = $pdo->prepare("SELECT COUNT(*) FROM admin_activity_logs WHERE activity_type IN ('pos.receipt_print','pos.reprint') AND description LIKE ?");
        $prev->execute(['%' . $order['order_number'] . '%']);
        $isCopy = ((int) $prev->fetchColumn() > 0);
        log_admin_activity($isCopy ? 'pos.reprint' : 'pos.receipt_print', ($isCopy ? 'Reprinted' : 'Printed') . " receipt {$order['order_number']}");
    } catch (Throwable $e) {
        error_log('[admin/pos/receipts] load failed: ' . $e->getMessage());
        http_response_code(500);
        echo 'Could not load the receipt.';
        exit;
    }

    $w = strtolower((string) input('w', '80', 'get'));
    if (!in_array($w, ['58', '80', 'a4'], true)) {
        $w = '80';
    }
    $store = [
        'name'    => pos_setting($pdo, 'pos_store_name', pos_setting($pdo, 'site_name', 'GroCo Supermarket & Department Store')),
        'tagline' => pos_setting($pdo, 'pos_store_tagline', 'Fresh Supermarket • Apparel & Fashion • Daily Essentials'),
        'addr'    => pos_setting($pdo, 'site_address', 'Flat 4A, House 12, Road 4, Banani, Dhaka, Bangladesh'),
        'phone'   => pos_setting($pdo, 'site_phone', '+880 1712 345678'),
        'vat_bin' => pos_setting($pdo, 'pos_vat_bin', '004189214-0101'),
        'policy'  => pos_setting($pdo, 'pos_receipt_footer', 'Exchange within 7 days for Clothing & Dry goods with receipt and tag intact. Fresh produce, fish and dairy are non-exchangeable.'),
    ];
    $cur = pos_setting($pdo, 'site_currency_symbol', '৳');
    $money = static fn($v) => $cur . number_format((float) $v, 2);

    $gross    = (float) $order['subtotal'];
    $discount = (float) $order['discount_amount'];
    $total    = (float) $order['total_amount'];
    $vat      = max(0.0, round($total - ($gross - $discount), 2));
    $pays = [];
    if ($mk) {
        foreach (['cash' => 'Cash', 'card' => 'Card', 'bkash' => 'bKash / Mobile', 'bank' => 'Bank transfer', 'wallet' => 'Wallet'] as $k => $label) {
            if ((float) ($mk[$k] ?? 0) > 0) {
                $pays[$label] = (float) $mk[$k];
            }
        }
    } else {
        $pays[ucfirst(str_replace('_', ' ', (string) $order['payment_method']))] = $total; // legacy sale without split data
    }
    $change = (float) ($mk['change'] ?? 0);
    $paidTotal = array_sum($pays) + $change;
    $isVoid = ($order['status'] === 'cancelled');
    $bodyWidth = $w === 'a4' ? '190mm' : ($w === '58' ? '48mm' : '72mm');
    $font = $w === 'a4' ? '13px' : ($w === '58' ? '10px' : '11px');

    $itemCount = count($items);
    $totalQty = 0.0;
    foreach ($items as $it) {
        $totalQty += (float) $it['quantity'];
    }

    // Customer loyalty & member info
    $isMember = false;
    $memberPointsEarned = 0;
    $memberPointsBalance = 0;
    $walkinId = pos_walkin_customer_id($pdo);
    if ((int)$order['user_id'] > 0 && (int)$order['user_id'] !== $walkinId) {
        $isMember = true;
        $memberPointsEarned = (int) floor($total / 100);
        try {
            $userSt = $pdo->prepare('SELECT reward_points FROM users WHERE id = ? LIMIT 1');
            $userSt->execute([(int)$order['user_id']]);
            $memberPointsBalance = (int) $userSt->fetchColumn();
        } catch (Throwable $e) {
            $memberPointsBalance = 0;
        }
    }

    if (!function_exists('pos_barcode_128_svg')) {
        function pos_barcode_128_svg(string $code, int $height = 36): string {
            static $patterns = [
                '212222', '222122', '222221', '121223', '121322', '131222', '122213', '122312', '132212', '221213',
                '221312', '231212', '112232', '122132', '122231', '113222', '123122', '123221', '223211', '221132',
                '221231', '213212', '223112', '312131', '311222', '321122', '321221', '312212', '322112', '322211',
                '212123', '212321', '232121', '111323', '131123', '131321', '112313', '132113', '132311', '211313',
                '231113', '231311', '112133', '112331', '132131', '113123', '113321', '133121', '313121', '211331',
                '231131', '213113', '213311', '213131', '311123', '311321', '331121', '312113', '312311', '332111',
                '314111', '221411', '431111', '111224', '111422', '121124', '121421', '141122', '141221', '112214',
                '112412', '122114', '122411', '142112', '142211', '241211', '221114', '413111', '241112', '134111',
                '111242', '121142', '121241', '114212', '124112', '124211', '411212', '421112', '421211', '212141',
                '214121', '412121', '111143', '111341', '131141', '114113', '114311', '411113', '411311', '113141',
                '114131', '311141', '411131', '211412', '211214', '211232', '2331112'
            ];
            $code = trim($code);
            if ($code === '') return '';
            $values = [104];
            $checkSum = 104;
            for ($i = 0, $len = strlen($code); $i < $len; $i++) {
                $val = ord($code[$i]) - 32;
                if ($val < 0 || $val > 95) $val = 0;
                $values[] = $val;
                $checkSum += $val * ($i + 1);
            }
            $values[] = $checkSum % 103;
            $values[] = 106;
            $bars = '';
            foreach ($values as $val) {
                $pat = $patterns[$val] ?? '';
                for ($j = 0, $plen = strlen($pat); $j < $plen; $j++) {
                    $bars .= str_repeat(($j % 2 === 0) ? '1' : '0', (int)$pat[$j]);
                }
            }
            $totalWidth = strlen($bars);
            $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $totalWidth . ' ' . $height . '" preserveAspectRatio="none" style="width:100%; height:' . $height . 'px; display:block;">';
            for ($x = 0; $x < $totalWidth; $x++) {
                if ($bars[$x] === '1') {
                    $svg .= '<rect x="' . $x . '" y="0" width="1" height="' . $height . '" fill="#000000" />';
                }
            }
            $svg .= '</svg>';
            return $svg;
        }
    }

    header('Cache-Control: no-store');
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Receipt <?= e($order['order_number']) ?></title>
    <style>
        @page { margin: <?= $w === 'a4' ? '12mm' : '2mm' ?>; }
        body { font-family: <?= $w === 'a4' ? "Arial, Helvetica, sans-serif" : "'Courier New', Courier, monospace" ?>; font-size: <?= $font ?>; color:#000; margin:0 auto; padding:6px; width:<?= $bodyWidth ?>; background:#fff; line-height:1.25; }
        .c { text-align:center; } .r { text-align:right; }
        h1 { font-size: <?= $w === 'a4' ? '22px' : '15px' ?>; margin:0 0 2px; text-transform:uppercase; letter-spacing:0.5px; }
        p { margin:2px 0; }
        .tagline { font-size: <?= $w === '58' ? '8px' : '10px' ?>; font-style:italic; margin-bottom:3px; }
        .line { border-top:1px dashed #000; margin:5px 0; }
        .double-line { border-top:1px double #000; border-bottom:1px solid #000; height:2px; margin:5px 0; }
        table { width:100%; border-collapse:collapse; }
        th { text-align:left; border-bottom:1px solid #000; font-size:<?= $font ?>; padding:2px 0; }
        td { vertical-align:top; padding:2px 0; }
        .row { display:flex; justify-content:space-between; margin:1px 0; }
        .strong { font-weight:700; }
        .stamp { border:2px solid #000; display:inline-block; padding:2px 8px; margin:4px 0; font-weight:700; letter-spacing:1px; }
        .barcode-box { max-width: <?= $w === 'a4' ? '260px' : ($w === '58' ? '150px' : '190px') ?>; margin:4px auto; text-align:center; }
        @media screen { .noprint { margin-bottom:8px; } }
        @media print { .noprint { display:none; } }
    </style>
</head>
<body onload="window.print();">
    <div class="noprint c">
        Paper:
        <?php foreach (['58' => '58mm', '80' => '80mm', 'a4' => 'A4'] as $k => $lbl): ?>
            <a href="?id=<?= (int) $orderId ?>&amp;w=<?= $k ?>"<?= $k === $w ? ' style="font-weight:700"' : '' ?>><?= $lbl ?></a>&nbsp;
        <?php endforeach; ?>
        <button type="button" onclick="window.print()">Print</button>
    </div>

    <div class="c">
        <h1><?= e($store['name']) ?></h1>
        <?php if ($store['tagline'] !== ''): ?><p class="tagline"><?= e($store['tagline']) ?></p><?php endif; ?>
        <?php if ($store['addr'] !== ''): ?><p style="font-size:<?= $w === '58' ? '8px' : '10px' ?>;"><?= e($store['addr']) ?></p><?php endif; ?>
        <?php if ($store['phone'] !== ''): ?><p style="font-size:<?= $w === '58' ? '8px' : '10px' ?>;">Helpline: <?= e($store['phone']) ?></p><?php endif; ?>
        <?php if ($store['vat_bin'] !== ''): ?><p style="font-weight:700; font-size:<?= $w === '58' ? '9px' : '11px' ?>;">VAT Reg / BIN: <?= e($store['vat_bin']) ?> (Mushak-6.3)</p><?php endif; ?>
        <?php if ($isCopy): ?><div class="stamp">DUPLICATE COPY</div><?php endif; ?>
        <?php if ($isVoid): ?><div class="stamp">VOIDED SALE</div><?php endif; ?>
    </div>
    <div class="line"></div>
    <div class="row"><span>Invoice #:</span><strong style="letter-spacing:0.5px;"><?= e($order['order_number']) ?></strong></div>
    <div class="row"><span>Date &amp; Time:</span><span><?= e(date('d-M-Y h:i A', strtotime((string) $order['created_at']))) ?></span></div>
    <?php if ($cashierName !== ''): ?><div class="row"><span>Cashier:</span><span><?= e($cashierName) ?></span></div><?php endif; ?>
    <div class="row"><span>Counter / Station:</span><span><?= !empty($mk['shift']) ? 'Register 01 (Shift #' . (int)$mk['shift'] . ')' : 'Register 01' ?></span></div>
    <div class="row"><span>Customer:</span><span><?= e($order['customer_name'] ?? 'Walk-in Customer') ?> <?= !empty($order['customer_phone']) && $order['customer_phone'] !== '00000000000' ? '(' . e($order['customer_phone']) . ')' : '' ?></span></div>
    <div class="line"></div>

    <table>
        <thead><tr><th>Item</th><th class="r">Qty</th><th class="r">Rate</th><th class="r">Amount</th></tr></thead>
        <tbody>
        <?php foreach ($items as $row): ?>
            <tr>
                <td><?= e($row['product_name']) ?></td>
                <td class="r"><?= e(pos_fmt_qty((float) $row['quantity'])) ?></td>
                <td class="r"><?= e(number_format((float) $row['price'], 2)) ?></td>
                <td class="r"><?= e(number_format((float) $row['line_total'], 2)) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <div class="line"></div>

    <div class="row" style="font-size:<?= $w === '58' ? '9px' : '10px' ?>;">
        <span>Items: <strong><?= $itemCount ?></strong> | Total Qty: <strong><?= pos_fmt_qty($totalQty) ?></strong></span>
        <span>Subtotal: <?= e($money($gross)) ?></span>
    </div>
    <?php if ($discount > 0): ?><div class="row"><span>Discount Applied:</span><span>-<?= e($money($discount)) ?></span></div><?php endif; ?>
    <?php if ($vat > 0): ?><div class="row"><span>VAT / Tax (Included):</span><span><?= e($money($vat)) ?></span></div><?php endif; ?>
    <div class="line"></div>
    <div class="row strong" style="font-size:<?= $w === 'a4' ? '16px' : ($w === '58' ? '12px' : '14px') ?>;">
        <span>NET PAYABLE</span><span><?= e($money($total)) ?></span>
    </div>
    <div class="line"></div>
    <?php foreach ($pays as $label => $amt): ?>
        <div class="row"><span><?= e($label) ?>:</span><span><?= e($money($amt)) ?></span></div>
    <?php endforeach; ?>
    <div class="row"><span>Tender Received:</span><span><?= e($money($paidTotal)) ?></span></div>
    <div class="row strong"><span>Change Returned:</span><span><?= e($money($change)) ?></span></div>

    <?php if ($isMember): ?>
        <div class="line"></div>
        <div class="row" style="font-size:<?= $w === '58' ? '9px' : '10px' ?>;"><span>Points Earned Today:</span><span>+<?= $memberPointsEarned ?> pts</span></div>
        <div class="row" style="font-size:<?= $w === '58' ? '9px' : '10px' ?>;"><span>Loyalty Points Balance:</span><strong><?= $memberPointsBalance ?> pts</strong></div>
    <?php endif; ?>

    <div class="line"></div>
    <div class="barcode-box">
        <?= pos_barcode_128_svg($order['order_number'], 32) ?>
        <div style="font-size:9px; letter-spacing:1px; margin-top:2px; font-weight:700;"><?= e($order['order_number']) ?></div>
    </div>

    <div class="c" style="font-size:<?= $w === '58' ? '8px' : '9.5px' ?>; margin-top:4px; line-height:1.3; color:#222;">
        <p><?= e($store['policy']) ?></p>
        <p style="font-weight:700; margin-top:4px;">Thank you for shopping at <?= e($store['name']) ?>!</p>
    </div>
</body>
</html>
    <?php
    exit;
}

// OTHERWISE RENDER DIRECTORY LIST VIEW IN LAYOUT
$pageTitle = 'POS Invoices — GroCo Admin';
require_once __DIR__ . '/../layouts/dashboard_layout.php';
$q = trim(input('q', '', 'get'));
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-5);">
    <div>
        <h1 style="font-size:var(--fs-xl); font-weight:800; color:var(--color-text); margin:0;">POS Invoices Directory</h1>
        <p style="font-size:var(--fs-sm); color:var(--color-text-muted); margin:4px 0 0 0;">Search a sale and reprint it on 58mm, 80mm or A4 paper. Reprinting never creates a sale and is logged.</p>
    </div>
    <a href="index.php" class="btn btn-secondary" style="border-radius:var(--radius-pill); font-weight:700;"><i class="fas fa-arrow-left"></i> POS Terminal</a>
</div>

<form method="get" style="display:flex; gap:8px; margin-bottom:var(--space-4);">
    <label for="rcptSearch" class="sr-only" style="position:absolute; left:-9999px;">Search receipt number</label>
    <input id="rcptSearch" type="search" name="q" value="<?= e($q) ?>" placeholder="Search receipt no. e.g. POS-20261007" style="flex:1; padding:10px 14px; border:1px solid var(--color-border); border-radius:var(--radius-sm); background:var(--color-surface); color:var(--color-text);">
    <button type="submit" class="btn btn-primary" style="border:none; border-radius:var(--radius-pill); font-weight:700;"><i class="fas fa-magnifying-glass"></i> Search</button>
</form>

<div class="dashboard-card" style="padding:0; overflow:hidden;">
    <div class="admin-table-wrapper" style="border:none;">
        <table class="admin-data-table" style="font-size:13px;">
            <thead>
                <tr>
                    <th style="padding:16px 20px;">Order Number</th>
                    <th style="padding:16px 20px; text-align:right; width:150px;">Order Subtotal</th>
                    <th style="padding:16px 20px; text-align:right; width:150px;">Discount</th>
                    <th style="padding:16px 20px; text-align:right; width:180px;">Total Due</th>
                    <th style="padding:16px 20px; width:220px;">Created Date</th>
                    <th style="padding:16px 20px; width:150px; text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                try {
                    $sqlList = "SELECT * FROM orders WHERE order_number LIKE 'POS-%'" . ($q !== '' ? ' AND order_number LIKE :q' : '') . ' ORDER BY created_at DESC LIMIT 50';
                    $stList = $pdo->prepare($sqlList);
                    $stList->execute($q !== '' ? [':q' => '%' . addcslashes($q, '%_\\') . '%'] : []);
                    $orders = $stList->fetchAll();
                } catch (PDOException $e) {
                    $orders = [];
                }
                if (!empty($orders)):
                    foreach ($orders as $row):
                ?>
                    <tr style="border-bottom:1px solid var(--color-border); vertical-align:middle;">
                        <td style="padding:12px 20px;"><strong><?= e($row['order_number']) ?></strong></td>
                        <td style="padding:12px 20px; text-align:right; color:var(--color-text-muted);">৳<?= number_format((float)$row['subtotal'], 2) ?></td>
                        <td style="padding:12px 20px; text-align:right; color:#e03131;">-৳<?= number_format((float)$row['discount_amount'], 2) ?></td>
                        <td style="padding:12px 20px; text-align:right; font-weight:800; color:var(--color-primary);">৳<?= number_format((float)$row['total_amount'], 2) ?></td>
                        <td style="padding:12px 20px; color:var(--color-text-faint);"><?= date('M d, Y H:i', strtotime($row['created_at'])) ?></td>
                        <td style="padding:12px 20px; text-align:right;">
                            <?php foreach (['58' => '58', '80' => '80', 'a4' => 'A4'] as $wk => $wl): ?><a href="?id=<?= (int) $row['id'] ?>&amp;w=<?= $wk ?>" target="_blank" rel="noopener" class="btn btn-secondary" style="padding:4px 8px; font-size:10px; border-radius:var(--radius-sm); text-decoration:none;" title="Reprint (<?= $wl ?>)"><i class="fas fa-print"></i> <?= $wl ?></a> <?php endforeach; ?>
                        </td>
                    </tr>
                <?php
                    endforeach;
                else:
                ?>
                    <tr>
                        <td colspan="6" style="padding:32px; text-align:center; color:var(--color-text-faint);">No counter sales invoices logged in the specified period.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>

