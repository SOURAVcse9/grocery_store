<?php
/**
 * ==========================================================================
 * admin/includes/pos_lib.php — Shared, server-authoritative POS business logic
 * ==========================================================================
 * Used by: pos/checkout.php, pos/ajax/process_sale.php, pos/returns.php,
 *          pos/register.php, pos/ajax/shifts.php, pos/shift.php, pos/receipts.php
 *
 * Design rules
 *  - NO schema change is required. Payment split / shift link / idempotency key
 *    are stored as a machine-readable marker appended to orders.note.
 *  - The client is never trusted: prices, totals, stock, discounts, payments
 *    and change are all recomputed and validated here.
 *  - Every money-moving operation runs inside ONE database transaction with
 *    row locks taken in a stable order (product id ASC) to avoid deadlocks.
 * ==========================================================================
 */

declare(strict_types=1);

if (!class_exists('PosException')) {
    /** A validation / business-rule failure whose message is safe to show to the cashier. */
    final class PosException extends RuntimeException {}
}

const POS_MAX_LINES       = 200;
const POS_MAX_QTY         = 100000.0;
const POS_PAYMENT_METHODS = ['cash', 'card', 'bkash', 'bank', 'wallet'];

/* ------------------------------------------------------------------ helpers */

function pos_round(float $v): float
{
    return round($v + 0.0, 2, PHP_ROUND_HALF_UP);
}

function pos_money_in($v): float
{
    if (is_string($v)) {
        $v = trim($v);
        if ($v === '') {
            return 0.0;
        }
    }
    if (!is_numeric($v)) {
        throw new PosException('Invalid amount supplied.');
    }
    $f = (float) $v;
    if (!is_finite($f)) {
        throw new PosException('Invalid amount supplied.');
    }
    return $f;
}

function pos_clip(string $s, int $max): string
{
    $s = trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $s) ?? '');
    return function_exists('mb_substr') ? mb_substr($s, 0, $max, 'UTF-8') : substr($s, 0, $max);
}

function pos_table_exists(PDO $pdo, string $table): bool
{
    static $cache = [];
    if (!isset($cache[$table])) {
        try {
            $st = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
            $st->execute([$table]);
            $cache[$table] = ((int) $st->fetchColumn() > 0);
        } catch (Throwable $e) {
            $cache[$table] = false;
        }
    }
    return $cache[$table];
}

function pos_column_exists(PDO $pdo, string $table, string $column): bool
{
    static $cache = [];
    $k = $table . '.' . $column;
    if (!isset($cache[$k])) {
        try {
            $st = $pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?');
            $st->execute([$table, $column]);
            $cache[$k] = ((int) $st->fetchColumn() > 0);
        } catch (Throwable $e) {
            $cache[$k] = false;
        }
    }
    return $cache[$k];
}

function pos_column_is_decimal(PDO $pdo, string $table, string $column): bool
{
    static $cache = [];
    $k = $table . '.' . $column;
    if (!isset($cache[$k])) {
        try {
            $st = $pdo->prepare('SELECT DATA_TYPE FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?');
            $st->execute([$table, $column]);
            $t = strtolower((string) $st->fetchColumn());
            $cache[$k] = in_array($t, ['decimal', 'numeric', 'float', 'double'], true);
        } catch (Throwable $e) {
            $cache[$k] = false;
        }
    }
    return $cache[$k];
}

/** Fractional quantities (kg, litre …) are only safe when stock + order_items.quantity are DECIMAL. */
function pos_decimal_qty_enabled(PDO $pdo): bool
{
    return pos_column_is_decimal($pdo, 'products', 'stock')
        && pos_column_is_decimal($pdo, 'order_items', 'quantity')
        && pos_column_is_decimal($pdo, 'inventory_logs', 'quantity');
}

function pos_setting(PDO $pdo, string $key, string $default = ''): string
{
    try {
        $st = $pdo->prepare('SELECT value FROM settings WHERE key_name = ? LIMIT 1');
        $st->execute([$key]);
        $v = $st->fetchColumn();
        return ($v === false || $v === null) ? $default : (string) $v;
    } catch (Throwable $e) {
        return $default;
    }
}

function pos_effective_price(array $p): float
{
    $reg = (float) $p['price'];
    $dis = $p['discount_price'] ?? null;
    return ($dis !== null && (float) $dis > 0 && (float) $dis < $reg) ? (float) $dis : $reg;
}

function pos_fmt_qty(float $q): string
{
    return rtrim(rtrim(number_format($q, 3, '.', ''), '0'), '.');
}

function pos_active_shift(PDO $pdo, int $adminId, bool $lock = false): ?array
{
    $st = $pdo->prepare("SELECT * FROM pos_shifts WHERE admin_id = ? AND status = 'open' ORDER BY id DESC LIMIT 1" . ($lock ? ' FOR UPDATE' : ''));
    $st->execute([$adminId]);
    $row = $st->fetch();
    return $row ?: null;
}

function pos_walkin_customer_id(PDO $pdo): int
{
    $row = $pdo->query("SELECT id FROM users WHERE phone = '00000000000' LIMIT 1")->fetch();
    if ($row) {
        return (int) $row['id'];
    }
    $pw = password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT);
    $pdo->prepare("INSERT INTO users (role_id, full_name, email, phone, password, is_verified, is_active, created_at, updated_at)
                   VALUES (2, 'Walk-in Customer', 'walkin@grocery.store', '00000000000', ?, 1, 1, NOW(), NOW())")->execute([$pw]);
    return (int) $pdo->lastInsertId();
}

/* ------------------------------------------------- order-note marker (no DDL) */

/**
 * Marker format (one line, appended to orders.note):
 *   [POS|shift=12|admin=2|cash=500.00|card=0.00|bkash=300.00|bank=0.00|wallet=0.00|change=0.00|idem=KEY]
 */
function pos_build_marker(array $m): string
{
    $parts = ['POS'];
    foreach (['shift', 'admin', 'cash', 'card', 'bkash', 'bank', 'wallet', 'change', 'idem'] as $k) {
        if (isset($m[$k])) {
            $v = is_float($m[$k]) ? number_format($m[$k], 2, '.', '') : preg_replace('/[^A-Za-z0-9_\-.]/', '', (string) $m[$k]);
            $parts[] = $k . '=' . $v;
        }
    }
    return '[' . implode('|', $parts) . ']';
}

function pos_parse_marker(?string $note): ?array
{
    if ($note === null || !preg_match('/\[POS\|([^\]]*)\]/', $note, $mm)) {
        return null;
    }
    $out = [];
    foreach (explode('|', $mm[1]) as $kv) {
        if (strpos($kv, '=') !== false) {
            [$k, $v] = explode('=', $kv, 2);
            $out[$k] = $v;
        }
    }
    return $out;
}

/* ------------------------------------------------------------- cart parsing */

/**
 * Validate and merge cart lines. Duplicate product lines are merged so stock is
 * checked against the TOTAL requested quantity (prevents split-line overselling).
 *
 * @return array<int,array{id:int,qty:float,price:?float,line_discount:float}>
 */
function pos_normalize_cart($raw, bool $allowDecimal): array
{
    if (is_string($raw)) {
        $raw = json_decode($raw, true);
    }
    if (!is_array($raw) || $raw === []) {
        throw new PosException('Cart is empty.');
    }
    // Accept both a list and the {id: line} map used by the POS front-end.
    $lines = array_values($raw);
    if (count($lines) > POS_MAX_LINES) {
        throw new PosException('Too many cart lines (max ' . POS_MAX_LINES . ').');
    }

    $merged = [];
    foreach ($lines as $ln) {
        if (!is_array($ln)) {
            throw new PosException('Malformed cart line.');
        }
        $id = (int) ($ln['id'] ?? 0);
        if ($id <= 0) {
            throw new PosException('Cart contains an invalid product.');
        }
        $qty = pos_money_in($ln['qty'] ?? 0);
        if ($qty <= 0) {
            throw new PosException('Quantity must be greater than zero.');
        }
        if ($qty > POS_MAX_QTY) {
            throw new PosException('Quantity is unreasonably large.');
        }
        $qty = round($qty, 3);
        if ($qty < 0.001) {
            throw new PosException('Quantity must be at least 0.001.');
        }
        if (!$allowDecimal && abs($qty - round($qty)) > 1e-9) {
            throw new PosException('Fractional quantities are not enabled for this store (run block A of migrations/001_pos_optional_upgrades.sql).');
        }
        $price = (isset($ln['price']) && $ln['price'] !== '') ? pos_money_in($ln['price']) : null;
        if ($price !== null && $price < 0) {
            throw new PosException('Price cannot be negative.');
        }
        $ld = pos_money_in($ln['line_discount'] ?? 0);
        if ($ld < 0) {
            throw new PosException('Line discount cannot be negative.');
        }

        if (isset($merged[$id])) {
            // Same product on two lines: only merge if the pricing intent is identical.
            if ($merged[$id]['price'] !== $price) {
                throw new PosException('The same product appears twice with different prices.');
            }
            $merged[$id]['qty'] = round($merged[$id]['qty'] + $qty, 3);
            $merged[$id]['line_discount'] += $ld;
        } else {
            $merged[$id] = ['id' => $id, 'qty' => $qty, 'price' => $price, 'line_discount' => $ld];
        }
    }
    ksort($merged); // stable lock order
    return $merged;
}

/* ---------------------------------------------------------------- checkout */

/**
 * Complete a POS sale atomically.
 *
 * @param array    $in      items, discount, cash, card, bkash, bank_transfer, wallet, customer_id, note, idempotency_key
 * @param callable $can     fn(string $permissionKey): bool
 * @return array{order_id:int,order_number:string,subtotal:float,discount:float,vat:float,total:float,paid:float,change:float,duplicate:bool}
 */
function pos_checkout(PDO $pdo, array $in, int $adminId, callable $can): array
{
    $allowDecimal = pos_decimal_qty_enabled($pdo);
    $cart = pos_normalize_cart($in['items'] ?? '[]', $allowDecimal);

    $cartDiscount = pos_money_in($in['discount'] ?? 0);
    if ($cartDiscount < 0) {
        throw new PosException('Discount cannot be negative.');
    }
    $pay = [
        'cash'   => pos_money_in($in['cash'] ?? 0),
        'card'   => pos_money_in($in['card'] ?? 0),
        'bkash'  => pos_money_in($in['bkash'] ?? 0),
        'bank'   => pos_money_in($in['bank_transfer'] ?? ($in['bank'] ?? 0)),
        'wallet' => pos_money_in($in['wallet'] ?? 0),
    ];
    foreach ($pay as $m => $a) {
        if ($a < 0) {
            throw new PosException('Payment amounts cannot be negative.');
        }
    }
    $customerId = (int) ($in['customer_id'] ?? 0);
    $userNote   = pos_clip((string) ($in['note'] ?? 'POS Checkout'), 800);
    $idem       = preg_replace('/[^A-Za-z0-9_\-]/', '', (string) ($in['idempotency_key'] ?? ''));
    $idem       = substr((string) $idem, 0, 64);

    $lockName = $idem !== '' ? 'pos_idem_' . md5($idem) : null;
    $gotLock  = false;
    if ($lockName !== null) {
        $st = $pdo->prepare('SELECT GET_LOCK(?, 10)');
        $st->execute([$lockName]);
        $gotLock = ((int) $st->fetchColumn() === 1);
        if (!$gotLock) {
            throw new PosException('Another request for this sale is still being processed. Please wait.');
        }
    }

    try {
        // ---- Idempotency: the same key must never create a second sale ------
        if ($idem !== '') {
            $st = $pdo->prepare("SELECT id, order_number, subtotal, discount_amount, total_amount, note FROM orders
                                  WHERE order_number LIKE 'POS-%' AND created_at >= DATE_SUB(NOW(), INTERVAL 3 DAY)
                                    AND note LIKE ? LIMIT 1");
            $st->execute(['%|idem=' . $idem . ']%']);
            if ($dup = $st->fetch()) {
                $mk = pos_parse_marker($dup['note']) ?? [];
                $paid = (float) ($mk['cash'] ?? 0) + (float) ($mk['card'] ?? 0) + (float) ($mk['bkash'] ?? 0) + (float) ($mk['bank'] ?? 0) + (float) ($mk['wallet'] ?? 0);
                return [
                    'order_id' => (int) $dup['id'], 'order_number' => $dup['order_number'],
                    'subtotal' => (float) $dup['subtotal'], 'discount' => (float) $dup['discount_amount'],
                    'vat' => max(0.0, pos_round((float) $dup['total_amount'] - ((float) $dup['subtotal'] - (float) $dup['discount_amount']))),
                    'total' => (float) $dup['total_amount'], 'paid' => pos_round($paid), 'change' => (float) ($mk['change'] ?? 0),
                    'duplicate' => true,
                ];
            }
        }

        $pdo->beginTransaction();

        $shift = pos_active_shift($pdo, $adminId, true);
        if (!$shift) {
            throw new PosException('No active cashier shift open.');
        }

        // ---- Customer -------------------------------------------------------
        $walkinId = pos_walkin_customer_id($pdo);
        if ($customerId <= 0) {
            $customerId = $walkinId;
        } else {
            $st = $pdo->prepare('SELECT id FROM users WHERE id = ? AND deleted_at IS NULL LIMIT 1');
            $st->execute([$customerId]);
            if (!$st->fetch()) {
                $customerId = $walkinId;
            }
        }
        $isRegistered = ($customerId !== $walkinId);

        // ---- Lock + price products (sorted by id) ---------------------------
        $ids = array_keys($cart);
        $ph  = implode(',', array_fill(0, count($ids), '?'));
        $st  = $pdo->prepare("SELECT id, name, sku, price, discount_price, stock, is_active FROM products
                               WHERE id IN ($ph) AND deleted_at IS NULL ORDER BY id ASC FOR UPDATE");
        $st->execute($ids);
        $products = [];
        foreach ($st->fetchAll() as $row) {
            $products[(int) $row['id']] = $row;
        }

        $canOverride = $can('pos.override');
        $canDiscount = $canOverride || $can('pos.discount');

        $lines = [];
        $gross = 0.0;
        $lineDiscountTotal = 0.0;
        foreach ($cart as $id => $c) {
            if (!isset($products[$id])) {
                throw new PosException("Product #{$id} was not found.");
            }
            $p = $products[$id];
            if ((int) ($p['is_active'] ?? 1) !== 1) {
                throw new PosException("'{$p['name']}' is inactive and cannot be sold.");
            }
            if ((float) $p['stock'] + 1e-9 < $c['qty']) {
                throw new PosException("'{$p['name']}' has insufficient stock. Available: " . pos_fmt_qty((float) $p['stock']) . ', requested: ' . pos_fmt_qty($c['qty']));
            }

            $catalog = pos_effective_price($p);
            $unit    = $catalog;
            if ($c['price'] !== null && abs($c['price'] - $catalog) > 0.009) {
                if ($c['price'] < $catalog) {
                    if (!$canDiscount) {
                        throw new PosException("You do not have permission to discount or override prices ('{$p['name']}').");
                    }
                } elseif (!$canOverride) {
                    throw new PosException("You do not have permission to override prices ('{$p['name']}').");
                }
                $unit = $c['price'];
            }
            $unit      = pos_round($unit);
            $lineGross = pos_round($unit * $c['qty']);
            $ld        = pos_round($c['line_discount']);
            if ($ld > 0 && !$canDiscount) {
                throw new PosException('You do not have permission to apply line discounts.');
            }
            if ($ld > $lineGross + 0.001) {
                throw new PosException("Line discount exceeds the line total for '{$p['name']}'.");
            }
            $gross             += $lineGross;
            $lineDiscountTotal += $ld;
            $lines[] = [
                'id' => $id, 'name' => $p['name'], 'sku' => $p['sku'] ?? 'N/A', 'qty' => $c['qty'],
                'unit' => $unit, 'gross' => $lineGross, 'line_discount' => $ld, 'net' => pos_round($lineGross - $ld),
            ];
        }
        $gross             = pos_round($gross);
        $lineDiscountTotal = pos_round($lineDiscountTotal);

        // ---- Discount policy -------------------------------------------------
        $afterLines = pos_round($gross - $lineDiscountTotal);
        if ($cartDiscount > $afterLines + 0.001) {
            throw new PosException('Discount cannot exceed the cart subtotal.');
        }
        $cartDiscount = pos_round($cartDiscount);
        $totalDiscount = pos_round($lineDiscountTotal + $cartDiscount);
        if ($cartDiscount > 0 && !$canDiscount && !$can('pos.sale')) {
            throw new PosException('You do not have permission to apply discounts.');
        }
        if ($totalDiscount > 0 && !$canOverride && $gross > 0) {
            $maxPct = (float) pos_setting($pdo, 'pos_max_discount_pct', '15');
            if ($maxPct <= 0) {
                $maxPct = 15.0;
            }
            if ($totalDiscount > pos_round($gross * $maxPct / 100) + 0.001) {
                throw new PosException("Discount exceeds the {$maxPct}% limit. A manager override is required.");
            }
        }

        // ---- Totals (VAT only if configured; site_tax is exclusive %) --------
        $taxable = pos_round($gross - $totalDiscount);
        $vatPct  = max(0.0, (float) pos_setting($pdo, 'site_tax', '0'));
        $vat     = $vatPct > 0 ? pos_round($taxable * $vatPct / 100) : 0.0;
        $total   = pos_round($taxable + $vat);

        // ---- Payments --------------------------------------------------------
        $nonCash = pos_round($pay['card'] + $pay['bkash'] + $pay['bank'] + $pay['wallet']);
        if ($nonCash > $total + 0.001) {
            throw new PosException('Card / mobile / bank / wallet payments cannot exceed the total (no change can be given on them).');
        }
        $cashDue = pos_round($total - $nonCash);
        if ($pay['cash'] + 0.001 < $cashDue) {
            $short = pos_round($cashDue - $pay['cash']);
            throw new PosException('Payment is short by ' . number_format($short, 2) . '. The sale cannot be completed with a balance due.');
        }
        $change = pos_round($pay['cash'] - $cashDue);
        $paidTotal = pos_round($pay['cash'] - $change + $nonCash); // = $total

        // ---- Wallet (atomic conditional debit) ------------------------------
        if ($pay['wallet'] > 0) {
            if (!$isRegistered) {
                throw new PosException('Wallet payment requires a registered customer.');
            }
            $st = $pdo->prepare('UPDATE users SET wallet_balance = wallet_balance - ? WHERE id = ? AND wallet_balance >= ?');
            $st->execute([$pay['wallet'], $customerId, $pay['wallet']]);
            if ($st->rowCount() !== 1) {
                throw new PosException('Insufficient customer wallet balance.');
            }
        }
        if ($isRegistered) {
            $points = (int) floor($total / 100);
            if ($points > 0) {
                $pdo->prepare('UPDATE users SET reward_points = reward_points + ? WHERE id = ?')->execute([$points, $customerId]);
            }
        }

        // ---- Order header ----------------------------------------------------
        $methodEnum = 'cod';
        $maxAmt = $pay['cash'] - $change;
        if ($pay['card'] > $maxAmt) {
            $methodEnum = 'card';
            $maxAmt = $pay['card'];
        }
        if (($pay['bkash'] + $pay['bank']) > $maxAmt) {
            $methodEnum = 'mobile_banking';
        }

        $orderNumber = '';
        for ($i = 0; $i < 8; $i++) {
            $cand = 'POS-' . date('Ymd') . '-' . random_int(100000, 999999);
            $st = $pdo->prepare('SELECT 1 FROM orders WHERE order_number = ? LIMIT 1');
            $st->execute([$cand]);
            if (!$st->fetchColumn()) {
                $orderNumber = $cand;
                break;
            }
        }
        if ($orderNumber === '') {
            throw new PosException('Could not allocate a receipt number. Please retry.');
        }

        $mk = [
            'shift' => (int) $shift['id'], 'admin' => $adminId,
            'cash' => pos_round($pay['cash'] - $change), 'card' => pos_round($pay['card']), 'bkash' => pos_round($pay['bkash']),
            'bank' => pos_round($pay['bank']), 'wallet' => pos_round($pay['wallet']), 'change' => $change,
        ];
        if ($idem !== '') {
            $mk['idem'] = $idem;
        }
        $marker = pos_build_marker($mk);
        $fullNote = ($userNote !== '' ? $userNote . "\n" : '') . $marker;

        $pdo->prepare("INSERT INTO orders (order_number, user_id, address_id, subtotal, discount_amount, total_amount, payment_method, payment_status, status, note, created_at)
                       VALUES (?, ?, NULL, ?, ?, ?, ?, 'paid', 'delivered', ?, NOW())")
            ->execute([$orderNumber, $customerId, $gross, $totalDiscount, $total, $methodEnum, $fullNote]);
        $orderId = (int) $pdo->lastInsertId();

        // ---- Lines + stock + movement ---------------------------------------
        $insItem = $pdo->prepare('INSERT INTO order_items (order_id, product_id, product_name, product_sku, price, quantity, line_total) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $decStock = $pdo->prepare('UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?');
        $getStock = $pdo->prepare('SELECT stock FROM products WHERE id = ?');
        $insLog = $pdo->prepare("INSERT INTO inventory_logs (product_id, admin_id, type, quantity, remaining_stock, note, created_at) VALUES (?, ?, 'stock_out', ?, ?, ?, NOW())");
        foreach ($lines as $l) {
            $insItem->execute([$orderId, $l['id'], $l['name'], $l['sku'], $l['unit'], $l['qty'], $l['net']]);
            $decStock->execute([$l['qty'], $l['id'], $l['qty']]);
            if ($decStock->rowCount() !== 1) {
                throw new PosException("'{$l['name']}' ran out of stock during checkout.");
            }
            $getStock->execute([$l['id']]);
            $rem = $getStock->fetchColumn();
            $insLog->execute([$l['id'], $adminId, -$l['qty'], $rem, "POS sale {$orderNumber}"]);
        }

        // ---- Ledger: ONE entry per tender (not a blanket 'pos_split') -------
        $ledgerMap = ['cash' => ['cash', $pay['cash'] - $change], 'card' => ['card', $pay['card']], 'bkash' => ['mobile_banking', $pay['bkash']],
                      'bank' => ['bank_transfer', $pay['bank']], 'wallet' => ['wallet', $pay['wallet']]];
        $insLedger = $pdo->prepare("INSERT INTO transactions (type, category_id, amount, reference, payment_method, reconciled, created_at) VALUES ('income', NULL, ?, ?, ?, 1, NOW())");
        $ledgerRows = 0;
        foreach ($ledgerMap as [$pm, $amt]) {
            $amt = pos_round((float) $amt);
            if ($amt > 0) {
                $insLedger->execute([$amt, "POS Counter checkout sales: {$orderNumber}", $pm]);
                $ledgerRows++;
            }
        }
        if ($ledgerRows === 0 && $total > 0) {
            throw new PosException('No payment was recorded.');
        }

        $pdo->commit();

        return [
            'order_id' => $orderId, 'order_number' => $orderNumber, 'subtotal' => $gross, 'discount' => $totalDiscount, 'vat' => $vat,
            'total' => $total, 'paid' => $paidTotal, 'change' => $change, 'duplicate' => false,
        ];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    } finally {
        if ($gotLock) {
            $pdo->prepare('SELECT RELEASE_LOCK(?)')->execute([$lockName]);
        }
    }
}

/* ------------------------------------------------------------------ shifts */

/**
 * Expected-cash reconciliation for a shift. Only CASH tenders count toward the drawer.
 * Orders carrying a shift marker are matched exactly; legacy unmarked POS orders created
 * inside the shift window are included as fully-cash (the pre-upgrade behaviour).
 */
function pos_shift_summary(PDO $pdo, array $shift): array
{
    $sid   = (int) $shift['id'];
    $end   = $shift['end_time'] ?? null;
    $start = $shift['start_time'] ?? $shift['created_at'];

    $st = $pdo->prepare("SELECT id, order_number, total_amount, payment_status, status, note, created_at FROM orders
                          WHERE order_number LIKE 'POS-%' AND (note LIKE ? OR (note NOT LIKE '%[POS|shift=%' AND created_at >= ? AND created_at <= COALESCE(?, NOW())))");
    $st->execute(['%[POS|shift=' . $sid . '|%', $start, $end]);

    $sum = ['sales_count' => 0, 'gross_sales' => 0.0, 'cash_sales' => 0.0, 'card' => 0.0, 'bkash' => 0.0, 'bank' => 0.0, 'wallet' => 0.0,
            'voided_count' => 0, 'voided_cash' => 0.0, 'voided_total' => 0.0];
    foreach ($st->fetchAll() as $o) {
        $mk = pos_parse_marker($o['note']);
        $isVoid = ($o['status'] === 'cancelled');
        if ($mk !== null && (int) ($mk['shift'] ?? 0) === $sid) {
            $cash = (float) $mk['cash'];
            $tot  = (float) $o['total_amount'];
            if ($isVoid) {
                $sum['voided_count']++;
                $sum['voided_cash']  += $cash;
                $sum['voided_total'] += $tot;
                continue;
            }
            $sum['sales_count']++;
            $sum['gross_sales'] += $tot;
            $sum['cash_sales']  += $cash;
            $sum['card']  += (float) $mk['card'];
            $sum['bkash'] += (float) $mk['bkash'];
            $sum['bank']  += (float) $mk['bank'];
            $sum['wallet'] += (float) $mk['wallet'];
        } elseif ($mk === null) { // legacy
            if ($isVoid) {
                continue;
            }
            $sum['sales_count']++;
            $sum['gross_sales'] += (float) $o['total_amount'];
            $sum['cash_sales']  += (float) $o['total_amount'];
        }
    }

    // Drawer cash in / out
    $cashIn = $cashOut = 0.0;
    $st = $pdo->prepare('SELECT type, amount FROM pos_drawer_transactions WHERE shift_id = ?');
    $st->execute([$sid]);
    foreach ($st->fetchAll() as $t) {
        if ($t['type'] === 'cash_in') {
            $cashIn += (float) $t['amount'];
        } else {
            $cashOut += (float) $t['amount'];
        }
    }

    // Cash refunds paid by this cashier during the shift window
    $st = $pdo->prepare("SELECT COALESCE(SUM(refund_amount),0) FROM pos_returns WHERE admin_id = ? AND refund_method = 'cash' AND created_at >= ? AND created_at <= COALESCE(?, NOW())");
    $st->execute([(int) $shift['admin_id'], $start, $end]);
    $cashRefunds = (float) $st->fetchColumn();

    $opening  = (float) $shift['opening_cash'];
    $expected = pos_round($opening + $sum['cash_sales'] + $cashIn - $cashOut - $cashRefunds);

    return $sum + [
        'opening_cash' => $opening, 'cash_in' => pos_round($cashIn), 'cash_out' => pos_round($cashOut),
        'cash_refunds' => pos_round($cashRefunds), 'expected_cash' => $expected,
    ];
}

function pos_open_shift(PDO $pdo, int $adminId, float $openingCash): int
{
    if ($openingCash < 0 || !is_finite($openingCash) || $openingCash > 10000000) {
        throw new PosException('Opening cash must be a sensible, non-negative amount.');
    }
    $pdo->beginTransaction();
    try {
        // Serialise concurrent opens for the same cashier.
        $pdo->prepare('SELECT id FROM admins WHERE id = ? FOR UPDATE')->execute([$adminId]);
        if (pos_active_shift($pdo, $adminId, true)) {
            throw new PosException('A register shift is already active.');
        }
        $pdo->prepare("INSERT INTO pos_shifts (admin_id, opening_cash, status, start_time, created_at) VALUES (?, ?, 'open', NOW(), NOW())")
            ->execute([$adminId, pos_round($openingCash)]);
        $id = (int) $pdo->lastInsertId();
        $pdo->commit();
        return $id;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/**
 * Close the cashier's open shift. The variance (actual − expected) is stored as-is and never altered.
 * closing_cash = EXPECTED cash, actual_cash = counted cash (existing column semantics preserved).
 */
function pos_close_shift(PDO $pdo, int $adminId, float $actualCash): array
{
    if ($actualCash < 0 || !is_finite($actualCash)) {
        throw new PosException('Counted cash must be a non-negative amount.');
    }
    $pdo->beginTransaction();
    try {
        $shift = pos_active_shift($pdo, $adminId, true);
        if (!$shift) {
            throw new PosException('No active shift found to close.');
        }
        $sum = pos_shift_summary($pdo, $shift);
        $pdo->prepare("UPDATE pos_shifts SET end_time = NOW(), closing_cash = ?, actual_cash = ?, status = 'closed' WHERE id = ? AND status = 'open'")
            ->execute([$sum['expected_cash'], pos_round($actualCash), $shift['id']]);
        $pdo->commit();
        return ['shift_id' => (int) $shift['id'], 'expected' => $sum['expected_cash'], 'actual' => pos_round($actualCash),
                'difference' => pos_round($actualCash - $sum['expected_cash']), 'summary' => $sum];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function pos_drawer_tx(PDO $pdo, int $adminId, string $type, float $amount, string $notes): void
{
    if (!in_array($type, ['cash_in', 'cash_out'], true)) {
        throw new PosException('Invalid drawer transaction type.');
    }
    if ($amount <= 0 || !is_finite($amount) || $amount > 10000000) {
        throw new PosException('Drawer transaction amount must be greater than zero.');
    }
    $notes = pos_clip($notes, 250);
    if ($type === 'cash_out' && $notes === '') {
        throw new PosException('A reason is required for cash-out.');
    }
    $pdo->beginTransaction();
    try {
        $shift = pos_active_shift($pdo, $adminId, true);
        if (!$shift) {
            throw new PosException('No active shift. Open a shift first.');
        }
        if ($type === 'cash_out') {
            $sum = pos_shift_summary($pdo, $shift);
            if ($amount > $sum['expected_cash'] + 0.001) {
                throw new PosException('Cash-out exceeds the cash expected in the drawer (' . number_format($sum['expected_cash'], 2) . ').');
            }
        }
        $pdo->prepare('INSERT INTO pos_drawer_transactions (shift_id, type, amount, notes, created_at) VALUES (?, ?, ?, ?, NOW())')
            ->execute([$shift['id'], $type, pos_round($amount), $notes]);
        $pdo->prepare("INSERT INTO transactions (type, category_id, amount, reference, payment_method, reconciled, created_at) VALUES (?, NULL, ?, ?, 'cash', 1, NOW())")
            ->execute([$type === 'cash_in' ? 'income' : 'expense', pos_round($amount), "POS Shift #{$shift['id']} Drawer {$type}: {$notes}"]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/* ------------------------------------------------------- returns / refunds */

/** Quantity already returned per product for an order. @return array<int,float> */
function pos_returned_qty_map(PDO $pdo, int $orderId, bool $lock = false): array
{
    $st = $pdo->prepare('SELECT ri.product_id, SUM(ri.quantity) AS q FROM pos_return_items ri JOIN pos_returns r ON r.id = ri.pos_return_id WHERE r.order_id = ? GROUP BY ri.product_id' . ($lock ? ' FOR UPDATE' : ''));
    $st->execute([$orderId]);
    $out = [];
    foreach ($st->fetchAll() as $r) {
        $out[(int) $r['product_id']] = (float) $r['q'];
    }
    return $out;
}

/**
 * Share of each line's net value the customer really paid after order-level discount / VAT.
 * refund per unit = (line_total / quantity) * factor
 */
function pos_refund_factor(array $order, array $items): float
{
    $net = 0.0;
    foreach ($items as $it) {
        $net += (float) $it['line_total'];
    }
    return $net > 0 ? ((float) $order['total_amount'] / $net) : 1.0;
}

/**
 * @param array<int,float> $returns product_id => quantity
 * @return array{return_id:int,refund:float}
 */
function pos_process_return(PDO $pdo, int $orderId, array $returns, string $method, string $reason, int $adminId, callable $can): array
{
    if (!in_array($method, ['cash', 'wallet', 'card', 'bkash', 'bank'], true)) {
        throw new PosException('Invalid refund method.');
    }
    $reason = pos_clip($reason, 250);
    if ($reason === '') {
        throw new PosException('A return reason is required.');
    }
    $allowDecimal = pos_decimal_qty_enabled($pdo);

    $want = [];
    foreach ($returns as $pid => $q) {
        $q = pos_money_in($q);
        if ($q < 0) {
            throw new PosException('Return quantity cannot be negative.');
        }
        if ($q > 0) {
            $q = round($q, 3);
            if (!$allowDecimal && abs($q - round($q)) > 1e-9) {
                throw new PosException('Fractional return quantities require block A of migrations/001_pos_optional_upgrades.sql.');
            }
            $want[(int) $pid] = $q;
        }
    }
    if ($want === []) {
        throw new PosException('No items specified for return.');
    }
    ksort($want);

    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare('SELECT * FROM orders WHERE id = ? FOR UPDATE'); // serialises concurrent returns on this order
        $st->execute([$orderId]);
        $order = $st->fetch();
        if (!$order) {
            throw new PosException('Order not found.');
        }
        if (strpos((string) $order['order_number'], 'POS-') !== 0) {
            throw new PosException('Only POS counter sales can be returned here. Use Orders for online orders.');
        }
        if ($order['status'] === 'cancelled') {
            throw new PosException('This sale was voided and cannot be returned.');
        }

        $st = $pdo->prepare('SELECT * FROM order_items WHERE order_id = ?');
        $st->execute([$orderId]);
        $items = [];
        foreach ($st->fetchAll() as $it) {
            $items[(int) $it['product_id']] = $it;
        }
        $already = pos_returned_qty_map($pdo, $orderId, true);

        $factor = pos_refund_factor($order, $items);

        $refund = 0.0;
        $rows = [];
        foreach ($want as $pid => $q) {
            if (!isset($items[$pid])) {
                throw new PosException("Product #{$pid} is not part of this sale.");
            }
            $it = $items[$pid];
            $remaining = (float) $it['quantity'] - ($already[$pid] ?? 0.0);
            if ($q > $remaining + 1e-9) {
                throw new PosException("Cannot return " . pos_fmt_qty($q) . " of '{$it['product_name']}'. Only " . pos_fmt_qty(max(0.0, $remaining)) . ' left to return.');
            }
            $unitPaid = ((float) $it['line_total'] / (float) $it['quantity']) * $factor;
            $line = pos_round($unitPaid * $q);
            $refund += $line;
            $rows[] = ['pid' => $pid, 'qty' => $q, 'name' => $it['product_name']];
        }
        $refund = pos_round($refund);

        // never refund more than was paid in total
        $st = $pdo->prepare('SELECT COALESCE(SUM(refund_amount),0) FROM pos_returns WHERE order_id = ?');
        $st->execute([$orderId]);
        $refundedSoFar = (float) $st->fetchColumn();
        if ($refund + $refundedSoFar > (float) $order['total_amount'] + 0.01) {
            $refund = pos_round(max(0.0, (float) $order['total_amount'] - $refundedSoFar));
        }
        if ($refund <= 0) {
            throw new PosException('Nothing to refund for the selected items.');
        }

        // Authorization: refunds above the configured limit need a manager override
        $limit = (float) pos_setting($pdo, 'pos_refund_approval_limit', '0');
        if ($limit > 0 && $refund > $limit && !$can('pos.override')) {
            throw new PosException('Refunds above ' . number_format($limit, 2) . ' require manager approval (pos.override).');
        }
        if ($method === 'wallet') {
            $walkin = pos_walkin_customer_id($pdo);
            if ((int) $order['user_id'] === $walkin) {
                throw new PosException('Wallet refunds need a registered customer. Choose another refund method.');
            }
        }

        $pdo->prepare('INSERT INTO pos_returns (order_id, admin_id, refund_amount, refund_method, created_at) VALUES (?, ?, ?, ?, NOW())')
            ->execute([$orderId, $adminId, $refund, $method]);
        $returnId = (int) $pdo->lastInsertId();

        $insRI = $pdo->prepare('INSERT INTO pos_return_items (pos_return_id, product_id, quantity) VALUES (?, ?, ?)');
        $inc   = $pdo->prepare('UPDATE products SET stock = stock + ? WHERE id = ?');
        $get   = $pdo->prepare('SELECT stock FROM products WHERE id = ?');
        $log   = $pdo->prepare("INSERT INTO inventory_logs (product_id, admin_id, type, quantity, remaining_stock, note, created_at) VALUES (?, ?, 'stock_in', ?, ?, ?, NOW())");
        foreach ($rows as $r) {
            $insRI->execute([$returnId, $r['pid'], $r['qty']]);
            $inc->execute([$r['qty'], $r['pid']]);
            $get->execute([$r['pid']]);
            $log->execute([$r['pid'], $adminId, $r['qty'], $get->fetchColumn(), "Return #{$returnId} for {$order['order_number']}"]);
        }
        if ($method === 'wallet') {
            $pdo->prepare('UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?')->execute([$refund, $order['user_id']]);
        }
        $catId = $pdo->query("SELECT id FROM expense_categories WHERE name = 'Customer Refunds' LIMIT 1")->fetchColumn();
        $pdo->prepare("INSERT INTO transactions (type, category_id, amount, reference, payment_method, reconciled, created_at) VALUES ('expense', ?, ?, ?, ?, 1, NOW())")
            ->execute([$catId !== false ? $catId : null, $refund, "POS Refund #{$returnId} for {$order['order_number']}: {$reason}", $method === 'bkash' ? 'mobile_banking' : $method]);

        // If everything has now been returned, mark the order refunded (never delete it)
        $after = pos_returned_qty_map($pdo, $orderId);
        $all = true;
        foreach ($items as $pid => $it) {
            if (($after[$pid] ?? 0.0) + 1e-9 < (float) $it['quantity']) {
                $all = false;
                break;
            }
        }
        if ($all) {
            $pdo->prepare("UPDATE orders SET payment_status = 'refunded' WHERE id = ?")->execute([$orderId]);
        }

        $pdo->commit();
        return ['return_id' => $returnId, 'refund' => $refund, 'order_number' => $order['order_number']];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/**
 * Void (reverse) an entire POS sale. The order row is KEPT and flagged cancelled/refunded.
 * Allowed only for POS orders with no returns; restores stock and posts a ledger reversal.
 */
function pos_void_sale(PDO $pdo, int $orderId, string $reason, int $adminId): array
{
    $reason = pos_clip($reason, 250);
    if ($reason === '') {
        throw new PosException('A void reason is required.');
    }
    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare('SELECT * FROM orders WHERE id = ? FOR UPDATE');
        $st->execute([$orderId]);
        $order = $st->fetch();
        if (!$order || strpos((string) $order['order_number'], 'POS-') !== 0) {
            throw new PosException('POS sale not found.');
        }
        if ($order['status'] === 'cancelled') {
            throw new PosException('This sale is already voided.');
        }
        if (pos_returned_qty_map($pdo, $orderId, true) !== []) {
            throw new PosException('This sale already has returns and cannot be voided. Process remaining items as a return.');
        }
        $st = $pdo->prepare('SELECT * FROM order_items WHERE order_id = ? ORDER BY product_id ASC');
        $st->execute([$orderId]);
        $items = $st->fetchAll();

        $inc = $pdo->prepare('UPDATE products SET stock = stock + ? WHERE id = ?');
        $get = $pdo->prepare('SELECT stock FROM products WHERE id = ?');
        $log = $pdo->prepare("INSERT INTO inventory_logs (product_id, admin_id, type, quantity, remaining_stock, note, created_at) VALUES (?, ?, 'stock_in', ?, ?, ?, NOW())");
        foreach ($items as $it) {
            $inc->execute([$it['quantity'], $it['product_id']]);
            $get->execute([$it['product_id']]);
            $log->execute([$it['product_id'], $adminId, $it['quantity'], $get->fetchColumn(), "VOID {$order['order_number']}"]);
        }

        // Reverse wallet usage + reward points for registered customers
        $mk = pos_parse_marker($order['note']) ?? [];
        $walkin = pos_walkin_customer_id($pdo);
        if ((int) $order['user_id'] !== $walkin) {
            if ((float) ($mk['wallet'] ?? 0) > 0) {
                $pdo->prepare('UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?')->execute([(float) $mk['wallet'], $order['user_id']]);
            }
            $pts = (int) floor((float) $order['total_amount'] / 100);
            if ($pts > 0) {
                $pdo->prepare('UPDATE users SET reward_points = GREATEST(0, reward_points - ?) WHERE id = ?')->execute([$pts, $order['user_id']]);
            }
        }

        $pdo->prepare("UPDATE orders SET status = 'cancelled', payment_status = 'refunded', note = CONCAT(COALESCE(note,''), ?) WHERE id = ?")
            ->execute(["\n[VOID by admin #{$adminId}: {$reason}]", $orderId]);
        $pdo->prepare("INSERT INTO transactions (type, category_id, amount, reference, payment_method, reconciled, created_at) VALUES ('expense', NULL, ?, ?, 'void_reversal', 1, NOW())")
            ->execute([(float) $order['total_amount'], "VOID reversal of {$order['order_number']}: {$reason}"]);
        $pdo->commit();
        return ['order_number' => $order['order_number'], 'total' => (float) $order['total_amount']];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
