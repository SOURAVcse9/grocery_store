<?php
/**
 * ==========================================================================
 * admin/pos/hold-orders.php — Hold and Resume POS Cart Sessions
 * ==========================================================================
 */

declare(strict_types=1);

// NOTE: auth + AJAX handlers must run BEFORE the layout is included. The layout starts output
// buffering and prints the page chrome, which used to be prepended to the JSON replies and made
// Hold/Resume fail in the browser (r.json() parse error).
require_once __DIR__ . '/../../public/dbconnect.php';
require_once __DIR__ . '/../middleware/auth_middleware.php';
require_once __DIR__ . '/../includes/auth_helpers.php';
require_once __DIR__ . '/../includes/pos_lib.php';
require_admin_auth();
require_admin_permission('pos.access');

$pdo = db();
$adminId = current_admin_id();

$canSeeAll = has_admin_permission('pos.manage') || has_admin_permission('pos.override');

/** Normalise a client cart into a safe, minimal structure (never store/echo raw client JSON). */
function pos_hold_clean_cart($raw): array
{
    $d = is_string($raw) ? json_decode($raw, true) : $raw;
    if (!is_array($d) || $d === [] || count($d) > POS_MAX_LINES) {
        throw new PosException('The cart is empty or invalid.');
    }
    $out = [];
    foreach (array_values($d) as $it) {
        if (!is_array($it)) {
            continue;
        }
        $id = (int) ($it['id'] ?? 0);
        $qty = is_numeric($it['qty'] ?? null) ? (float) $it['qty'] : 0.0;
        $price = is_numeric($it['price'] ?? null) ? (float) $it['price'] : -1.0;
        if ($id <= 0 || $qty <= 0 || $qty > POS_MAX_QTY || $price < 0) {
            continue;
        }
        $out[$id] = [
            'id' => $id, 'name' => pos_clip((string) ($it['name'] ?? ('#' . $id)), 200), 'price' => round($price, 2), 'qty' => round($qty, 3),
            'stock' => is_numeric($it['stock'] ?? null) ? (float) $it['stock'] : $qty,
            'sku' => pos_clip((string) ($it['sku'] ?? ''), 60), 'image' => pos_clip((string) ($it['image'] ?? ''), 255),
            'line_discount' => is_numeric($it['line_discount'] ?? null) ? max(0.0, round((float) $it['line_discount'], 2)) : 0.0,
        ];
    }
    if ($out === []) {
        throw new PosException('The cart has no valid items.');
    }
    return $out;
}

// ---- AJAX POST actions: hold / resume / delete (all CSRF-protected, ownership-checked) ----
if (method_is('post') && in_array(input('pos_action', ''), ['hold', 'resume', 'delete'], true)) {
    header('Content-Type: application/json');
    header('Cache-Control: no-store');
    if (!verify_csrf()) {
        http_response_code(419);
        echo json_encode(['success' => false, 'error' => 'CSRF verification failed.']);
        exit;
    }
    $act = input('pos_action', '');
    try {
        if ($act === 'hold') {
            $cart = pos_hold_clean_cart(input('cart_data', '[]'));
            $customerId = (int) input('customer_id', '0');
            $notes = pos_clip((string) input('hold_notes', 'General Suspension'), 250);
            $json = json_encode($cart, JSON_UNESCAPED_UNICODE);
            if (strlen($json) > 200000) {
                throw new PosException('The cart is too large to hold.');
            }
            $pdo->prepare('INSERT INTO pos_hold_orders (admin_id, customer_id, cart_data, hold_notes, created_at) VALUES (?, ?, ?, ?, NOW())')
                ->execute([$adminId, $customerId > 0 ? $customerId : null, $json, $notes]);
            log_admin_activity('pos.hold', 'Held a POS cart (' . count($cart) . ' lines)');
            echo json_encode(['success' => true]);
            exit;
        }

        $holdId = (int) input('id', '0');
        $pdo->beginTransaction();
        $st = $pdo->prepare('SELECT * FROM pos_hold_orders WHERE id = ? FOR UPDATE');
        $st->execute([$holdId]);
        $row = $st->fetch();
        if (!$row || ((int) $row['admin_id'] !== (int) $adminId && !$canSeeAll)) {
            $pdo->rollBack();
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Held cart not found.']);
            exit;
        }
        // Resume = hand the cart to the terminal and remove it from the hold list in ONE step,
        // so the same held basket can never be resumed twice (e.g. by two cashiers).
        $pdo->prepare('DELETE FROM pos_hold_orders WHERE id = ?')->execute([$holdId]);
        $pdo->commit();
        log_admin_activity($act === 'resume' ? 'pos.resume' : 'pos.hold_cancel', ($act === 'resume' ? 'Resumed' : 'Cancelled') . " held cart #{$holdId}");
        if ($act === 'resume') {
            echo json_encode(['success' => true, 'cart' => array_values(pos_hold_clean_cart($row['cart_data'])), 'customer_id' => (int) ($row['customer_id'] ?? 0)]);
        } else {
            echo json_encode(['success' => true]);
        }
        exit;
    } catch (PosException $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        error_log('[admin/pos/hold-orders] failed: ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Held-cart operation failed.']);
        exit;
    }
}

// The old GET ?action=delete link was a CSRF hole (any page could cancel a held cart). Removed.

// Fetch held orders (own only, unless manager)
try {
    $sqlHold = 'SELECT ho.*, u.full_name AS customer_name, a.username AS cashier FROM pos_hold_orders ho
                LEFT JOIN users u ON u.id = ho.customer_id LEFT JOIN admins a ON a.id = ho.admin_id'
             . ($canSeeAll ? '' : ' WHERE ho.admin_id = :aid') . ' ORDER BY ho.created_at DESC LIMIT 200';
    $stHold = $pdo->prepare($sqlHold);
    $stHold->execute($canSeeAll ? [] : [':aid' => $adminId]);
    $holds = $stHold->fetchAll();
} catch (PDOException $e) {
    error_log('[admin/pos/hold-orders] load failed: ' . $e->getMessage());
    $holds = [];
}
$pageTitle = 'Held Carts — GroCo Admin';
require_once __DIR__ . '/../layouts/dashboard_layout.php';
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-5);">
    <div>
        <h1 style="font-size:var(--fs-xl); font-weight:800; color:var(--color-text); margin:0;">Suspended Carts</h1>
        <p style="font-size:var(--fs-sm); color:var(--color-text-muted); margin:4px 0 0 0;">Inspect temporarily held checkout sessions and resume them.</p>
    </div>
    <a href="index.php" class="btn btn-secondary" style="border-radius:var(--radius-pill); font-weight:700;"><i class="fas fa-arrow-left"></i> POS Terminal</a>
</div>

<!-- Alerts -->
<?php if (has_flash('pos_hold_msg')): ?>
    <div style="background:#e6fcf5; border:1px solid #c3fae8; color:#0ca678; padding:12px; border-radius:var(--radius-sm); font-size:var(--fs-sm); font-weight:600; margin-bottom:var(--space-4);">
        <?= flash('pos_hold_msg') ?>
    </div>
<?php endif; ?>

<div class="dashboard-card" style="padding:0; overflow:hidden;">
    <div class="admin-table-wrapper" style="border:none;">
        <table class="admin-data-table" style="font-size:13px;">
            <thead>
                <tr>
                    <th style="padding:16px 20px;">Suspension Note / ID</th>
                    <th style="padding:16px 20px;">Customer Profile</th>
                    <th style="padding:16px 20px; width:220px;">Suspended Date</th>
                    <th style="padding:16px 20px; width:200px; text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($holds)): ?>
                    <?php foreach ($holds as $row): ?>
                        <tr style="border-bottom:1px solid var(--color-border); vertical-align:middle;">
                            <td style="padding:12px 20px;"><strong style="color:var(--color-primary);"><?= e($row['hold_notes'] ?: 'No notes') ?></strong></td>
                            <td style="padding:12px 20px; color:var(--color-text-muted);"><?= e($row['customer_name'] ?: 'Walk-in Customer') ?><br><span style="font-size:11px; color:var(--color-text-faint);">Cashier: <?= e($row['cashier'] ?? '') ?></span></td>
                            <td style="padding:12px 20px; color:var(--color-text-faint);"><?= date('M d, Y H:i', strtotime($row['created_at'])) ?></td>
                            <td style="padding:12px 20px; text-align:right;">
                                <div style="display:inline-flex; gap:6px;">
                                    <button type="button" onclick="holdAction('resume', <?= (int) $row['id'] ?>);" class="btn btn-primary" style="padding:6px 10px; font-size:11px; border-radius:var(--radius-pill);"><i class="fas fa-play"></i> Resume</button>
                                    <button type="button" onclick="if(confirm('Cancel this held cart?')) holdAction('delete', <?= (int) $row['id'] ?>);" class="btn btn-secondary" style="padding:6px 10px; font-size:11px; border-radius:var(--radius-pill); color:#e03131;"><i class="fas fa-trash"></i> Cancel</button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" style="padding:32px; text-align:center; color:var(--color-text-faint);">No suspended carts logged in the registry.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
const HOLD_CSRF = <?= json_encode(csrf_token()) ?>;
function holdAction(action, id) {
    const fd = new FormData();
    fd.append('pos_action', action); fd.append('id', id); fd.append('csrf_token', HOLD_CSRF);
    fetch('hold-orders.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (!res.success) { alert(res.error || 'Operation failed.'); return; }
            if (action !== 'resume') { location.reload(); return; }
            const opener = window.opener && !window.opener.closed ? window.opener : null;
            const payload = { cartData: res.cart, customerId: res.customer_id, holdId: id };
            if (opener && typeof opener.applyResumedCart === 'function') {
                opener.applyResumedCart(payload);
                window.close();
            } else {
                sessionStorage.setItem('groco_pos_resume_cart', JSON.stringify(payload));
                window.location.href = 'index.php';
            }
        })
        .catch(() => alert('Network error. Nothing was changed.'));
}
</script>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>

