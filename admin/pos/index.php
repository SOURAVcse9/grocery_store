<?php
/**
 * ==========================================================================
 * admin/pos/index.php — Enterprise Supermarket POS Terminal Interface
 * ==========================================================================
 */

declare(strict_types=1);

$pageTitle = 'Enterprise POS Terminal — GroCo Supermarket';
require_once __DIR__ . '/../layouts/dashboard_layout.php';
require_admin_permission('pos.access');

$pdo = db();
$adminId = current_admin_id();

// 1. Check for active cash register shift
try {
    $stmt = $pdo->prepare("
        SELECT ps.*, s.name AS store_name, s.code AS store_code, r.name AS register_name, t.name AS terminal_name
        FROM pos_shifts ps
        LEFT JOIN pos_stores s ON s.id = ps.store_id
        LEFT JOIN pos_registers r ON r.id = ps.register_id
        LEFT JOIN pos_terminals t ON t.id = ps.terminal_id
        WHERE ps.admin_id = ? AND ps.status = 'open' 
        LIMIT 1
    ");
    $stmt->execute([$adminId]);
    $activeShift = $stmt->fetch();
} catch (PDOException $e) {
    error_log('[admin/pos/index] shift load failed: ' . $e->getMessage());
    $activeShift = null;
}

// Fetch master selection arrays
try {
    $categories = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC")->fetchAll();
    $brands = $pdo->query("SELECT id, name FROM brands ORDER BY name ASC")->fetchAll();
    $stores = $pdo->query("SELECT id, name, code FROM pos_stores WHERE is_active = 1 ORDER BY id ASC")->fetchAll();
    $registers = $pdo->query("SELECT id, name, register_number FROM pos_registers WHERE is_active = 1 ORDER BY id ASC")->fetchAll();
    $terminals = $pdo->query("SELECT id, name, terminal_code FROM pos_terminals WHERE is_active = 1 ORDER BY id ASC")->fetchAll();
    
    // Load products with active stock
    $products = $pdo->query("
        SELECT p.id, p.name, p.price, p.discount_price, p.stock, p.sku, p.barcode, p.unit, 
               (CASE WHEN p.unit IN ('kg', 'gm', 'liter', 'ml') THEN 1 ELSE 0 END) AS is_weighted, 
               p.thumbnail AS image, c.id AS category_id, b.id AS brand_id
        FROM products p
        LEFT JOIN categories c ON c.id = p.category_id
        LEFT JOIN brands b ON b.id = p.brand_id
        WHERE p.deleted_at IS NULL AND p.is_active = 1 AND p.stock > 0
        ORDER BY p.name ASC
    ")->fetchAll();

    // Ensure default Walk-in Customer exists in the database
    $walkinCheck = $pdo->prepare("SELECT id, full_name, phone, wallet_balance, reward_points FROM users WHERE phone = '00000000000' LIMIT 1");
    $walkinCheck->execute();
    $defaultWalkin = $walkinCheck->fetch();
    
    if (!$defaultWalkin) {
        $password = password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT);
        $stmtIns = $pdo->prepare("
            INSERT INTO users (role_id, full_name, email, phone, password, is_verified, is_active, created_at, updated_at) 
            VALUES (2, 'Walk-in Customer', 'walkin@grocery.store', '00000000000', ?, 1, 1, NOW(), NOW())
        ");
        $stmtIns->execute([$password]);
        $walkinId = (int)$pdo->lastInsertId();
        $defaultWalkin = ['id' => $walkinId, 'full_name' => 'Walk-in Customer', 'phone' => '00000000000', 'wallet_balance' => 0.00, 'reward_points' => 0];
    }

    $currentAdminName = $_SESSION['admin_user']['full_name'] ?? 'Counter Cashier';

} catch (PDOException $e) {
    error_log('[admin/pos/index] option loading failed: ' . $e->getMessage());
    $categories = $brands = $products = $stores = $registers = $terminals = [];
    $defaultWalkin = ['id' => 0, 'full_name' => 'Walk-in Customer', 'phone' => '00000000000', 'wallet_balance' => 0.00, 'reward_points' => 0];
    $currentAdminName = 'Cashier';
}
?>

<!-- ENTERPRISE SUPERMARKET TOP BAR -->
<div class="pos-top-bar dashboard-card" style="padding: 10px 16px; margin-bottom: 12px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; border-radius: 12px; background: var(--color-surface); border: 1px solid var(--color-border); box-shadow: 0 1px 4px rgba(0,0,0,0.05);">
    <div style="display: flex; align-items: center; gap: 14px; flex-wrap: wrap;">
        <div style="display: flex; align-items: center; gap: 8px;">
            <i class="fas fa-store-alt" style="font-size: 20px; color: var(--admin-color-primary);"></i>
            <div>
                <strong style="font-size: 13px; color: var(--color-text); display: block; line-height: 1.2;">
                    <?= e($activeShift['store_name'] ?? ($stores[0]['name'] ?? 'GroCo Main Superstore')) ?>
                </strong>
                <span style="font-size: 10px; color: var(--color-text-muted);">
                    <?= e($activeShift['store_code'] ?? ($stores[0]['code'] ?? 'STORE-MAIN')) ?>
                </span>
            </div>
        </div>

        <div style="height: 24px; width: 1px; background: var(--color-border);" class="d-none d-md-block"></div>

        <div style="display: flex; align-items: center; gap: 12px; font-size: 11px; color: var(--color-text-muted); flex-wrap: wrap;">
            <span><i class="fas fa-desktop" style="margin-right: 4px; color: #4dabf7;"></i><strong><?= e($activeShift['register_name'] ?? 'REG-01') ?></strong> / <strong><?= e($activeShift['terminal_name'] ?? 'TERM-01') ?></strong></span>
            <span><i class="fas fa-user-circle" style="margin-right: 4px; color: #51cf66;"></i>Cashier: <strong><?= e($currentAdminName) ?></strong></span>
            <?php if ($activeShift): ?>
                <span class="badge" style="background: rgba(64, 192, 87, 0.15); color: #2b8a3e; font-size: 10px; font-weight: 700; padding: 4px 8px; border-radius: 6px;">
                    <i class="fas fa-lock-open" style="margin-right: 3px;"></i> Shift #<?= $activeShift['id'] ?> (Open)
                </span>
            <?php else: ?>
                <span class="badge" style="background: rgba(240, 62, 62, 0.15); color: #c92a2a; font-size: 10px; font-weight: 700; padding: 4px 8px; border-radius: 6px;">
                    <i class="fas fa-lock" style="margin-right: 3px;"></i> Drawer Closed
                </span>
            <?php endif; ?>
        </div>
    </div>

    <!-- Network Connection Status & Quick Action Buttons -->
    <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
        <!-- Online/Offline Badge -->
        <span id="posNetworkBadge" style="display: inline-flex; align-items: center; gap: 5px; font-size: 10px; font-weight: 700; padding: 4px 8px; border-radius: 20px; background: #e6fcf5; color: #0ca678; border: 1px solid #c3fae8;">
            <span style="width: 7px; height: 7px; border-radius: 50%; background: #0ca678; display: inline-block;"></span> Online
        </span>
        <span id="posOfflineQueueBadge" style="display: none; font-size: 10px; font-weight: 700; padding: 4px 8px; border-radius: 20px; background: #fff3bf; color: #f08c00; border: 1px solid #ffe066; cursor: pointer;" onclick="syncOfflineQueueManually();">
            <i class="fas fa-sync fa-spin"></i> <span id="posOfflineCount">0</span> Pending Sync
        </span>

        <?php if ($activeShift): ?>
            <button type="button" class="btn btn-secondary" onclick="openPettyCashModal();" style="padding: 5px 10px; font-size: 11px; border-radius: var(--radius-pill); font-weight: 700;" title="Petty Cash In / Out (F8)"><i class="fas fa-exchange-alt"></i> Cash In/Out</button>
            <button type="button" class="btn btn-secondary" onclick="openReturnModal();" style="padding: 5px 10px; font-size: 11px; border-radius: var(--radius-pill); font-weight: 700;" title="Returns & Refunds"><i class="fas fa-undo-alt"></i> Return</button>
            <a href="hold-orders.php" class="btn btn-secondary" style="padding: 5px 10px; font-size: 11px; border-radius: var(--radius-pill); font-weight: 700;"><i class="fas fa-hand-holding"></i> Suspended</a>
            <a href="receipts.php" class="btn btn-secondary" style="padding: 5px 10px; font-size: 11px; border-radius: var(--radius-pill); font-weight: 700;"><i class="fas fa-receipt"></i> Invoices</a>
            <a href="register.php" class="btn btn-secondary" style="padding: 5px 10px; font-size: 11px; border-radius: var(--radius-pill); font-weight: 700;"><i class="fas fa-cash-register"></i> Drawer / Z-Report</a>
        <?php endif; ?>
    </div>
</div>

<?php if (!$activeShift): ?>
    <!-- Open Cash Register drawer form -->
    <div class="dashboard-card" style="max-width: 500px; padding:var(--space-6); margin: 40px auto 0 auto; border-radius:16px;">
        <div style="text-align:center; margin-bottom:20px;">
            <i class="fas fa-cash-register" style="font-size:48px; color:var(--admin-color-primary); margin-bottom:12px;"></i>
            <h3 style="font-size:18px; font-weight:800; margin:0;">Open Cashier Register Shift</h3>
            <p style="font-size:13px; color:var(--color-text-muted); margin:4px 0 0 0;">An active shift is required to perform sales operations.</p>
        </div>
        
        <form method="post" action="register.php" class="auth-form">
            <?= csrf_field() ?>
            <input type="hidden" name="pos_action" value="open_shift">
            
            <div class="form-field-group" style="margin-bottom:14px;">
                <label style="font-weight:700; display:block; margin-bottom:6px; font-size:12px;">Store Location *</label>
                <select name="store_id" required style="width:100%; padding:10px 12px; border:1.5px solid var(--color-border); border-radius:var(--radius-sm); font-size:13px; outline:none; background:var(--color-surface); color:var(--color-text);">
                    <?php foreach ($stores as $s): ?>
                        <option value="<?= $s['id'] ?>"><?= e($s['name']) ?> (<?= e($s['code']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:14px;">
                <div class="form-field-group">
                    <label style="font-weight:700; display:block; margin-bottom:6px; font-size:12px;">Register Station *</label>
                    <select name="register_id" required style="width:100%; padding:10px 12px; border:1.5px solid var(--color-border); border-radius:var(--radius-sm); font-size:13px; outline:none; background:var(--color-surface); color:var(--color-text);">
                        <?php foreach ($registers as $r): ?>
                            <option value="<?= $r['id'] ?>"><?= e($r['name']) ?> (<?= e($r['register_number']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-field-group">
                    <label style="font-weight:700; display:block; margin-bottom:6px; font-size:12px;">POS Terminal *</label>
                    <select name="terminal_id" required style="width:100%; padding:10px 12px; border:1.5px solid var(--color-border); border-radius:var(--radius-sm); font-size:13px; outline:none; background:var(--color-surface); color:var(--color-text);">
                        <?php foreach ($terminals as $t): ?>
                            <option value="<?= $t['id'] ?>"><?= e($t['name']) ?> (<?= e($t['terminal_code']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-field-group" style="margin-bottom:14px;">
                <label style="font-weight:700; display:block; margin-bottom:6px; font-size:12px;">Opening Drawer Cash (৳) *</label>
                <input type="number" name="opening_cash" step="0.01" value="1000.00" required style="width:100%; padding:10px 12px; border:1.5px solid var(--color-border); border-radius:var(--radius-sm); font-size:13px; outline:none;">
            </div>

            <div class="form-field-group" style="margin-bottom:20px;">
                <label style="font-weight:700; display:block; margin-bottom:6px; font-size:12px;">Shift Notes</label>
                <textarea name="notes" placeholder="E.g. Morning Shift opening drawer change..." style="width:100%; padding:10px 12px; border:1.5px solid var(--color-border); border-radius:var(--radius-sm); font-size:13px; outline:none; font-family:inherit; resize:vertical;"></textarea>
            </div>

            <button type="submit" class="btn btn-primary" style="width:100%; border:none; border-radius:var(--radius-pill); font-weight:700; padding:12px; font-size:13px; background-color: var(--admin-color-primary); color: #fff;"><i class="fas fa-lock-open"></i> Initialize Shift Drawer</button>
        </form>
    </div>
<?php else: ?>
    <!-- Touchscreen responsive layout (1366x768 & 1920x1080 optimized) -->
    <div style="display:grid; grid-template-columns: 1.4fr 1.1fr; gap:var(--space-4);" class="admin-dashboard-layout">
        
        <!-- Left: Products Catalog & Barcode Fast Scanner -->
        <div style="display:flex; flex-direction:column; gap:10px;">
            <!-- Filter & Barcode toolbar -->
            <div class="dashboard-card" style="padding:10px 14px; margin:0; display:flex; gap:10px; flex-wrap:wrap; position:relative;">
                <div style="position:relative; flex:2; display:flex; align-items:center;">
                    <i class="fas fa-barcode" style="position:absolute; left:10px; color:var(--admin-color-primary); font-size:14px;"></i>
                    <input type="text" id="posFilterSearch" autocomplete="off" placeholder="Scan Barcode / SKU or Search product (F1/F2)..." onkeyup="filterPOSCatalog();" style="width:100%; padding:8px 12px 8px 32px; border:1.5px solid var(--admin-color-primary); border-radius:var(--radius-sm); font-size:13px; outline:none;">
                    <div id="posAutocompleteDropdown" style="display:none; position:absolute; top:100%; left:0; right:0; background:var(--color-surface); border:1px solid var(--color-border); border-radius:var(--radius-sm); box-shadow:var(--shadow-md); z-index:1005; max-height:250px; overflow-y:auto; margin-top:2px;"></div>
                </div>
                
                <select id="posFilterCat" onchange="filterPOSCatalog();" style="flex:1; padding:8px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:12px; outline:none; background:var(--color-surface); color:var(--color-text);">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>"><?= e($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>

                <select id="posFilterBrand" onchange="filterPOSCatalog();" style="flex:1; padding:8px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:12px; outline:none; background:var(--color-surface); color:var(--color-text);">
                    <option value="">All Brands</option>
                    <?php foreach ($brands as $b): ?>
                        <option value="<?= $b['id'] ?>"><?= e($b['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Touch product cells grid -->
            <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(115px, 1fr)); gap:10px; max-height: 520px; overflow-y: auto; padding:4px;" id="posCatalogGrid">
                <?php foreach ($products as $p): 
                    $img = image_url($p['image'], 'products');
                    $activePrice = ($p['discount_price'] !== null && (float)$p['discount_price'] > 0 && (float)$p['discount_price'] < (float)$p['price']) ? (float)$p['discount_price'] : (float)$p['price'];
                    $isWeighted = !empty($p['is_weighted']) || in_array($p['unit'] ?? '', ['kg', 'gm', 'liter'], true);
                    $unitStr = $p['unit'] ?? 'pcs';
                ?>
                    <div class="dashboard-card touch-product-cell" 
                         data-id="<?= $p['id'] ?>"
                         data-name="<?= strtolower($p['name']) ?>" 
                         data-name-original="<?= e($p['name']) ?>"
                         data-sku="<?= strtolower($p['sku'] ?? '') ?>"
                         data-barcode="<?= strtolower($p['barcode'] ?? '') ?>"
                         data-price="<?= $activePrice ?>"
                         data-stock="<?= $p['stock'] ?>"
                         data-unit="<?= e($unitStr) ?>"
                         data-weighted="<?= $isWeighted ? '1' : '0' ?>"
                         data-image="<?= e($img) ?>"
                         data-cat="<?= $p['category_id'] ?: '' ?>"
                         data-brand="<?= $p['brand_id'] ?: '' ?>"
                         onclick="addTouchCartItem(<?= $p['id'] ?>, '<?= e(addslashes($p['name'])) ?>', <?= $activePrice ?>, <?= $p['stock'] ?>, '<?= e($img) ?>', '<?= e($p['sku'] ?? '') ?>', '<?= e($unitStr) ?>', <?= $isWeighted ? 'true' : 'false' ?>);" 
                         style="padding:8px; text-align:center; cursor:pointer; margin:0; transition: transform 0.1s; position:relative; border-radius:8px;">
                        
                        <?php if ($isWeighted): ?>
                            <span style="position:absolute; top:4px; right:4px; font-size:8px; background:#4dabf7; color:#fff; padding:1px 4px; border-radius:4px; font-weight:700;">Scale</span>
                        <?php endif; ?>

                        <div style="width:100%; height:60px; border-radius:4px; overflow:hidden; border:1px solid var(--color-border); background:var(--color-surface); margin-bottom:4px;">
                            <img src="<?= e($img) ?>" alt="" style="width:100%; height:100%; object-fit:cover;" loading="lazy">
                        </div>
                        <strong style="font-size:11px; color:var(--color-text); display:block; height:28px; overflow:hidden; line-height:14px; margin-bottom:2px;"><?= e($p['name']) ?></strong>
                        <span style="font-size:12px; font-weight:800; color:var(--color-primary);">৳<?= number_format($activePrice, 2) ?></span>
                        <span style="font-size:9px; color:var(--color-text-muted);">/<?= e($unitStr) ?></span><br>
                        <span style="font-size:9px; color:var(--color-text-faint);">Stock: <?= $p['stock'] ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Right: Real-time Cart, Customer Loyalty, and Payment Controls -->
        <div class="dashboard-card" style="padding:14px; margin:0; display:flex; flex-direction:column; height: 600px; justify-content:space-between; border-radius:12px;">
            
            <!-- Customer Bar & Cart Items -->
            <div>
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px; border-bottom:1px solid var(--color-border); padding-bottom:6px; flex-wrap:wrap; gap:6px;">
                    <div>
                        <h3 style="font-size:13px; font-weight:800; margin:0;"><i class="fas fa-shopping-cart"></i> Active Cart</h3>
                        <span id="posCurrentCustomerLabel" style="font-size:10px; color:var(--color-primary); font-weight:700;">Walk-in Customer</span>
                    </div>
                    <!-- Customer search input and dropdown -->
                    <div style="position:relative; width:170px;">
                        <input type="text" id="posCustomerSearch" placeholder="Customer Phone/Name (F3)..." autocomplete="off" style="width:100%; padding:4px 8px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:11px; outline:none;">
                        <input type="hidden" id="posCustomerSelect" 
                               value="<?= $defaultWalkin['id'] ?>" 
                               data-wallet="<?= $defaultWalkin['wallet_balance'] ?>" 
                               data-points="<?= $defaultWalkin['reward_points'] ?>" 
                               data-name="Walk-in Customer"
                               data-default-id="<?= $defaultWalkin['id'] ?>"
                               data-default-wallet="<?= $defaultWalkin['wallet_balance'] ?>"
                               data-default-points="<?= $defaultWalkin['reward_points'] ?>"
                               data-default-name="Walk-in Customer">
                        <div id="posCustomerAutocomplete" style="display:none; position:absolute; top:100%; right:0; width:220px; background:var(--color-surface); border:1px solid var(--color-border); border-radius:var(--radius-sm); box-shadow:var(--shadow-md); z-index:1006; max-height:200px; overflow-y:auto; margin-top:2px;"></div>
                    </div>
                </div>

                <!-- Customer loyalty status banner -->
                <div id="loyaltyWidget" style="display:none; background:rgba(92,124,250,0.08); padding:6px 10px; border-radius:var(--radius-sm); font-size:11px; margin-bottom:8px; justify-content:space-between;">
                    <span>Wallet: <strong id="lblWallet">৳0.00</strong></span>
                    <span>Points: <strong id="lblPoints">0 pts</strong></span>
                </div>

                <!-- Active cart items scroll area -->
                <div id="posActiveCartList" style="max-height: 230px; overflow-y: auto; display:flex; flex-direction:column; gap:6px; margin-bottom:8px; border-bottom:1px dashed var(--color-border); padding-bottom:6px;">
                    <p style="text-align:center; color:var(--color-text-faint); font-size:11px; margin:20px 0;">Cart is empty. Scan barcode or click items to add.</p>
                </div>
            </div>

            <!-- Pricing Summary, Suspends, and Checkout CTA -->
            <div>
                <div style="font-size:12px; color:var(--color-text-muted); display:flex; flex-direction:column; gap:4px; margin-bottom:10px;">
                    <div style="display:flex; justify-content:space-between;">
                        <span>Subtotal:</span>
                        <strong id="posCartSubtotal">৳0.00</strong>
                    </div>

                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span>Discount Override (৳):</span>
                        <input type="number" id="posCartDiscount" min="0" step="0.5" value="0" onchange="recalculatePOSBalances();" onkeyup="recalculatePOSBalances();" style="width:75px; padding:2px 6px; border:1px solid var(--color-border); border-radius:var(--radius-sm); text-align:right; font-size:11px;">
                    </div>

                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span>Coupon Discount (৳):</span>
                        <input type="number" id="posCartCoupon" min="0" step="0.5" value="0" onchange="recalculatePOSBalances();" onkeyup="recalculatePOSBalances();" style="width:75px; padding:2px 6px; border:1px solid var(--color-border); border-radius:var(--radius-sm); text-align:right; font-size:11px;">
                    </div>

                    <div style="display:flex; justify-content:space-between; border-top:1px dashed var(--color-border); padding-top:4px; font-size:14px; color:var(--color-text);">
                        <span>Total Payable:</span>
                        <strong id="posCartTotalPayable" style="color:var(--color-primary); font-size:16px;">৳0.00</strong>
                    </div>
                </div>

                <div style="display:grid; grid-template-columns: 1fr 2fr; gap:8px;">
                    <button type="button" onclick="suspendPOSCart();" class="btn btn-secondary" style="border-radius:var(--radius-pill); font-weight:700; padding:10px; font-size:12px;" title="Hold / Park Cart (F5)"><i class="fas fa-hand-holding"></i> Hold (F5)</button>
                    <button type="button" id="btnPOSCheckoutTrigger" disabled onclick="checkoutProcess();" class="btn btn-primary" style="border-radius:var(--radius-pill); font-weight:700; padding:10px; font-size:13px;" title="Split Payment & Receipt (F9/F10)"><i class="fas fa-shopping-bag"></i> Pay & Print (F9)</button>
                </div>
            </div>

        </div>
    </div>

    <!-- DOCKED KEYBOARD SHORTCUTS FOOTER BAR -->
    <div style="margin-top: 12px; padding: 6px 14px; background: var(--color-surface); border: 1px solid var(--color-border); border-radius: 8px; font-size: 10.5px; color: var(--color-text-muted); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
        <span style="font-weight: 700; color: var(--color-text);"><i class="fas fa-keyboard" style="margin-right: 4px;"></i> Shortcuts:</span>
        <span><kbd style="background: #e9ecef; color: #333; padding: 1px 5px; border-radius: 3px; font-size: 9.5px;">F1/F2</kbd> Scanner</span>
        <span><kbd style="background: #e9ecef; color: #333; padding: 1px 5px; border-radius: 3px; font-size: 9.5px;">F3</kbd> Cust Search</span>
        <span><kbd style="background: #e9ecef; color: #333; padding: 1px 5px; border-radius: 3px; font-size: 9.5px;">F4</kbd> New Cust</span>
        <span><kbd style="background: #e9ecef; color: #333; padding: 1px 5px; border-radius: 3px; font-size: 9.5px;">F5</kbd> Hold Cart</span>
        <span><kbd style="background: #e9ecef; color: #333; padding: 1px 5px; border-radius: 3px; font-size: 9.5px;">F7</kbd> Discount</span>
        <span><kbd style="background: #e9ecef; color: #333; padding: 1px 5px; border-radius: 3px; font-size: 9.5px;">F8</kbd> Cash In/Out</span>
        <span><kbd style="background: #e9ecef; color: #333; padding: 1px 5px; border-radius: 3px; font-size: 9.5px;">F9</kbd> Pay Modal</span>
        <span><kbd style="background: #e9ecef; color: #333; padding: 1px 5px; border-radius: 3px; font-size: 9.5px;">F10</kbd> Complete</span>
        <span><kbd style="background: #e9ecef; color: #333; padding: 1px 5px; border-radius: 3px; font-size: 9.5px;">ESC</kbd> Clear</span>
    </div>
<?php endif; ?>

<!-- Checkout Split Payment Modal with Quick Cash Buttons -->
<div class="modal fade" id="checkoutPaymentModal" tabindex="-1" aria-hidden="true" style="z-index: 1055;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:var(--radius-md); border:none; box-shadow:var(--shadow-lg); background:var(--color-surface);">
            <div class="modal-header" style="border-bottom:1px solid var(--color-border); padding:14px 20px;">
                <h5 class="modal-title" style="font-weight:800; font-size:15px; color:var(--color-text); margin:0;"><i class="fas fa-credit-card"></i> Split Payment Terminal</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="font-size:12px; border:none; background:transparent; cursor:pointer;"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body" style="padding:16px 20px;">
                <!-- Summary of Payable -->
                <div style="background:rgba(92,124,250,0.06); padding:10px 14px; border-radius:var(--radius-sm); margin-bottom:12px; display:flex; flex-direction:column; gap:4px; font-size:11.5px; color:var(--color-text-muted);">
                    <div style="display:flex; justify-content:space-between;">
                        <span>Customer:</span>
                        <span id="modalCustomerInfo" style="font-weight:700; color:var(--color-text);">Walk-in Customer</span>
                    </div>
                    <div style="display:flex; justify-content:space-between;">
                        <span>Subtotal:</span>
                        <span id="modalSubtotal" style="font-weight:700; color:var(--color-text);">৳0.00</span>
                    </div>
                    <div style="display:flex; justify-content:space-between;">
                        <span>Total Discounts:</span>
                        <span id="modalDiscount" style="font-weight:700; color:#e03131;">৳0.00</span>
                    </div>
                    <div style="display:flex; justify-content:space-between; border-top:1px dashed var(--color-border); padding-top:4px; font-size:13px; font-weight:800; color:var(--color-text);">
                        <span>Grand Total Payable:</span>
                        <strong id="modalPayableTotal" style="color:var(--color-primary); font-size:15px;">৳0.00</strong>
                    </div>
                </div>

                <!-- Quick Tender Preset Cash Buttons -->
                <div style="margin-bottom:12px;">
                    <span style="font-size:10px; font-weight:700; color:var(--color-text-muted); display:block; margin-bottom:4px;">Quick Cash Tender Presets:</span>
                    <div style="display:flex; gap:6px; flex-wrap:wrap;">
                        <button type="button" class="btn btn-secondary" onclick="applyQuickCash('exact');" style="padding:3px 8px; font-size:10px; border-radius:4px; font-weight:700;">Exact</button>
                        <button type="button" class="btn btn-secondary" onclick="applyQuickCash(50);" style="padding:3px 8px; font-size:10px; border-radius:4px; font-weight:700;">+৳50</button>
                        <button type="button" class="btn btn-secondary" onclick="applyQuickCash(100);" style="padding:3px 8px; font-size:10px; border-radius:4px; font-weight:700;">+৳100</button>
                        <button type="button" class="btn btn-secondary" onclick="applyQuickCash(500);" style="padding:3px 8px; font-size:10px; border-radius:4px; font-weight:700;">+৳500</button>
                        <button type="button" class="btn btn-secondary" onclick="applyQuickCash(1000);" style="padding:3px 8px; font-size:10px; border-radius:4px; font-weight:700;">+৳1000</button>
                    </div>
                </div>

                <form id="frmPOSPayment" onsubmit="event.preventDefault(); confirmPOSSale();">
                    <div style="display:flex; flex-direction:column; gap:10px;">
                        <!-- Cash Row -->
                        <div style="display:grid; grid-template-columns: 1.4fr 2fr; gap:8px; align-items:center;">
                            <label style="font-size:11.5px; font-weight:700; color:var(--color-text);"><i class="fas fa-money-bill-wave" style="color:#40c057;"></i> Cash (৳)</label>
                            <input type="number" id="splitCash" min="0" step="0.01" value="0" class="form-control" style="font-size:12px; text-align:right;">
                        </div>

                        <!-- Card Row -->
                        <div style="display:grid; grid-template-columns: 1.4fr 2fr; gap:8px; align-items:center;">
                            <label style="font-size:11.5px; font-weight:700; color:var(--color-text);"><i class="fas fa-credit-card" style="color:#228be6;"></i> Card (৳)</label>
                            <input type="number" id="splitCard" min="0" step="0.01" value="0" class="form-control" style="font-size:12px; text-align:right;">
                        </div>
                        <div id="cardDetailsRow" style="display:none; flex-direction:column; gap:6px; background:#f8f9fa; padding:8px; border-radius:var(--radius-sm); border:1px solid var(--color-border);">
                            <div style="display:grid; grid-template-columns: 1.4fr 2fr; gap:8px; align-items:center;">
                                <label style="font-size:10px; font-weight:700; color:var(--color-text-muted);">Card Type</label>
                                <select id="splitCardType" class="form-control" style="font-size:11px; padding:3px 6px;">
                                    <option value="Visa">Visa</option>
                                    <option value="MasterCard">MasterCard</option>
                                    <option value="Amex">Amex</option>
                                    <option value="Nexus">Nexus</option>
                                </select>
                            </div>
                            <div style="display:grid; grid-template-columns: 1.4fr 2fr; gap:8px; align-items:center;">
                                <label style="font-size:10px; font-weight:700; color:var(--color-text-muted);">Last 4 Digits</label>
                                <input type="text" id="splitCardNo" placeholder="E.g. 4321" class="form-control" style="font-size:11px; padding:3px 6px;">
                            </div>
                            <div style="display:grid; grid-template-columns: 1.4fr 2fr; gap:8px; align-items:center;">
                                <label style="font-size:10px; font-weight:700; color:var(--color-text-muted);">Bank / Ref</label>
                                <input type="text" id="splitCardBank" placeholder="E.g. DBBL / Ref #" class="form-control" style="font-size:11px; padding:3px 6px;">
                            </div>
                        </div>

                        <!-- Mobile Banking Row (bKash / Nagad / Rocket) -->
                        <div style="display:grid; grid-template-columns: 1.4fr 2fr; gap:8px; align-items:center;">
                            <label style="font-size:11.5px; font-weight:700; color:var(--color-text);"><i class="fas fa-mobile-alt" style="color:#e64980;"></i> Mobile Banking (৳)</label>
                            <input type="number" id="splitBkash" min="0" step="0.01" value="0" class="form-control" style="font-size:12px; text-align:right;">
                        </div>
                        <div id="mobileDetailsRow" style="display:none; flex-direction:column; gap:6px; background:#f8f9fa; padding:8px; border-radius:var(--radius-sm); border:1px solid var(--color-border);">
                            <div style="display:grid; grid-template-columns: 1.4fr 2fr; gap:8px; align-items:center;">
                                <label style="font-size:10px; font-weight:700; color:var(--color-text-muted);">Provider</label>
                                <select id="splitMobileProvider" class="form-control" style="font-size:11px; padding:3px 6px;">
                                    <option value="bKash">bKash</option>
                                    <option value="Nagad">Nagad</option>
                                    <option value="Rocket">Rocket</option>
                                </select>
                            </div>
                            <div style="display:grid; grid-template-columns: 1.4fr 2fr; gap:8px; align-items:center;">
                                <label style="font-size:10px; font-weight:700; color:var(--color-text-muted);">Trx ID *</label>
                                <input type="text" id="splitBkashTxnId" placeholder="E.g. 9B28X1A" class="form-control" style="font-size:11px; padding:3px 6px;">
                            </div>
                        </div>

                        <!-- Customer Wallet Balance -->
                        <div style="display:grid; grid-template-columns: 1.4fr 2fr; gap:8px; align-items:center;">
                            <label style="font-size:11.5px; font-weight:700; color:var(--color-text);"><i class="fas fa-wallet" style="color:#fcc419;"></i> Wallet Credit (৳)</label>
                            <input type="number" id="splitWallet" min="0" step="0.01" value="0" class="form-control" style="font-size:12px; text-align:right;">
                        </div>

                        <!-- Bank Transfer -->
                        <div style="display:grid; grid-template-columns: 1.4fr 2fr; gap:8px; align-items:center;">
                            <label style="font-size:11.5px; font-weight:700; color:var(--color-text);"><i class="fas fa-university" style="color:#7950f2;"></i> Bank Transfer (৳)</label>
                            <input type="number" id="splitBank" min="0" step="0.01" value="0" class="form-control" style="font-size:12px; text-align:right;">
                        </div>
                    </div>

                    <!-- Balance breakdown -->
                    <div style="margin-top:12px; border-top:1px dashed var(--color-border); padding-top:8px; font-size:11.5px; color:var(--color-text-muted); display:flex; flex-direction:column; gap:3px;">
                        <div style="display:flex; justify-content:space-between;">
                            <span>Total Entered:</span>
                            <strong id="modalTotalEntered" style="color:var(--color-text);">৳0.00</strong>
                        </div>
                        <div style="display:flex; justify-content:space-between;">
                            <span>Remaining Due:</span>
                            <strong id="modalRemainingDue" style="color:#e03131;">৳0.00</strong>
                        </div>
                        <div style="display:flex; justify-content:space-between; font-weight:700; font-size:12px; color:var(--color-primary); border-top:1px solid var(--color-border); padding-top:4px; margin-top:2px;">
                            <span>Change Due:</span>
                            <strong id="modalChangeDue">৳0.00</strong>
                        </div>
                    </div>

                    <div style="display:flex; justify-content:end; gap:8px; margin-top:16px;">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="font-size:12px; padding:6px 12px; border-radius:var(--radius-pill); font-weight:700;">Cancel</button>
                        <button type="submit" id="btnConfirmPOSSale" class="btn btn-primary" disabled style="font-size:12px; padding:6px 16px; border-radius:var(--radius-pill); font-weight:700;">Confirm Sale (F10)</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Petty Cash In / Out Modal -->
<div class="modal fade" id="pettyCashModal" tabindex="-1" aria-hidden="true" style="z-index: 1060;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:var(--radius-md); border:none; box-shadow:var(--shadow-lg); background:var(--color-surface);">
            <div class="modal-header" style="border-bottom:1px solid var(--color-border); padding:14px 20px;">
                <h5 class="modal-title" style="font-weight:800; font-size:15px; color:var(--color-text); margin:0;"><i class="fas fa-exchange-alt"></i> Register Petty Cash Movement</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="font-size:12px; border:none; background:transparent; cursor:pointer;"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body" style="padding:16px 20px;">
                <form id="frmPettyCash" onsubmit="event.preventDefault(); submitPettyCashMovement();">
                    <div style="display:flex; flex-direction:column; gap:12px;">
                        <div>
                            <label style="font-size:11px; font-weight:700; color:var(--color-text-muted); display:block; margin-bottom:4px;">Movement Type *</label>
                            <select id="pettyCashType" required style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:13px; outline:none; background:var(--color-surface); color:var(--color-text);">
                                <option value="cash_in">Cash In (Float Addition / Paid-In)</option>
                                <option value="cash_out">Cash Out (Expense / Drawer Drop)</option>
                            </select>
                        </div>
                        <div>
                            <label style="font-size:11px; font-weight:700; color:var(--color-text-muted); display:block; margin-bottom:4px;">Amount (৳) *</label>
                            <input type="number" id="pettyCashAmount" min="1" step="0.5" required placeholder="0.00" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:13px; outline:none;">
                        </div>
                        <div>
                            <label style="font-size:11px; font-weight:700; color:var(--color-text-muted); display:block; margin-bottom:4px;">Reason / Notes *</label>
                            <input type="text" id="pettyCashReason" required placeholder="E.g. Change replenishment, tea snacks expense..." style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:13px; outline:none;">
                        </div>
                    </div>
                    <div style="display:flex; justify-content:end; gap:8px; margin-top:18px;">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="font-size:12px; padding:6px 12px; border-radius:var(--radius-pill); font-weight:700;">Cancel</button>
                        <button type="submit" class="btn btn-primary" style="font-size:12px; padding:6px 16px; border-radius:var(--radius-pill); font-weight:700;">Save Movement</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Return & Refund Modal -->
<div class="modal fade" id="posReturnModal" tabindex="-1" aria-hidden="true" style="z-index: 1060;">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content" style="border-radius:var(--radius-md); border:none; box-shadow:var(--shadow-lg); background:var(--color-surface);">
            <div class="modal-header" style="border-bottom:1px solid var(--color-border); padding:14px 20px;">
                <h5 class="modal-title" style="font-weight:800; font-size:15px; color:var(--color-text); margin:0;"><i class="fas fa-undo-alt"></i> POS Return & Refund Processor</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="font-size:12px; border:none; background:transparent; cursor:pointer;"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body" style="padding:16px 20px;">
                <div style="display:flex; gap:10px; margin-bottom:14px;">
                    <input type="text" id="returnTxnSearch" placeholder="Enter Order Number or Transaction # (e.g. POS-20261004-1234)..." style="flex:1; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:13px; outline:none;">
                    <button type="button" onclick="lookupReturnOrder();" class="btn btn-primary" style="padding:8px 14px; font-size:12px; font-weight:700; border-radius:var(--radius-sm);">Lookup Invoice</button>
                </div>
                
                <div id="returnOrderContainer" style="display:none;">
                    <!-- Injected by JavaScript -->
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Create Customer Modal -->
<div class="modal fade" id="createCustomerModal" tabindex="-1" aria-hidden="true" style="z-index: 1060;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:var(--radius-md); border:none; box-shadow:var(--shadow-lg); background:var(--color-surface);">
            <div class="modal-header" style="border-bottom:1px solid var(--color-border); padding:16px 20px;">
                <h5 class="modal-title" style="font-weight:800; font-size:15px; color:var(--color-text); margin:0;">Create New Customer</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="font-size:12px; border:none; background:transparent; cursor:pointer;"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body" style="padding:20px;">
                <form id="frmCreateCustomer">
                    <div style="display:flex; flex-direction:column; gap:12px;">
                        <div>
                            <label style="font-size:11px; font-weight:700; color:var(--color-text-muted); display:block; margin-bottom:4px; text-align:left;">Full Name *</label>
                            <input type="text" id="custNewName" required placeholder="E.g. Sazzad Hossain" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:13px; outline:none;">
                        </div>
                        <div>
                            <label style="font-size:11px; font-weight:700; color:var(--color-text-muted); display:block; margin-bottom:4px; text-align:left;">Mobile Number *</label>
                            <input type="text" id="custNewPhone" required placeholder="E.g. 01712345678" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:13px; outline:none;">
                        </div>
                        <div>
                            <label style="font-size:11px; font-weight:700; color:var(--color-text-muted); display:block; margin-bottom:4px; text-align:left;">Email (Optional)</label>
                            <input type="email" id="custNewEmail" placeholder="E.g. sazzad@example.com" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:13px; outline:none;">
                        </div>
                        <div style="display:flex; align-items:center; gap:8px; margin-top:4px;">
                            <input type="checkbox" id="custNewLoyalty" checked style="cursor:pointer; width:16px; height:16px;">
                            <label for="custNewLoyalty" style="font-size:12px; font-weight:700; color:var(--color-text); cursor:pointer; margin:0;">Enroll in Loyalty & Rewards Program</label>
                        </div>
                    </div>
                    <div style="display:flex; justify-content:end; gap:8px; margin-top:20px;">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="font-size:12px; padding:6px 12px; border-radius:var(--radius-pill); font-weight:700;">Cancel</button>
                        <button type="submit" class="btn btn-primary" style="font-size:12px; padding:6px 16px; border-radius:var(--radius-pill); font-weight:700;">Save Customer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
window.csrfToken = '<?= csrf_token() ?>';
window.canOverridePrice = <?= has_admin_permission('pos.override') ? 'true' : 'false' ?>;
window.activeShiftId = <?= !empty($activeShift['id']) ? (int)$activeShift['id'] : 0 ?>;
</script>
<script src="<?= BASE_URL ?>/../admin/assets/js/pos.js"></script>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
