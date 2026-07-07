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
    <div style="display:grid; grid-template-columns: 1.5fr 1fr; gap:var(--space-4);" class="admin-dashboard-layout">
        
        <!-- Left: Products selector block -->
        <div style="display:flex; flex-direction:column; gap:12px;">
            <!-- Filter toolbar -->
            <div class="dashboard-card" style="padding:12px; margin:0; display:flex; gap:10px; flex-wrap:wrap; position:relative;">
                <div style="position:relative; flex:1.5; display:flex; align-items:center;">
                    <input type="text" id="posFilterSearch" autocomplete="off" placeholder="Scan SKU/Barcode or type product name..." onkeyup="filterPOSCatalog();" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:13px; outline:none;">
                    <div id="posAutocompleteDropdown" style="display:none; position:absolute; top:100%; left:0; right:0; background:#fff; border:1px solid var(--color-border); border-radius:var(--radius-sm); box-shadow:var(--shadow-md); z-index:1005; max-height:250px; overflow-y:auto; margin-top:2px;"></div>
                </div>
                
                <select id="posFilterCat" onchange="filterPOSCatalog();" style="flex:1; padding:8px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:13px; outline:none; background:#fff;">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>"><?= e($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>

                <select id="posFilterBrand" onchange="filterPOSCatalog();" style="flex:1; padding:8px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:13px; outline:none; background:#fff;">
                    <option value="">All Brands</option>
                    <?php foreach ($brands as $b): ?>
                        <option value="<?= $b['id'] ?>"><?= e($b['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Touch product cells grid -->
            <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap:10px; max-height: 520px; overflow-y: auto; padding:4px;" id="posCatalogGrid">
                <?php foreach ($products as $p): 
                    $img = image_url($p['image'], 'products');
                ?>
                    <div class="dashboard-card touch-product-cell" 
                         data-id="<?= $p['id'] ?>"
                         data-name="<?= strtolower($p['name']) ?>" 
                         data-name-original="<?= e($p['name']) ?>"
                         data-sku="<?= strtolower($p['sku'] ?? '') ?>"
                         data-barcode="<?= strtolower($p['barcode'] ?? '') ?>"
                         data-cat="<?= $p['category_id'] ?: '' ?>"
                         data-brand="<?= $p['brand_id'] ?: '' ?>"
                         onclick="addTouchCartItem(<?= $p['id'] ?>, '<?= e($p['name']) ?>', <?= $p['price'] ?>, <?= $p['stock'] ?>);" 
                         style="padding:10px; text-align:center; cursor:pointer; margin:0; transition: 0.1s;">
                        
                        <div style="width:100%; height:70px; border-radius:var(--radius-sm); overflow:hidden; border:1px solid var(--color-border); background:#fff; margin-bottom:6px;">
                            <img src="<?= e($img) ?>" alt="" style="width:100%; height:100%; object-fit:cover;">
                        </div>
                        <strong style="font-size:11px; color:var(--color-text); display:block; height:32px; overflow:hidden; line-height:16px; margin-bottom:4px;"><?= e($p['name']) ?></strong>
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
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; border-bottom:1px solid var(--color-border); padding-bottom:6px; flex-wrap:wrap; gap:6px;">
                    <div>
                        <h3 style="font-size:14px; font-weight:800; margin:0;"><i class="fas fa-shopping-basket"></i> POS Cart</h3>
                        <span id="posCurrentCustomerLabel" style="font-size:10px; color:var(--color-primary); font-weight:700;">Walk-in Customer</span>
                    </div>
                    <!-- Customer search input and dropdown -->
                    <div style="position:relative; width:160px;">
                        <input type="text" id="posCustomerSearch" placeholder="Search Customer (F4)..." autocomplete="off" style="width:100%; padding:4px 8px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:11px; outline:none;">
                        <input type="hidden" id="posCustomerSelect" 
                               value="<?= $defaultWalkin['id'] ?>" 
                               data-wallet="<?= $defaultWalkin['wallet_balance'] ?>" 
                               data-points="<?= $defaultWalkin['reward_points'] ?>" 
                               data-name="Walk-in Customer"
                               data-default-id="<?= $defaultWalkin['id'] ?>"
                               data-default-wallet="<?= $defaultWalkin['wallet_balance'] ?>"
                               data-default-points="<?= $defaultWalkin['reward_points'] ?>"
                               data-default-name="Walk-in Customer">
                        <div id="posCustomerAutocomplete" style="display:none; position:absolute; top:100%; right:0; width:220px; background:#fff; border:1px solid var(--color-border); border-radius:var(--radius-sm); box-shadow:var(--shadow-md); z-index:1006; max-height:200px; overflow-y:auto; margin-top:2px;"></div>
                    </div>
                </div>

                <!-- Customer loyalty widgets status -->
                <div id="loyaltyWidget" style="display:none; background:rgba(92,124,250,0.06); padding:8px; border-radius:var(--radius-sm); font-size:11px; margin-bottom:12px; display:flex; justify-content:space-between;">
                    <span>Wallet Balance: <strong id="lblWallet">৳0.00</strong></span>
                    <span>Reward Points: <strong id="lblPoints">0 pts</strong></span>
                </div>

                <!-- Active checkout items list -->
                <div id="posActiveCartList" style="max-height: 200px; overflow-y: auto; display:flex; flex-direction:column; gap:8px; margin-bottom:16px; border-bottom:1px dashed var(--color-border); padding-bottom:12px;">
                    <p style="text-align:center; color:var(--color-text-faint); font-size:11px; margin:16px 0;">Checkout list is empty.</p>
                </div>
            </div>

            <!-- Calculations, Suspends, Checkout buttons -->
            <div>
                <div style="font-size:12px; color:var(--color-text-muted); display:flex; flex-direction:column; gap:6px; margin-bottom:12px;">
                    <div style="display:flex; justify-content:space-between;">
                        <span>Subtotal:</span>
                        <strong id="posCartSubtotal">৳0.00</strong>
                    </div>

                    <div style="display:flex; justify-content:space-between;">
                        <span>VAT (5%):</span>
                        <strong id="posCartVat">৳0.00</strong>
                    </div>
                    
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span>Discount Override (৳):</span>
                        <input type="number" id="posCartDiscount" min="0" value="0" onchange="recalculatePOSBalances();" onkeyup="recalculatePOSBalances();" style="width:70px; padding:2px 6px; border:1px solid var(--color-border); border-radius:var(--radius-sm); text-align:right;">
                    </div>

                    <div style="display:flex; justify-content:space-between; align-items:center; margin-top:4px;">
                        <span>Coupon Discount (৳):</span>
                        <input type="number" id="posCartCoupon" min="0" value="0" onchange="recalculatePOSBalances();" onkeyup="recalculatePOSBalances();" style="width:70px; padding:2px 6px; border:1px solid var(--color-border); border-radius:var(--radius-sm); text-align:right;">
                    </div>

                    <div style="display:flex; justify-content:space-between; border-top:1px dashed var(--color-border); padding-top:6px; font-size:14px; color:var(--color-text);">
                        <span>Total Payable Due:</span>
                        <strong id="posCartTotalPayable" style="color:var(--color-primary); font-size:15px;">৳0.00</strong>
                    </div>
                </div>

                <!-- Split Payment Modal triggers upon clicking Checkout below -->

                <div style="display:grid; grid-template-columns: 1fr 2fr; gap:8px;" class="grid-2">
                    <button type="button" onclick="suspendPOSCart();" class="btn btn-secondary" style="border-radius:var(--radius-pill); font-weight:700; padding:10px; font-size:12px;"><i class="fas fa-hand-holding"></i> Hold</button>
                    <button type="button" id="btnPOSCheckoutTrigger" disabled onclick="checkoutProcess();" class="btn btn-primary" style="border-radius:var(--radius-pill); font-weight:700; padding:10px; font-size:12px;"><i class="fas fa-shopping-bag"></i> Checkout & Print</button>
                </div>
            </div>

        </div>
    </div>
<?php endif; ?>

<script>
let touchCart = {};
window.touchCart = touchCart;
window.csrfToken = '<?= csrf_token() ?>';

function filterPOSCatalog() {
    const searchEl = document.getElementById('posFilterSearch');
    const catEl = document.getElementById('posFilterCat');
    const brandEl = document.getElementById('posFilterBrand');
    if (!searchEl || !catEl || !brandEl) return;
    
    const search = searchEl.value.toLowerCase();
    const cat = catEl.value;
    const brand = brandEl.value;
    
    const items = document.querySelectorAll('.touch-product-cell');
    items.forEach(el => {
        const name = el.getAttribute('data-name');
        const sku = el.getAttribute('data-sku');
        const itemCat = el.getAttribute('data-cat');
        const itemBrand = el.getAttribute('data-brand');
        
        let match = true;
        if (search && !name.includes(search) && !sku.includes(search)) match = false;
        if (cat && itemCat !== cat) match = false;
        if (brand && itemBrand !== brand) match = false;
        
        el.style.display = match ? 'block' : 'none';
    });
}

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

function addTouchCartItem(id, name, price, stock) {
    window.addTouchCartItem = addTouchCartItem;
    window.updateTouchQty = updateTouchQty;
    if (touchCart[id]) {
        if (touchCart[id].qty < stock) {
            touchCart[id].qty++;
        } else {
            alert('Out of stock.');
        }
    } else {
        touchCart[id] = { id, name, price, qty: 1, stock };
    }
    renderTouchCart();
    document.getElementById('posFilterSearch')?.focus();
}

function updateTouchQty(id, change) {
    if (touchCart[id]) {
        touchCart[id].qty += change;
        if (touchCart[id].qty <= 0) {
            delete touchCart[id];
        } else if (touchCart[id].qty > touchCart[id].stock) {
            touchCart[id].qty = touchCart[id].stock;
            alert('Out of stock.');
        }
    }
    renderTouchCart();
    document.getElementById('posFilterSearch')?.focus();
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
    window.renderTouchCart = renderTouchCart;
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
        row.style.cssText = 'display:flex; justify-content:space-between; align-items:center; font-size:12px; border-bottom:1px solid var(--color-border); padding-bottom:6px;';
        
        let priceDisplay = `৳${item.price} each`;
        if (canOverridePrice) {
            priceDisplay = `<span onclick="triggerPriceOverride(${item.id});" style="text-decoration: underline; cursor: pointer; color: var(--color-primary);" title="Click to override price">৳${item.price} each <i class="fas fa-edit" style="font-size: 8px;"></i></span>`;
        }
        
        row.innerHTML = `
            <div style="flex:1.5;">
                <strong>${item.name}</strong><br>
                <span style="font-size:9px; color:var(--color-text-faint);">${priceDisplay}</span>
            </div>
            <div style="display:flex; align-items:center; gap:8px; flex:1; justify-content:center;">
                <button type="button" onclick="updateTouchQty(${item.id}, -1);" style="border:1px solid var(--color-border); background:#fff; width:20px; height:20px; border-radius:50%; cursor:pointer;">-</button>
                <strong>${item.qty}</strong>
                <button type="button" onclick="updateTouchQty(${item.id}, 1);" style="border:1px solid var(--color-border); background:#fff; width:20px; height:20px; border-radius:50%; cursor:pointer;">+</button>
            </div>
            <div style="flex:1; text-align:right; font-weight:700;">৳${(item.price * item.qty).toFixed(2)}</div>
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
        subtotal += (touchCart[k].price * touchCart[k].qty);
    });
    
    const discountEl = document.getElementById('posCartDiscount');
    const couponEl = document.getElementById('posCartCoupon');
    const discount = discountEl ? parseFloat(discountEl.value) || 0 : 0;
    const coupon = couponEl ? parseFloat(couponEl.value) || 0 : 0;
    
    const totalDiscounts = discount + coupon;
    const taxableAmount = Math.max(subtotal - totalDiscounts, 0);
    const vat = taxableAmount * 0.05;
    const total = taxableAmount + vat;
    
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
            touchCart = {};
            renderTouchCart();
        } else {
            alert('Failed to suspend cart.');
        }
    });
}
</script>

<!-- Checkout Split Payment Modal -->
<div class="modal fade" id="checkoutPaymentModal" tabindex="-1" aria-hidden="true" style="z-index: 1055;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:var(--radius-md); border:none; box-shadow:var(--shadow-lg); background:#fff;">
            <div class="modal-header" style="border-bottom:1px solid var(--color-border); padding:16px 20px;">
                <h5 class="modal-title" style="font-weight:800; font-size:15px; color:var(--color-text); margin:0;"><i class="fas fa-credit-card"></i> Split Payment Terminal</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="font-size:12px; border:none; background:transparent; cursor:pointer;"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body" style="padding:20px;">
                <!-- Summary of Payable -->
                <div style="background:rgba(92,124,250,0.06); padding:12px 16px; border-radius:var(--radius-sm); margin-bottom:16px; display:flex; flex-direction:column; gap:6px; font-size:12px; color:var(--color-text-muted);">
                    <div style="display:flex; justify-content:space-between;">
                        <span>Subtotal:</span>
                        <span id="modalSubtotal" style="font-weight:700; color:var(--color-text);">৳0.00</span>
                    </div>
                    <div style="display:flex; justify-content:space-between;">
                        <span>VAT (5%):</span>
                        <span id="modalVat" style="font-weight:700; color:var(--color-text);">৳0.00</span>
                    </div>
                    <div style="display:flex; justify-content:space-between;">
                        <span>Discount Override:</span>
                        <span id="modalDiscount" style="font-weight:700; color:var(--color-text);">৳0.00</span>
                    </div>
                    <div style="display:flex; justify-content:space-between;">
                        <span>Coupon Discount:</span>
                        <span id="modalCoupon" style="font-weight:700; color:var(--color-text);">৳0.00</span>
                    </div>
                    <div style="display:flex; justify-content:space-between; border-top:1px dashed var(--color-border); padding-top:6px; font-size:14px; font-weight:800; color:var(--color-text);">
                        <span style="color:var(--color-text);">Grand Total:</span>
                        <strong id="modalPayableTotal" style="color:var(--color-primary); font-size:16px;">৳0.00</strong>
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
                                <label style="font-size:10px; font-weight:700; color:var(--color-text-muted);">Card Number (opt)</label>
                                <input type="text" id="splitCardNo" placeholder="Last 4 digits or Full" class="form-control" style="font-size:11px;">
                            </div>
                            <div style="display:grid; grid-template-columns: 1.5fr 2fr; gap:10px; align-items:center;">
                                <label style="font-size:10px; font-weight:700; color:var(--color-text-muted);">Reference Number *</label>
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
                                <select id="splitMobileProvider" class="form-control" style="font-size:11px; background:#fff; height:auto; padding:4px 8px;">
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
                    </div>

                    <!-- Split calculations display -->
                    <div style="margin-top:16px; border-top:1px dashed var(--color-border); padding-top:12px; font-size:12px; color:var(--color-text-muted); display:flex; flex-direction:column; gap:4px;">
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

                    <div style="display:flex; justify-content:end; gap:8px; margin-top:20px;">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="font-size:12px; padding:6px 12px; border-radius:var(--radius-pill); font-weight:700;">Cancel</button>
                        <button type="submit" id="btnConfirmPOSSale" class="btn btn-primary" disabled style="font-size:12px; padding:6px 16px; border-radius:var(--radius-pill); font-weight:700;">Confirm Sale</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<!-- Create Customer Modal -->
<div class="modal fade" id="createCustomerModal" tabindex="-1" aria-hidden="true" style="z-index: 1060;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:var(--radius-md); border:none; box-shadow:var(--shadow-lg); background:#fff;">
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
                                <select id="custNewGender" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:13px; outline:none; background:#fff;">
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
</div>
