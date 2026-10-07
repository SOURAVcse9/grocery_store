<?php
/**
 * ==========================================================================
 * admin/pos/index.php — Touchscreen POS Terminal Interface
 * ==========================================================================
 */

declare(strict_types=1);

$pageTitle = 'Enterprise POS Checkout — GroCo Admin';
require_once __DIR__ . '/../layouts/dashboard_layout.php';
require_admin_permission('pos.access');
require_once __DIR__ . '/../includes/pos_lib.php';

$pdo = db();
$adminId = current_admin_id();

// 1. Check for active cash register shift
try {
    $stmt = $pdo->prepare("SELECT * FROM pos_shifts WHERE admin_id = ? AND status = 'open' LIMIT 1");
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
    
    // Load products with active stock
    $products = $pdo->query("
        SELECT p.id, p.name, p.price, p.stock, p.sku, p.barcode, p.thumbnail AS image, c.id AS category_id, b.id AS brand_id
        FROM products p
        LEFT JOIN categories c ON c.id = p.category_id
        LEFT JOIN brands b ON b.id = p.brand_id
        WHERE p.deleted_at IS NULL AND p.is_active = 1 AND p.stock > 0
        ORDER BY p.name ASC
    ")->fetchAll();

    // Ensure default Walk-in Customer exists in the database
    $walkinCheck = $pdo->prepare("SELECT id FROM users WHERE phone = '00000000000' LIMIT 1");
    $walkinCheck->execute();
    $walkinUser = $walkinCheck->fetch();
    
    if (!$walkinUser) {
        // Create the Walk-in Customer
        $password = password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT);
        $stmtIns = $pdo->prepare("
            INSERT INTO users (role_id, full_name, email, phone, password, is_verified, is_active, created_at, updated_at) 
            VALUES (2, 'Walk-in Customer', 'walkin@grocery.store', '00000000000', ?, 1, 1, NOW(), NOW())
        ");
        $stmtIns->execute([$password]);
        $walkinId = (int)$pdo->lastInsertId();
    } else {
        $walkinId = (int)$walkinUser['id'];
    }

    $walkinDetails = $pdo->prepare("SELECT id, full_name, phone, wallet_balance, reward_points FROM users WHERE id = ? LIMIT 1");
    $walkinDetails->execute([$walkinId]);
    $defaultWalkin = $walkinDetails->fetch();

    // Load registered customers for selection
    $customers = $pdo->query("SELECT id, full_name, phone, wallet_balance, reward_points FROM users WHERE role_id != 1 AND deleted_at IS NULL ORDER BY full_name ASC")->fetchAll();

} catch (PDOException $e) {
    error_log('[admin/pos/index] option loading failed: ' . $e->getMessage());
    $categories = $brands = $products = $customers = [];
    $defaultWalkin = ['id' => 0, 'full_name' => 'Walk-in Customer', 'phone' => '00000000000', 'wallet_balance' => 0.00, 'reward_points' => 0];
}
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-4); flex-wrap:wrap; gap:16px;">
    <div>
        <h1 style="font-size:var(--fs-xl); font-weight:800; color:var(--color-text); margin:0;">POS Terminal</h1>
        <p style="font-size:var(--fs-sm); color:var(--color-text-muted); margin:4px 0 0 0;">Checkout counter interface.</p>
    </div>
    
    <?php if ($activeShift): ?>
        <div style="display:flex; gap:12px; align-items:center;">
            <a href="register.php" class="btn btn-secondary" style="border-radius:var(--radius-pill); font-weight:700;"><i class="fas fa-cash-register"></i> Register drawer</a>
            <a href="hold-orders.php" class="btn btn-secondary" style="border-radius:var(--radius-pill); font-weight:700;"><i class="fas fa-hand-holding"></i> Suspended Carts</a>
        </div>
    <?php endif; ?>
</div>

<?php if (!$activeShift): ?>
    <!-- Open Cash Register drawer form directly integrated -->
    <div class="dashboard-card" style="max-width: 500px; padding:var(--space-6); margin: 40px auto 0 auto; border-radius:16px;">
        <div style="text-align:center; margin-bottom:20px;">
            <i class="fas fa-cash-register" style="font-size:48px; color:var(--admin-color-primary); margin-bottom:12px;"></i>
            <h3 style="font-size:18px; font-weight:800; margin:0;">Open Cashier Register Shift</h3>
            <p style="font-size:13px; color:var(--color-text-muted); margin:4px 0 0 0;">An active shift is required to perform sales operations.</p>
        </div>
        
        <form method="post" action="register.php" class="auth-form">
            <?= csrf_field() ?>
            <input type="hidden" name="pos_action" value="open_shift">
            
            <div class="form-field-group" style="margin-bottom:16px;">
                <label style="font-weight:700; display:block; margin-bottom:6px; font-size:12px;">Opening Drawer Cash (৳) *</label>
                <input type="number" name="opening_cash" step="0.01" value="1000.00" required style="width:100%; padding:10px 12px; border:1.5px solid var(--color-border); border-radius:var(--radius-sm); font-size:13px; outline:none;">
            </div>

            <div class="form-field-group" style="margin-bottom:16px;">
                <label style="font-weight:700; display:block; margin-bottom:6px; font-size:12px;">Register / Station Number *</label>
                <input type="text" name="register_number" value="Register 01" required style="width:100%; padding:10px 12px; border:1.5px solid var(--color-border); border-radius:var(--radius-sm); font-size:13px; outline:none;">
            </div>

            <div class="form-field-group" style="margin-bottom:24px;">
                <label style="font-weight:700; display:block; margin-bottom:6px; font-size:12px;">Shift Opening Notes</label>
                <textarea name="notes" placeholder="E.g. Morning Shift opening drawer change..." style="width:100%; padding:10px 12px; border:1.5px solid var(--color-border); border-radius:var(--radius-sm); font-size:13px; outline:none; font-family:inherit; resize:vertical;"></textarea>
            </div>

            <button type="submit" class="btn btn-primary" style="width:100%; border:none; border-radius:var(--radius-pill); font-weight:700; padding:12px; font-size:13px; background-color: var(--admin-color-primary); color: #fff;"><i class="fas fa-lock-open"></i> Initialize Shift Drawer</button>
        </form>
    </div>
<?php else: ?>
    <!-- Touch screen responsive layout -->
    <div style="display:grid; grid-template-columns: 1.55fr 1fr; gap:var(--space-4);" class="admin-dashboard-layout">
        
        <!-- Left: Products selector block -->
        <div style="display:flex; flex-direction:column; gap:10px;">
            <!-- Retail Departmental Category Filter Pills (Shwapno / Clothing / Grocery) -->
            <div style="display:flex; gap:6px; overflow-x:auto; padding-bottom:4px; scrollbar-width:thin;" id="posDepartmentTabs">
                <button type="button" class="btn btn-sm pos-dept-btn active" data-dept="all" onclick="filterByDepartment('all', this);" style="border-radius:var(--radius-pill); font-size:11px; font-weight:700; white-space:nowrap; padding:6px 14px; background:var(--color-primary); color:#fff; border:none; transition:0.15s;"><i class="fas fa-th-large"></i> All Items</button>
                <button type="button" class="btn btn-sm pos-dept-btn" data-dept="produce" onclick="filterByDepartment('produce', this);" style="border-radius:var(--radius-pill); font-size:11px; font-weight:700; white-space:nowrap; padding:6px 14px; background:var(--color-surface); color:var(--color-text); border:1px solid var(--color-border); transition:0.15s;">🥦 Fresh Produce</button>
                <button type="button" class="btn btn-sm pos-dept-btn" data-dept="dairy_bakery" onclick="filterByDepartment('dairy_bakery', this);" style="border-radius:var(--radius-pill); font-size:11px; font-weight:700; white-space:nowrap; padding:6px 14px; background:var(--color-surface); color:var(--color-text); border:1px solid var(--color-border); transition:0.15s;">🥛 Dairy &amp; Bakery</button>
                <button type="button" class="btn btn-sm pos-dept-btn" data-dept="meat_fish" onclick="filterByDepartment('meat_fish', this);" style="border-radius:var(--radius-pill); font-size:11px; font-weight:700; white-space:nowrap; padding:6px 14px; background:var(--color-surface); color:var(--color-text); border:1px solid var(--color-border); transition:0.15s;">🥩 Meat &amp; Fish</button>
                <button type="button" class="btn btn-sm pos-dept-btn" data-dept="grocery" onclick="filterByDepartment('grocery', this);" style="border-radius:var(--radius-pill); font-size:11px; font-weight:700; white-space:nowrap; padding:6px 14px; background:var(--color-surface); color:var(--color-text); border:1px solid var(--color-border); transition:0.15s;">🌾 Staples &amp; Grocery</button>
                <button type="button" class="btn btn-sm pos-dept-btn" data-dept="clothing" onclick="filterByDepartment('clothing', this);" style="border-radius:var(--radius-pill); font-size:11px; font-weight:700; white-space:nowrap; padding:6px 14px; background:var(--color-surface); color:var(--color-text); border:1px solid var(--color-border); transition:0.15s;">👗 Clothing &amp; Fashion</button>
                <button type="button" class="btn btn-sm pos-dept-btn" data-dept="household" onclick="filterByDepartment('household', this);" style="border-radius:var(--radius-pill); font-size:11px; font-weight:700; white-space:nowrap; padding:6px 14px; background:var(--color-surface); color:var(--color-text); border:1px solid var(--color-border); transition:0.15s;">🧴 Household &amp; Care</button>
                <button type="button" class="btn btn-sm pos-dept-btn" data-dept="snacks_drinks" onclick="filterByDepartment('snacks_drinks', this);" style="border-radius:var(--radius-pill); font-size:11px; font-weight:700; white-space:nowrap; padding:6px 14px; background:var(--color-surface); color:var(--color-text); border:1px solid var(--color-border); transition:0.15s;">🍪 Snacks &amp; Drinks</button>
            </div>

            <!-- Filter toolbar -->
            <div class="dashboard-card" style="padding:10px 12px; margin:0; display:flex; gap:10px; flex-wrap:wrap; position:relative; align-items:center;">
                <div style="position:relative; flex:2; display:flex; align-items:center;">
                    <i class="fas fa-barcode" style="position:absolute; left:12px; color:var(--color-text-muted); font-size:14px;"></i>
                    <input type="text" id="posFilterSearch" autocomplete="off" placeholder="Scan SKU/Barcode or search product (F1)..." onkeyup="filterPOSCatalog();" style="width:100%; padding:8px 12px 8px 34px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:13px; outline:none; background:var(--color-surface); color:var(--color-text);">
                    <div id="posAutocompleteDropdown" style="display:none; position:absolute; top:100%; left:0; right:0; background:var(--color-surface); border:1px solid var(--color-border); border-radius:var(--radius-sm); box-shadow:var(--shadow-md); z-index:1005; max-height:250px; overflow-y:auto; margin-top:2px;"></div>
                </div>
                
                <select id="posFilterCat" onchange="filterPOSCatalog();" style="flex:1; min-width:130px; padding:8px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:12px; outline:none; background:var(--color-surface); color:var(--color-text);">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>"><?= e($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>

                <select id="posFilterBrand" onchange="filterPOSCatalog();" style="flex:1; min-width:110px; padding:8px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:12px; outline:none; background:var(--color-surface); color:var(--color-text);">
                    <option value="">All Brands</option>
                    <?php foreach ($brands as $b): ?>
                        <option value="<?= $b['id'] ?>"><?= e($b['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Touch product cells grid -->
            <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(125px, 1fr)); gap:10px; max-height: 520px; overflow-y: auto; padding:4px;" id="posCatalogGrid">
                <?php foreach ($products as $p): 
                    $img = image_url($p['image'], 'products');
                ?>
                    <div class="dashboard-card touch-product-cell" 
                         data-id="<?= $p['id'] ?>"
                         data-name="<?= strtolower($p['name']) ?>" 
                         data-name-original="<?= e($p['name']) ?>"
                         data-sku="<?= strtolower($p['sku'] ?? '') ?>"
                         data-barcode="<?= strtolower($p['barcode'] ?? '') ?>"
                         data-price="<?= $p['price'] ?>"
                         data-stock="<?= $p['stock'] ?>"
                         data-image="<?= e($img) ?>"
                         data-cat="<?= $p['category_id'] ?: '' ?>"
                         data-brand="<?= $p['brand_id'] ?: '' ?>"
                         onclick="addTouchCartItem(<?= $p['id'] ?>, '<?= e(addslashes($p['name'])) ?>', <?= $p['price'] ?>, <?= $p['stock'] ?>, '<?= e($img) ?>', '<?= e($p['sku'] ?? '') ?>');" 
                         style="padding:10px; text-align:center; cursor:pointer; margin:0; transition: transform 0.1s ease, box-shadow 0.1s ease;">
                        
                        <div style="width:100%; height:72px; border-radius:var(--radius-sm); overflow:hidden; border:1px solid var(--color-border); background:var(--color-surface); margin-bottom:6px;">
                            <img src="<?= e($img) ?>" alt="" style="width:100%; height:100%; object-fit:cover;">
                        </div>
                        <strong style="font-size:11px; color:var(--color-text); display:block; height:32px; overflow:hidden; line-height:16px; margin-bottom:4px;" title="<?= e($p['name']) ?>"><?= e($p['name']) ?></strong>
                        <span style="font-size:12px; font-weight:800; color:var(--color-primary);">৳<?= number_format((float)$p['price'], 2) ?></span><br>
                        <span style="font-size:9px; color:var(--color-text-faint);">Stock: <?= $p['stock'] ?> units</span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Right: Cart controls, Customer loyalty, checkout split -->
        <div class="dashboard-card" style="padding:16px; margin:0; display:flex; flex-direction:column; height: 600px; justify-content:space-between;">
            
            <!-- Cart & Customer Loyalty -->
            <div>
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; border-bottom:1px solid var(--color-border); padding-bottom:8px; flex-wrap:wrap; gap:8px;">
                    <div>
                        <div style="display:flex; align-items:center; gap:6px;">
                            <h3 style="font-size:14px; font-weight:800; margin:0;"><i class="fas fa-shopping-basket"></i> POS Cart</h3>
                            <button type="button" onclick="resetToWalkinCustomer();" title="Reset to Walk-in Customer" class="btn btn-sm" style="font-size:10px; padding:2px 8px; border-radius:var(--radius-pill); background:rgba(0,0,0,0.06); border:1px solid var(--color-border); color:var(--color-text); font-weight:600;"><i class="fas fa-user-slash"></i> Walk-in</button>
                        </div>
                        <span id="posCurrentCustomerLabel" style="font-size:11px; color:var(--color-primary); font-weight:700;">Walk-in Customer</span>
                    </div>
                    <!-- Customer search input, autocomplete, and quick add -->
                    <div style="display:flex; align-items:center; gap:6px;">
                        <div style="position:relative; width:150px;">
                            <input type="text" id="posCustomerSearch" placeholder="Search Customer (F2)..." autocomplete="off" style="width:100%; padding:5px 8px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:11px; outline:none; background:var(--color-surface); color:var(--color-text);">
                            <input type="hidden" id="posCustomerSelect" 
                                   value="<?= $defaultWalkin['id'] ?>" 
                                   data-wallet="<?= $defaultWalkin['wallet_balance'] ?>" 
                                   data-points="<?= $defaultWalkin['reward_points'] ?>" 
                                   data-name="Walk-in Customer"
                                   data-default-id="<?= $defaultWalkin['id'] ?>"
                                   data-default-wallet="<?= $defaultWalkin['wallet_balance'] ?>"
                                   data-default-points="<?= $defaultWalkin['reward_points'] ?>"
                                   data-default-name="Walk-in Customer">
                            <div id="posCustomerAutocomplete" style="display:none; position:absolute; top:100%; right:0; width:240px; background:var(--color-surface); border:1px solid var(--color-border); border-radius:var(--radius-sm); box-shadow:var(--shadow-md); z-index:1006; max-height:220px; overflow-y:auto; margin-top:2px;"></div>
                        </div>
                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#createCustomerModal" style="border-radius:var(--radius-sm); font-size:11px; font-weight:700; padding:5px 8px; white-space:nowrap;" title="Register New Customer (F2)"><i class="fas fa-user-plus"></i></button>
                    </div>
                </div>

                <!-- Customer loyalty widgets status with 1-click action buttons -->
                <div id="loyaltyWidget" style="display:none; background:rgba(92,124,250,0.08); padding:8px 12px; border-radius:var(--radius-sm); font-size:11px; margin-bottom:10px; justify-content:space-between; align-items:center; border:1px solid rgba(92,124,250,0.2);">
                    <div>
                        <span>Wallet: <strong id="lblWallet" style="color:var(--color-primary);">৳0.00</strong></span>
                        <button type="button" onclick="quickApplyWallet();" class="btn btn-sm" style="font-size:9px; padding:1px 6px; margin-left:4px; border-radius:3px; background:#fcc419; color:#000; font-weight:700; border:none;" title="Apply Wallet balance in checkout">Use</button>
                    </div>
                    <div>
                        <span>Points: <strong id="lblPoints" style="color:#e67700;">0 pts</strong></span>
                        <button type="button" onclick="quickRedeemPoints();" class="btn btn-sm" style="font-size:9px; padding:1px 6px; margin-left:4px; border-radius:3px; background:#40c057; color:#fff; font-weight:700; border:none;" title="Redeem reward points as coupon">Redeem</button>
                    </div>
                </div>

                <!-- Active checkout items list -->
                <div id="posActiveCartList" style="max-height: 190px; overflow-y: auto; display:flex; flex-direction:column; gap:6px; margin-bottom:12px; border-bottom:1px dashed var(--color-border); padding-bottom:8px;">
                    <p style="text-align:center; color:var(--color-text-faint); font-size:11px; margin:16px 0;">Checkout list is empty.</p>
                </div>
            </div>

            <!-- Calculations, Quick discounts, Suspends, Checkout buttons -->
            <div>
                <div style="font-size:12px; color:var(--color-text-muted); display:flex; flex-direction:column; gap:5px; margin-bottom:10px;">
                    <div style="display:flex; justify-content:space-between;">
                        <span>Subtotal:</span>
                        <strong id="posCartSubtotal">৳0.00</strong>
                    </div>

                    <!-- Quick discount preset chips for cashiers -->
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span style="font-size:11px;">Discount Preset:</span>
                        <div style="display:flex; gap:3px; flex-wrap:wrap; justify-content:flex-end;">
                            <button type="button" onclick="applyQuickDiscount('pct', 5);" style="border:1px solid var(--color-border); background:var(--color-surface); color:var(--color-text); font-size:9px; font-weight:700; padding:1px 5px; border-radius:3px; cursor:pointer;">5%</button>
                            <button type="button" onclick="applyQuickDiscount('pct', 10);" style="border:1px solid var(--color-border); background:var(--color-surface); color:var(--color-text); font-size:9px; font-weight:700; padding:1px 5px; border-radius:3px; cursor:pointer;">10%</button>
                            <button type="button" onclick="applyQuickDiscount('pct', 15);" style="border:1px solid var(--color-border); background:var(--color-surface); color:var(--color-text); font-size:9px; font-weight:700; padding:1px 5px; border-radius:3px; cursor:pointer;">15%</button>
                            <button type="button" onclick="applyQuickDiscount('fixed', 50);" style="border:1px solid var(--color-border); background:var(--color-surface); color:var(--color-text); font-size:9px; font-weight:700; padding:1px 5px; border-radius:3px; cursor:pointer;">৳50</button>
                            <button type="button" onclick="applyQuickDiscount('fixed', 100);" style="border:1px solid var(--color-border); background:var(--color-surface); color:var(--color-text); font-size:9px; font-weight:700; padding:1px 5px; border-radius:3px; cursor:pointer;">৳100</button>
                            <button type="button" onclick="applyQuickDiscount('fixed', 0);" style="border:1px solid var(--color-border); background:var(--color-surface); color:#e03131; font-size:9px; font-weight:700; padding:1px 5px; border-radius:3px; cursor:pointer;">Clear</button>
                        </div>
                    </div>
                    
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span>Discount Override (৳):</span>
                        <input type="number" id="posCartDiscount" min="0" value="0" onchange="recalculatePOSBalances();" onkeyup="recalculatePOSBalances();" style="width:70px; padding:2px 6px; border:1px solid var(--color-border); border-radius:var(--radius-sm); text-align:right; font-size:11px; background:var(--color-surface); color:var(--color-text);">
                    </div>

                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span>Coupon / Points (৳):</span>
                        <input type="number" id="posCartCoupon" min="0" value="0" onchange="recalculatePOSBalances();" onkeyup="recalculatePOSBalances();" style="width:70px; padding:2px 6px; border:1px solid var(--color-border); border-radius:var(--radius-sm); text-align:right; font-size:11px; background:var(--color-surface); color:var(--color-text);">
                    </div>

                    <div style="display:flex; justify-content:space-between; border-top:1px dashed var(--color-border); padding-top:6px; font-size:14px; color:var(--color-text);">
                        <span>Total Payable Due:</span>
                        <strong id="posCartTotalPayable" style="color:var(--color-primary); font-size:15px;">৳0.00</strong>
                    </div>
                </div>

                <div style="display:grid; grid-template-columns: 1fr 2fr; gap:8px;" class="grid-2">
                    <button type="button" onclick="suspendPOSCart();" class="btn btn-secondary" style="border-radius:var(--radius-pill); font-weight:700; padding:10px; font-size:12px;"><i class="fas fa-hand-holding"></i> Hold (F3)</button>
                    <button type="button" id="btnPOSCheckoutTrigger" disabled onclick="checkoutProcess();" class="btn btn-primary" style="border-radius:var(--radius-pill); font-weight:700; padding:10px; font-size:12px;"><i class="fas fa-cash-register"></i> Pay &amp; Print (F6)</button>
                </div>
            </div>

        </div>
    </div>
<?php endif; ?>

<script>
let touchCart = {};
window.touchCart = touchCart;
window.csrfToken = '<?= csrf_token() ?>';
window.POS_DECIMAL = <?= pos_decimal_qty_enabled($pdo) ? 'true' : 'false' ?>;
window.POS_CAN_DISCOUNT = <?= (has_admin_permission('pos.discount') || has_admin_permission('pos.override')) ? 'true' : 'false' ?>;
window.currentPosDept = 'all';

const DEPT_MAP = {
    produce: [1, 2],
    dairy_bakery: [3, 4],
    meat_fish: [7, 8, 37],
    grocery: [10, 26, 27, 28, 29, 30],
    clothing: [38],
    household: [12, 13, 36],
    snacks_drinks: [5, 6, 31, 32, 33]
};

function filterByDepartment(dept, btn) {
    window.currentPosDept = dept;
    document.querySelectorAll('.pos-dept-btn').forEach(b => {
        b.classList.remove('active');
        b.style.background = 'var(--color-surface)';
        b.style.color = 'var(--color-text)';
        b.style.border = '1px solid var(--color-border)';
    });
    if (btn) {
        btn.classList.add('active');
        btn.style.background = 'var(--color-primary)';
        btn.style.color = '#fff';
        btn.style.border = 'none';
    }
    filterPOSCatalog();
}
window.filterByDepartment = filterByDepartment;

function escHtml(v) { return String(v ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
function clearTouchCart() { Object.keys(touchCart).forEach(k => delete touchCart[k]); renderTouchCart(); }
function lineNet(it) { return Math.max(0, it.price * it.qty - (it.line_discount || 0)); }

function filterPOSCatalog() {
    const searchEl = document.getElementById('posFilterSearch');
    const catEl = document.getElementById('posFilterCat');
    const brandEl = document.getElementById('posFilterBrand');
    if (!searchEl || !catEl || !brandEl) return;
    
    const search = searchEl.value.toLowerCase().trim();
    const cat = catEl.value;
    const brand = brandEl.value;
    const dept = window.currentPosDept || 'all';
    const deptCats = (dept !== 'all' && DEPT_MAP[dept]) ? DEPT_MAP[dept] : null;
    
    const items = document.querySelectorAll('.touch-product-cell');
    items.forEach(el => {
        const name = el.getAttribute('data-name') || '';
        const sku = el.getAttribute('data-sku') || '';
        const barcode = el.getAttribute('data-barcode') || '';
        const itemCat = parseInt(el.getAttribute('data-cat') || '0', 10);
        const itemBrand = el.getAttribute('data-brand') || '';
        
        let match = true;
        if (search && !name.includes(search) && !sku.includes(search) && !barcode.includes(search)) match = false;
        if (cat && String(itemCat) !== cat) match = false;
        if (brand && itemBrand !== brand) match = false;
        if (deptCats && !deptCats.includes(itemCat)) match = false;
        
        el.style.display = match ? 'block' : 'none';
    });
}

function resetToWalkinCustomer() {
    const selectEl = document.getElementById('posCustomerSelect');
    const labelEl = document.getElementById('posCurrentCustomerLabel');
    const searchEl = document.getElementById('posCustomerSearch');
    const loyaltyWidget = document.getElementById('loyaltyWidget');
    if (selectEl) {
        selectEl.value = selectEl.getAttribute('data-default-id') || '0';
        selectEl.setAttribute('data-wallet', selectEl.getAttribute('data-default-wallet') || '0.00');
        selectEl.setAttribute('data-points', selectEl.getAttribute('data-default-points') || '0');
        selectEl.setAttribute('data-name', 'Walk-in Customer');
    }
    if (labelEl) labelEl.innerText = 'Walk-in Customer';
    if (searchEl) searchEl.value = '';
    if (loyaltyWidget) loyaltyWidget.style.display = 'none';
    if (window.posToast) window.posToast('Reset to Walk-in Customer', 'ok');
}
window.resetToWalkinCustomer = resetToWalkinCustomer;

function applyQuickDiscount(type, val) {
    let subtotal = 0;
    Object.keys(touchCart).forEach(k => { subtotal += lineNet(touchCart[k]); });
    if (subtotal <= 0 && val > 0) return;
    let amt = 0;
    if (type === 'pct') {
        amt = Math.round((subtotal * (val / 100)) * 100) / 100;
    } else {
        amt = Math.min(val, subtotal);
    }
    const discountEl = document.getElementById('posCartDiscount');
    if (discountEl) discountEl.value = amt.toFixed(2);
    recalculatePOSBalances();
}
window.applyQuickDiscount = applyQuickDiscount;

function quickApplyWallet() {
    if (typeof checkoutProcess === 'function') checkoutProcess();
    setTimeout(() => { if (typeof setPOSPaymentMode === 'function') setPOSPaymentMode('wallet'); }, 150);
}
window.quickApplyWallet = quickApplyWallet;

function quickRedeemPoints() {
    const selectEl = document.getElementById('posCustomerSelect');
    if (!selectEl) return;
    const points = parseInt(selectEl.getAttribute('data-points'), 10) || 0;
    if (points <= 0) {
        alert('Customer has no reward points to redeem.');
        return;
    }
    let subtotal = 0;
    Object.keys(touchCart).forEach(k => { subtotal += lineNet(touchCart[k]); });
    const discount = Math.min(points, subtotal);
    const couponEl = document.getElementById('posCartCoupon');
    if (couponEl) couponEl.value = discount.toFixed(2);
    recalculatePOSBalances();
    if (window.posToast) window.posToast('Redeemed ' + discount + ' pts as discount', 'ok');
}
window.quickRedeemPoints = quickRedeemPoints;

function updateLoyaltyUI() {
    const sel = document.getElementById('posCustomerSelect');
    const widget = document.getElementById('loyaltyWidget');
    if (!sel || !widget) return;
    
    const val = sel.value;
    const name = sel.getAttribute('data-name') || '';
    
    if (name.includes('Walk-in') || val === '0' || val === '') {
        widget.style.display = 'none';
    } else {
        const wallet = parseFloat(sel.getAttribute('data-wallet')) || 0;
        const points = parseInt(sel.getAttribute('data-points')) || 0;
        
        const walletEl = document.getElementById('lblWallet');
        const pointsEl = document.getElementById('lblPoints');
        if (walletEl) walletEl.innerText = '৳' + wallet.toFixed(2);
        if (pointsEl) pointsEl.innerText = points + ' pts';
        
        widget.style.display = 'flex';
    }
}

function addTouchCartItem(id, name, price, stock, image = '', sku = '') {
    id = parseInt(id, 10);
    if (window.posBeep) window.posBeep(880, 70);
    if (touchCart[id]) {
        touchCart[id].stock = stock;
        if (touchCart[id].qty + 1 <= stock + 1e-9) {
            touchCart[id].qty = Math.round((touchCart[id].qty + 1) * 1000) / 1000;
        } else if (window.posToast) { window.posToast('Only ' + stock + ' in stock.', 'warn'); }
    } else {
        touchCart[id] = { id, name, price, qty: 1, stock, image, sku, line_discount: 0 };
    }
    renderTouchCart();
    document.getElementById('posFilterSearch')?.focus();
}

function updateTouchQty(id, change) {
    if (touchCart[id]) { setTouchQty(id, touchCart[id].qty + change); return; }
    renderTouchCart();
}

function setTouchQty(id, value) {
    const it = touchCart[id];
    if (!it) return;
    let q = parseFloat(value);
    if (!isFinite(q) || q <= 0) { delete touchCart[id]; renderTouchCart(); return; }
    if (!window.POS_DECIMAL) q = Math.round(q);
    q = Math.round(q * 1000) / 1000;
    if (q > it.stock + 1e-9) {
        q = it.stock;
        if (window.posToast) window.posToast('Only ' + it.stock + ' in stock.', 'warn');
    }
    it.qty = q;
    if ((it.line_discount || 0) > it.price * q) it.line_discount = 0;
    renderTouchCart();
    document.getElementById('posFilterSearch')?.focus();
}

function setLineDiscount(id) {
    const it = touchCart[id];
    if (!it || !window.POS_CAN_DISCOUNT) return;
    const v = prompt('Line discount for \u201c' + it.name + '\u201d (৳, total for this line):', it.line_discount || 0);
    if (v === null) return;
    const d = parseFloat(v);
    if (!isFinite(d) || d < 0 || d > it.price * it.qty) { alert('Invalid discount.'); return; }
    it.line_discount = Math.round(d * 100) / 100;
    renderTouchCart();
}

const canOverridePrice = <?= has_admin_permission('pos.override') ? 'true' : 'false' ?>;

function triggerPriceOverride(id) {
    const item = touchCart[id];
    if (!item) return;
    const newPriceInput = prompt("Enter custom overridden price unit amount (৳):", item.price);
    if (newPriceInput !== null) {
        const val = parseFloat(newPriceInput);
        if (!isNaN(val) && val >= 0) {
            touchCart[id].price = val;
            renderTouchCart();
        } else {
            alert("Invalid price amount.");
        }
    }
}

function renderTouchCart() {
    const wrapper = document.getElementById('posActiveCartList');
    wrapper.innerHTML = '';
    
    const keys = Object.keys(touchCart);
    const btnTrigger = document.getElementById('btnPOSCheckoutTrigger');
    if (keys.length === 0) {
        wrapper.innerHTML = '<p style="text-align:center; color:var(--color-text-faint); font-size:11px; margin:16px 0;">Checkout list is empty.</p>';
        if (btnTrigger) btnTrigger.disabled = true;
        recalculatePOSBalances();
        return;
    }
    
    keys.forEach(k => {
        const item = touchCart[k];
        const row = document.createElement('div');
        row.style.cssText = 'display:flex; gap:8px; align-items:center; font-size:11px; border-bottom:1px solid var(--color-border); padding:8px 0;';
        
        let priceDisplay = `৳${item.price}`;
        if (canOverridePrice) {
            priceDisplay = `<span onclick="triggerPriceOverride(${item.id});" style="text-decoration: underline; cursor: pointer; color: var(--color-primary);" title="Click to override price">৳${item.price} <i class="fas fa-edit" style="font-size: 8px;"></i></span>`;
        }
        
        const itemSubtotal = lineNet(item).toFixed(2);
        const fallbackImg = '<?= BASE_URL ?>/../admin/assets/images/placeholder.png';
        const imgSrc = escHtml(item.image ? item.image : fallbackImg);
        const nm = escHtml(item.name);
        const qtyStep = window.POS_DECIMAL ? 'any' : '1';

        row.innerHTML = `
            <div style="width:36px; height:36px; border-radius:4px; overflow:hidden; border:1px solid var(--color-border); flex-shrink:0;">
                <img src="${imgSrc}" alt="" style="width:100%; height:100%; object-fit:cover;">
            </div>
            <div style="flex:1; min-width:0;">
                <strong style="display:block; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="${nm}">${nm}</strong>
                <span style="font-size:9px; color:var(--color-text-faint);">SKU: ${escHtml(item.sku || 'N/A')} &bull; stock ${escHtml(item.stock)}</span>
            </div>
            <div style="display:flex; align-items:center; gap:4px; flex-shrink:0;">
                <button type="button" aria-label="Decrease quantity" onclick="updateTouchQty(${item.id}, -1);" style="border:1px solid var(--color-border); background:var(--color-surface); color:var(--color-text); width:26px; height:26px; border-radius:4px; cursor:pointer;">&minus;</button>
                <input type="number" aria-label="Quantity" min="0" step="${qtyStep}" value="${item.qty}" onchange="setTouchQty(${item.id}, this.value);" style="width:58px; padding:3px; text-align:center; border:1px solid var(--color-border); border-radius:4px; background:var(--color-surface); color:var(--color-text); font-size:12px; font-weight:700;">
                <button type="button" aria-label="Increase quantity" onclick="updateTouchQty(${item.id}, 1);" style="border:1px solid var(--color-border); background:var(--color-surface); color:var(--color-text); width:26px; height:26px; border-radius:4px; cursor:pointer;">+</button>
            </div>
            <div style="width:104px; text-align:right; font-size:10px; color:var(--color-text-muted); line-height:1.3; flex-shrink:0;">
                <div>৳${item.price.toFixed(2)} &times; ${escHtml(item.qty)}</div>
                <div style="font-size:9px; color:var(--color-text-faint);">${window.POS_CAN_DISCOUNT ? `<a href="#" onclick="setLineDiscount(${item.id}); return false;" style="color:var(--color-primary);">Disc: ৳${(item.line_discount || 0).toFixed(2)}</a>` : 'Disc: ৳' + (item.line_discount || 0).toFixed(2)}</div>
                <div style="font-weight:700; color:var(--color-text);">৳${itemSubtotal}</div>
            </div>
            <button type="button" aria-label="Remove item" onclick="setTouchQty(${item.id}, 0);" style="border:none; background:transparent; color:#e03131; cursor:pointer; padding:6px; font-size:14px; flex-shrink:0;" title="Remove"><i class="fas fa-trash"></i></button>
        `;
        wrapper.appendChild(row);
    });
    
    if (btnTrigger) btnTrigger.disabled = false;
    recalculatePOSBalances();
}

function recalculatePOSBalances() {
    window.recalculatePOSBalances = recalculatePOSBalances;
    let subtotal = 0;
    Object.keys(touchCart).forEach(k => {
        subtotal += lineNet(touchCart[k]);
    });
    
    const discountEl = document.getElementById('posCartDiscount');
    const couponEl = document.getElementById('posCartCoupon');
    const discount = discountEl ? parseFloat(discountEl.value) || 0 : 0;
    const coupon = couponEl ? parseFloat(couponEl.value) || 0 : 0;
    
    const totalDiscounts = discount + coupon;
    const taxableAmount = Math.max(subtotal - totalDiscounts, 0);
    const vat = 0.0;
    const total = taxableAmount;
    
    const subtotalEl = document.getElementById('posCartSubtotal');
    const vatEl = document.getElementById('posCartVat');
    const totalEl = document.getElementById('posCartTotalPayable');
    
    if (subtotalEl) subtotalEl.innerText = '৳' + subtotal.toFixed(2);
    if (vatEl) vatEl.innerText = '৳' + vat.toFixed(2);
    if (totalEl) totalEl.innerText = '৳' + total.toFixed(2);
}



function suspendPOSCart() {
    const keys = Object.keys(touchCart);
    if (keys.length === 0) {
        alert('Active cart is empty.');
        return;
    }
    
    const notes = prompt('Enter suspension note details (e.g. customer name or token id):');
    if (notes === null) return;
    
    const customerId = document.getElementById('posCustomerSelect').value;
    
    const formData = new FormData();
    formData.append('pos_action', 'hold');
    formData.append('customer_id', customerId);
    formData.append('cart_data', JSON.stringify(touchCart));
    formData.append('hold_notes', notes);
    formData.append('csrf_token', '<?= csrf_token() ?>');
    
    fetch('hold-orders.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert('Cart suspended successfully!');
            clearTouchCart();
        } else {
            alert(data.error || 'Failed to suspend cart.');
        }
    });
}

// Export cart functions to window scope immediately
window.addTouchCartItem = addTouchCartItem;
window.updateTouchQty = updateTouchQty;
window.setTouchQty = setTouchQty;
window.setLineDiscount = setLineDiscount;
window.clearTouchCart = clearTouchCart;
window.renderTouchCart = renderTouchCart;
window.suspendPOSCart = suspendPOSCart;
window.updateLoyaltyUI = updateLoyaltyUI;

function setPOSPaymentMode(mode) {
    document.querySelectorAll('.pos-paymode-btn').forEach(b => {
        b.classList.remove('active');
        b.style.background = 'var(--color-surface)';
        b.style.color = 'var(--color-text)';
        b.style.border = '1px solid var(--color-border)';
    });
    const activeBtn = document.querySelector(`.pos-paymode-btn[data-mode="${mode}"]`);
    if (activeBtn) {
        activeBtn.classList.add('active');
        activeBtn.style.background = (mode === 'cash' ? '#40c057' : (mode === 'bkash' ? '#e64980' : (mode === 'nagad' ? '#fd7e14' : (mode === 'rocket' ? '#845ef7' : (mode === 'card' ? '#228be6' : 'var(--color-primary)')))));
        activeBtn.style.color = '#fff';
        activeBtn.style.border = 'none';
    }

    const payableTotalEl = document.getElementById('modalPayableTotal');
    const total = payableTotalEl ? parseFloat(payableTotalEl.innerText.replace(/[^\d.]/g, '')) || 0 : 0;
    
    const splitCash = document.getElementById('splitCash');
    const splitCard = document.getElementById('splitCard');
    const splitBkash = document.getElementById('splitBkash');
    const splitWallet = document.getElementById('splitWallet');
    const splitBank = document.getElementById('splitBank');
    const quickCashBox = document.getElementById('quickCashTenderBox');

    // Reset all fields
    if (splitCash) splitCash.value = '0';
    if (splitCard) splitCard.value = '0';
    if (splitBkash) splitBkash.value = '0';
    if (splitWallet) splitWallet.value = '0';
    if (splitBank) splitBank.value = '0';

    if (quickCashBox) quickCashBox.style.display = (mode === 'cash' || mode === 'split') ? 'block' : 'none';

    if (mode === 'cash') {
        if (splitCash) splitCash.value = total.toFixed(2);
    } else if (mode === 'bkash') {
        if (splitBkash) splitBkash.value = total.toFixed(2);
        const prov = document.getElementById('splitMobileProvider');
        if (prov) prov.value = 'bKash';
        setTimeout(() => document.getElementById('splitBkashTxnId')?.focus(), 100);
    } else if (mode === 'nagad') {
        if (splitBkash) splitBkash.value = total.toFixed(2);
        const prov = document.getElementById('splitMobileProvider');
        if (prov) prov.value = 'Nagad';
        setTimeout(() => document.getElementById('splitBkashTxnId')?.focus(), 100);
    } else if (mode === 'rocket') {
        if (splitBkash) splitBkash.value = total.toFixed(2);
        const prov = document.getElementById('splitMobileProvider');
        if (prov) prov.value = 'Rocket';
        setTimeout(() => document.getElementById('splitBkashTxnId')?.focus(), 100);
    } else if (mode === 'card') {
        if (splitCard) splitCard.value = total.toFixed(2);
        setTimeout(() => document.getElementById('splitCardRef')?.focus(), 100);
    } else if (mode === 'wallet') {
        const custSelect = document.getElementById('posCustomerSelect');
        const walletBal = custSelect ? parseFloat(custSelect.getAttribute('data-wallet')) || 0 : 0;
        const walletUsed = Math.min(walletBal, total);
        if (splitWallet) splitWallet.value = walletUsed.toFixed(2);
        if (walletUsed < total && splitCash) {
            splitCash.value = (total - walletUsed).toFixed(2);
        }
    } else if (mode === 'split') {
        if (splitCash) splitCash.value = total.toFixed(2);
    }
    if (window.updateModalChangeDue) window.updateModalChangeDue();
}
window.setPOSPaymentMode = setPOSPaymentMode;

function tenderCashAdd(amt) {
    const splitCash = document.getElementById('splitCash');
    const payableTotalEl = document.getElementById('modalPayableTotal');
    const total = payableTotalEl ? parseFloat(payableTotalEl.innerText.replace(/[^\d.]/g, '')) || 0 : 0;
    if (!splitCash) return;
    
    if (amt === 'exact') {
        splitCash.value = total.toFixed(2);
    } else if (amt === 'clear') {
        splitCash.value = '0';
    } else {
        const cur = parseFloat(splitCash.value) || 0;
        splitCash.value = (cur + amt).toFixed(2);
    }
    if (window.updateModalChangeDue) window.updateModalChangeDue();
}
window.tenderCashAdd = tenderCashAdd;

window.applyResumedCart = function (data) {
    sessionStorage.setItem('groco_pos_resume_cart', JSON.stringify(data));
    location.reload();
};

document.addEventListener('DOMContentLoaded', () => {
    const resumeDataStr = sessionStorage.getItem('groco_pos_resume_cart');
    if (resumeDataStr) {
        try {
            const data = JSON.parse(resumeDataStr);
            sessionStorage.removeItem('groco_pos_resume_cart');
            if (data && data.cartData) {
                let items = data.cartData;
                items = Array.isArray(items) ? items : (items && typeof items === 'object' ? Object.values(items) : []);
                Object.keys(touchCart).forEach(k => delete touchCart[k]);
                items.forEach(it => {
                    const id = parseInt(it && it.id, 10), qty = parseFloat(it && it.qty), price = parseFloat(it && it.price), stock = parseFloat(it && it.stock);
                    if (!(id > 0) || !(qty > 0) || !isFinite(price) || price < 0) return;
                    touchCart[id] = { id, name: String(it.name || ('#' + id)), price, qty, stock: isFinite(stock) ? stock : qty,
                                      image: String(it.image || ''), sku: String(it.sku || ''), line_discount: Math.max(0, parseFloat(it.line_discount) || 0) };
                });
                
                if (data.customerId > 0) {
                    const custSelect = document.getElementById('posCustomerSelect');
                    if (custSelect) {
                        custSelect.value = data.customerId;
                    }
                }
                renderTouchCart();
                if (typeof updateLoyaltyUI === 'function') {
                    updateLoyaltyUI();
                }
            }
        } catch (e) {
            console.error('Failed to parse resumed cart', e);
        }
    }
});
</script>

<!-- Checkout Split Payment Modal -->
<div class="modal fade" id="checkoutPaymentModal" tabindex="-1" aria-hidden="true" style="z-index: 1055;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:var(--radius-md); border:none; box-shadow:var(--shadow-lg); background:var(--color-surface);">
            <div class="modal-header" style="border-bottom:1px solid var(--color-border); padding:16px 20px;">
                <h5 class="modal-title" style="font-weight:800; font-size:15px; color:var(--color-text); margin:0;"><i class="fas fa-credit-card"></i> Payment &amp; Tender Terminal</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="font-size:12px; border:none; background:transparent; cursor:pointer;"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body" style="padding:20px;">
                <!-- Summary of Payable -->
                <div style="background:rgba(92,124,250,0.06); padding:12px 16px; border-radius:var(--radius-sm); margin-bottom:14px; display:flex; flex-direction:column; gap:5px; font-size:12px; color:var(--color-text-muted);">
                    <div style="display:flex; justify-content:space-between;">
                        <span>Customer Info:</span>
                        <span id="modalCustomerInfo" style="font-weight:700; color:var(--color-text);">Walk-in Customer</span>
                    </div>
                    <div style="display:flex; justify-content:space-between;">
                        <span>Subtotal:</span>
                        <span id="modalSubtotal" style="font-weight:700; color:var(--color-text);">৳0.00</span>
                    </div>
                    <div style="display:flex; justify-content:space-between;">
                        <span>Discount Override:</span>
                        <span id="modalDiscount" style="font-weight:700; color:var(--color-text);">৳0.00</span>
                    </div>
                    <div style="display:flex; justify-content:space-between;">
                        <span>Coupon / Points:</span>
                        <span id="modalCoupon" style="font-weight:700; color:var(--color-text);">৳0.00</span>
                    </div>
                    <div style="display:flex; justify-content:space-between; border-top:1px dashed var(--color-border); padding-top:6px; font-size:14px; font-weight:800; color:var(--color-text);">
                        <span style="color:var(--color-text);">Grand Total Due:</span>
                        <strong id="modalPayableTotal" style="color:var(--color-primary); font-size:16px;">৳0.00</strong>
                    </div>
                </div>

                <!-- 1-Click Fast Payment Mode Switchers -->
                <div style="display:flex; gap:5px; overflow-x:auto; margin-bottom:14px; padding-bottom:4px; scrollbar-width:thin;" id="posPaymentModePills">
                    <button type="button" class="btn btn-sm pos-paymode-btn active" data-mode="cash" onclick="setPOSPaymentMode('cash');" style="border-radius:var(--radius-pill); font-size:11px; font-weight:700; padding:6px 12px; background:#40c057; color:#fff; border:none; white-space:nowrap;"><i class="fas fa-money-bill-wave"></i> Cash</button>
                    <button type="button" class="btn btn-sm pos-paymode-btn" data-mode="bkash" onclick="setPOSPaymentMode('bkash');" style="border-radius:var(--radius-pill); font-size:11px; font-weight:700; padding:6px 12px; background:var(--color-surface); color:var(--color-text); border:1px solid var(--color-border); white-space:nowrap;"><i class="fas fa-mobile-alt" style="color:#e64980;"></i> bKash</button>
                    <button type="button" class="btn btn-sm pos-paymode-btn" data-mode="nagad" onclick="setPOSPaymentMode('nagad');" style="border-radius:var(--radius-pill); font-size:11px; font-weight:700; padding:6px 12px; background:var(--color-surface); color:var(--color-text); border:1px solid var(--color-border); white-space:nowrap;"><i class="fas fa-bolt" style="color:#fd7e14;"></i> Nagad</button>
                    <button type="button" class="btn btn-sm pos-paymode-btn" data-mode="rocket" onclick="setPOSPaymentMode('rocket');" style="border-radius:var(--radius-pill); font-size:11px; font-weight:700; padding:6px 12px; background:var(--color-surface); color:var(--color-text); border:1px solid var(--color-border); white-space:nowrap;"><i class="fas fa-rocket" style="color:#845ef7;"></i> Rocket</button>
                    <button type="button" class="btn btn-sm pos-paymode-btn" data-mode="card" onclick="setPOSPaymentMode('card');" style="border-radius:var(--radius-pill); font-size:11px; font-weight:700; padding:6px 12px; background:var(--color-surface); color:var(--color-text); border:1px solid var(--color-border); white-space:nowrap;"><i class="fas fa-credit-card" style="color:#228be6;"></i> Card</button>
                    <button type="button" class="btn btn-sm pos-paymode-btn" data-mode="wallet" onclick="setPOSPaymentMode('wallet');" style="border-radius:var(--radius-pill); font-size:11px; font-weight:700; padding:6px 12px; background:var(--color-surface); color:var(--color-text); border:1px solid var(--color-border); white-space:nowrap;"><i class="fas fa-wallet" style="color:#fcc419;"></i> Wallet</button>
                    <button type="button" class="btn btn-sm pos-paymode-btn" data-mode="split" onclick="setPOSPaymentMode('split');" style="border-radius:var(--radius-pill); font-size:11px; font-weight:700; padding:6px 12px; background:var(--color-surface); color:var(--color-text); border:1px solid var(--color-border); white-space:nowrap;"><i class="fas fa-columns"></i> Split</button>
                </div>

                <!-- Fast Cash Tender Preset Chips (Bangladesh Retail Standard) -->
                <div id="quickCashTenderBox" style="background:rgba(64,192,87,0.08); padding:8px 12px; border-radius:var(--radius-sm); margin-bottom:14px; border:1px solid rgba(64,192,87,0.2);">
                    <span style="font-size:10px; font-weight:700; color:var(--color-text-muted); display:block; margin-bottom:5px;">QUICK CASH TENDER:</span>
                    <div style="display:flex; gap:5px; flex-wrap:wrap;">
                        <button type="button" class="btn btn-sm" onclick="tenderCashAdd('exact');" style="font-size:11px; font-weight:700; padding:3px 9px; border-radius:4px; background:#40c057; color:#fff; border:none;">Exact</button>
                        <button type="button" class="btn btn-sm" onclick="tenderCashAdd(50);" style="font-size:11px; font-weight:700; padding:3px 9px; border-radius:4px; background:var(--color-surface); border:1px solid var(--color-border); color:var(--color-text);">+৳50</button>
                        <button type="button" class="btn btn-sm" onclick="tenderCashAdd(100);" style="font-size:11px; font-weight:700; padding:3px 9px; border-radius:4px; background:var(--color-surface); border:1px solid var(--color-border); color:var(--color-text);">+৳100</button>
                        <button type="button" class="btn btn-sm" onclick="tenderCashAdd(500);" style="font-size:11px; font-weight:700; padding:3px 9px; border-radius:4px; background:var(--color-surface); border:1px solid var(--color-border); color:var(--color-text);">+৳500</button>
                        <button type="button" class="btn btn-sm" onclick="tenderCashAdd(1000);" style="font-size:11px; font-weight:700; padding:3px 9px; border-radius:4px; background:var(--color-surface); border:1px solid var(--color-border); color:var(--color-text);">+৳1000</button>
                        <button type="button" class="btn btn-sm" onclick="tenderCashAdd(2000);" style="font-size:11px; font-weight:700; padding:3px 9px; border-radius:4px; background:var(--color-surface); border:1px solid var(--color-border); color:var(--color-text);">+৳2000</button>
                        <button type="button" class="btn btn-sm" onclick="tenderCashAdd('clear');" style="font-size:11px; font-weight:700; padding:3px 9px; border-radius:4px; background:var(--color-surface); border:1px solid var(--color-border); color:#e03131;">Clear</button>
                    </div>
                </div>

                <form id="frmPOSPayment" onsubmit="event.preventDefault(); confirmPOSSale();">
                    <div style="display:flex; flex-direction:column; gap:12px;">
                        <!-- Cash Row -->
                        <div style="display:grid; grid-template-columns: 1.5fr 2fr; gap:10px; align-items:center;">
                            <label style="font-size:12px; font-weight:700; color:var(--color-text);"><i class="fas fa-money-bill-wave" style="color:#40c057;"></i> Cash (৳)</label>
                            <input type="number" id="splitCash" min="0" step="0.01" value="0" class="form-control" style="font-size:13px; text-align:right;">
                        </div>

                        <!-- Card Row -->
                        <div style="display:grid; grid-template-columns: 1.5fr 2fr; gap:10px; align-items:center;">
                            <label style="font-size:12px; font-weight:700; color:var(--color-text);"><i class="fas fa-credit-card" style="color:#228be6;"></i> Card (৳)</label>
                            <input type="number" id="splitCard" min="0" step="0.01" value="0" class="form-control" style="font-size:13px; text-align:right; margin-bottom:4px;">
                        </div>
                        <div id="cardDetailsRow" style="display:none; flex-direction:column; gap:8px; background:#f8f9fa; padding:10px; border-radius:var(--radius-sm); border:1px solid var(--color-border); margin-bottom:8px;">
                            <div style="display:grid; grid-template-columns: 1.5fr 2fr; gap:10px; align-items:center;">
                                <label style="font-size:10px; font-weight:700; color:var(--color-text-muted);">Card Type *</label>
                                <select id="splitCardType" class="form-control" style="font-size:11px; background:var(--color-surface); color:var(--color-text); height:auto; padding:4px 8px;">
                                    <option value="Visa">Visa</option>
                                    <option value="MasterCard">MasterCard</option>
                                    <option value="Amex">Amex</option>
                                    <option value="Nexus">Nexus Card</option>
                                    <option value="Other">Other Card</option>
                                </select>
                            </div>
                            <div style="display:grid; grid-template-columns: 1.5fr 2fr; gap:10px; align-items:center;">
                                <label style="font-size:10px; font-weight:700; color:var(--color-text-muted);">Last 4 Digits *</label>
                                <input type="text" id="splitCardNo" placeholder="E.g. 4321" class="form-control" style="font-size:11px;">
                            </div>
                            <div style="display:grid; grid-template-columns: 1.5fr 2fr; gap:10px; align-items:center;">
                                <label style="font-size:10px; font-weight:700; color:var(--color-text-muted);">Reference / Txn ID *</label>
                                <input type="text" id="splitCardRef" placeholder="Approval code / Txn Ref" class="form-control" style="font-size:11px;">
                            </div>
                            <div style="display:grid; grid-template-columns: 1.5fr 2fr; gap:10px; align-items:center;">
                                <label style="font-size:10px; font-weight:700; color:var(--color-text-muted);">Bank Name *</label>
                                <input type="text" id="splitCardBank" placeholder="E.g. DBBL, Brac Bank" class="form-control" style="font-size:11px;">
                            </div>
                        </div>

                        <!-- Mobile Banking Row -->
                        <div style="display:grid; grid-template-columns: 1.5fr 2fr; gap:10px; align-items:center;">
                            <label style="font-size:12px; font-weight:700; color:var(--color-text);"><i class="fas fa-mobile-alt" style="color:#e64980;"></i> Mobile Banking (৳)</label>
                            <input type="number" id="splitBkash" min="0" step="0.01" value="0" class="form-control" style="font-size:13px; text-align:right; margin-bottom:4px;">
                        </div>
                        <div id="mobileDetailsRow" style="display:none; flex-direction:column; gap:8px; background:#f8f9fa; padding:10px; border-radius:var(--radius-sm); border:1px solid var(--color-border); margin-bottom:8px;">
                            <div style="display:grid; grid-template-columns: 1.5fr 2fr; gap:10px; align-items:center;">
                                <label style="font-size:10px; font-weight:700; color:var(--color-text-muted);">Provider *</label>
                                <select id="splitMobileProvider" class="form-control" style="font-size:11px; background:var(--color-surface); color:var(--color-text); height:auto; padding:4px 8px;">
                                    <option value="bKash">bKash</option>
                                    <option value="Nagad">Nagad</option>
                                    <option value="Rocket">Rocket</option>
                                    <option value="Other">Other Mobile</option>
                                </select>
                            </div>
                            <div style="display:grid; grid-template-columns: 1.5fr 2fr; gap:10px; align-items:center;">
                                <label style="font-size:10px; font-weight:700; color:var(--color-text-muted);">Transaction ID *</label>
                                <input type="text" id="splitBkashTxnId" placeholder="E.g. TRX-8921B" class="form-control" style="font-size:11px;">
                            </div>
                        </div>

                        <!-- Wallet Credit Row -->
                        <div style="display:grid; grid-template-columns: 1.5fr 2fr; gap:10px; align-items:center;">
                            <label style="font-size:12px; font-weight:700; color:var(--color-text);"><i class="fas fa-wallet" style="color:#fcc419;"></i> Wallet Credit (৳)</label>
                            <input type="number" id="splitWallet" min="0" step="0.01" value="0" class="form-control" style="font-size:13px; text-align:right;">
                        </div>

                        <!-- Bank Transfer Row -->
                        <div style="display:grid; grid-template-columns: 1.5fr 2fr; gap:10px; align-items:center;">
                            <label style="font-size:12px; font-weight:700; color:var(--color-text);"><i class="fas fa-university" style="color:#7950f2;"></i> Bank Transfer (৳)</label>
                            <input type="number" id="splitBank" min="0" step="0.01" value="0" class="form-control" style="font-size:13px; text-align:right;">
                        </div>
                        <div id="bankDetailsRow" style="display:none; flex-direction:column; gap:8px; background:#f8f9fa; padding:10px; border-radius:var(--radius-sm); border:1px solid var(--color-border); margin-bottom:8px;">
                            <div style="display:grid; grid-template-columns: 1.5fr 2fr; gap:10px; align-items:center;">
                                <label style="font-size:10px; font-weight:700; color:var(--color-text-muted);">Bank Name *</label>
                                <input type="text" id="splitBankName" placeholder="E.g. City Bank, HSBC" class="form-control" style="font-size:11px;">
                            </div>
                            <div style="display:grid; grid-template-columns: 1.5fr 2fr; gap:10px; align-items:center;">
                                <label style="font-size:10px; font-weight:700; color:var(--color-text-muted);">Transaction Ref *</label>
                                <input type="text" id="splitBankRef" placeholder="Txn ID / reference code" class="form-control" style="font-size:11px;">
                            </div>
                        </div>
                    </div>

                    <!-- Split calculations display -->
                    <div style="margin-top:14px; border-top:1px dashed var(--color-border); padding-top:10px; font-size:12px; color:var(--color-text-muted); display:flex; flex-direction:column; gap:4px;">
                        <div style="display:flex; justify-content:space-between;">
                            <span>Total Entered:</span>
                            <strong id="modalTotalEntered" style="color:var(--color-text);">৳0.00</strong>
                        </div>
                        <div style="display:flex; justify-content:space-between;">
                            <span>Remaining Due:</span>
                            <strong id="modalRemainingDue" style="color:#e03131;">৳0.00</strong>
                        </div>
                        <div style="display:flex; justify-content:space-between; font-weight:700; font-size:13px; color:var(--color-primary); border-top:1px solid var(--color-border); padding-top:6px; margin-top:4px;">
                            <span>Change Due:</span>
                            <strong id="modalChangeDue">৳0.00</strong>
                        </div>
                    </div>

                    <!-- Large Prominent Change Return Banner for Cashiers -->
                    <div id="modalChangeBanner" style="display:none; background:#2b8a3e; color:#fff; font-size:16px; font-weight:800; text-align:center; padding:10px 14px; border-radius:8px; margin-top:12px; letter-spacing:0.5px;">
                        CHANGE TO RETURN: ৳<span id="modalChangeAmount">0.00</span>
                    </div>

                    <div style="display:flex; justify-content:end; gap:8px; margin-top:18px;">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="font-size:12px; padding:6px 14px; border-radius:var(--radius-pill); font-weight:700;">Cancel</button>
                        <button type="submit" id="btnConfirmPOSSale" class="btn btn-primary" disabled style="font-size:12px; padding:6px 18px; border-radius:var(--radius-pill); font-weight:700;"><i class="fas fa-check-circle"></i> Confirm Sale</button>
                    </div>
                </form>
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
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                            <div>
                                <label style="font-size:11px; font-weight:700; color:var(--color-text-muted); display:block; margin-bottom:4px;">Birthday (Optional)</label>
                                <input type="date" id="custNewBirthday" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:13px; outline:none;">
                            </div>
                            <div>
                                <label style="font-size:11px; font-weight:700; color:var(--color-text-muted); display:block; margin-bottom:4px;">Gender (Optional)</label>
                                <select id="custNewGender" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:13px; outline:none; background:var(--color-surface); color:var(--color-text);">
                                    <option value="">— Select —</option>
                                    <option value="male">Male</option>
                                    <option value="female">Female</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label style="font-size:11px; font-weight:700; color:var(--color-text-muted); display:block; margin-bottom:4px; text-align:left;">Address (Optional)</label>
                            <textarea id="custNewAddress" placeholder="E.g. House 12, Road 5, Dhaka" rows="2" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:13px; outline:none; resize:none;"></textarea>
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

<script src="<?= BASE_URL ?>/../admin/assets/js/pos.js"></script>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>

