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
        SELECT p.id, p.name, p.price, p.stock, p.sku, p.barcode, p.image, c.id AS category_id, b.id AS brand_id
        FROM products p
        LEFT JOIN categories c ON c.id = p.category_id
        LEFT JOIN brands b ON b.id = p.brand_id
        WHERE p.deleted_at IS NULL AND p.is_active = 1 AND p.stock > 0
        ORDER BY p.name ASC
    ")->fetchAll();

    // Load registered customers for selection
    $customers = $pdo->query("SELECT id, full_name, phone, wallet_balance, reward_points FROM users WHERE role_id != 1 AND deleted_at IS NULL ORDER BY full_name ASC")->fetchAll();

} catch (PDOException $e) {
    error_log('[admin/pos/index] option loading failed: ' . $e->getMessage());
    $categories = $brands = $products = $customers = [];
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
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; border-bottom:1px solid var(--color-border); padding-bottom:6px;">
                    <h3 style="font-size:14px; font-weight:800; margin:0;"><i class="fas fa-shopping-basket"></i> POS Cart</h3>
                    <!-- Customer selector -->
                    <select id="posCustomerSelect" onchange="updateLoyaltyUI();" style="width:180px; padding:4px 8px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:12px; background:#fff;">
                        <option value="0">Walk-in Customer</option>
                        <?php foreach ($customers as $c): ?>
                            <option value="<?= $c['id'] ?>" data-wallet="<?= $c['wallet_balance'] ?>" data-points="<?= $c['reward_points'] ?>"><?= e($c['full_name']) ?> (<?= e($c['phone']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
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
                    
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span>Discount Override (৳):</span>
                        <input type="number" id="posCartDiscount" min="0" value="0" onchange="recalculatePOSBalances();" style="width:70px; padding:2px 6px; border:1px solid var(--color-border); border-radius:var(--radius-sm); text-align:right;">
                    </div>

                    <div style="display:flex; justify-content:space-between; border-top:1px dashed var(--color-border); padding-top:6px; font-size:14px; color:var(--color-text);">
                        <span>Total Payable Due:</span>
                        <strong id="posCartTotalPayable" style="color:var(--color-primary); font-size:15px;">৳0.00</strong>
                    </div>
                </div>

                <!-- Payment split fields -->
                <div style="margin-bottom:12px; border-top:1px solid var(--color-border); padding-top:10px;">
                    <span style="font-size:10px; font-weight:700; color:var(--color-text-faint); text-transform:uppercase; display:block; margin-bottom:6px;">Split payment methods (৳)</span>
                    
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:6px; margin-bottom:10px;" class="grid-2">
                        <input type="number" id="splitCash" min="0" value="0" placeholder="Cash Amount" style="padding:6px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:11px;">
                        <input type="number" id="splitCard" min="0" value="0" placeholder="Card Amount" style="padding:6px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:11px;">
                    </div>
                    
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:6px;" class="grid-2">
                        <input type="number" id="splitBkash" min="0" value="0" placeholder="bKash Amount" style="padding:6px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:11px;">
                        <input type="number" id="splitWallet" min="0" value="0" placeholder="Wallet Credit" style="padding:6px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:11px;">
                    </div>
                </div>

                <div style="display:grid; grid-template-columns: 1fr 2fr; gap:8px;" class="grid-2">
                    <button type="button" onclick="suspendPOSCart();" class="btn btn-secondary" style="border-radius:var(--radius-pill); font-weight:700; padding:10px; font-size:12px;"><i class="fas fa-hand-holding"></i> Hold</button>
                    <button type="button" onclick="submitPOSCheckoutFinalist();" class="btn btn-primary" style="border-radius:var(--radius-pill); font-weight:700; padding:10px; font-size:12px;"><i class="fas fa-shopping-bag"></i> Checkout & Print</button>
                </div>
            </div>

        </div>
    </div>
<?php endif; ?>

<script>
let touchCart = {};
window.touchCart = touchCart;

function filterPOSCatalog() {
    const search = document.getElementById('posFilterSearch').value.toLowerCase();
    const cat = document.getElementById('posFilterCat').value;
    const brand = document.getElementById('posFilterBrand').value;
    
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
    const val = sel.value;
    const widget = document.getElementById('loyaltyWidget');
    
    if (val === '0') {
        widget.style.display = 'none';
    } else {
        const option = sel.options[sel.selectedIndex];
        document.getElementById('lblWallet').innerText = '৳' + parseFloat(option.getAttribute('data-wallet')).toFixed(2);
        document.getElementById('lblPoints').innerText = option.getAttribute('data-points') + ' pts';
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
    if (keys.length === 0) {
        wrapper.innerHTML = '<p style="text-align:center; color:var(--color-text-faint); font-size:11px; margin:16px 0;">Checkout list is empty.</p>';
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
    
    recalculatePOSBalances();
}

function recalculatePOSBalances() {
    window.recalculatePOSBalances = recalculatePOSBalances;
    let subtotal = 0;
    Object.keys(touchCart).forEach(k => {
        subtotal += (touchCart[k].price * touchCart[k].qty);
    });
    
    const discount = parseFloat(document.getElementById('posCartDiscount').value) || 0;
    const total = Math.max(subtotal - discount, 0);
    
    document.getElementById('posCartSubtotal').innerText = '৳' + subtotal.toFixed(2);
    document.getElementById('posCartTotalPayable').innerText = '৳' + total.toFixed(2);
    
    // Default split values
    document.getElementById('splitCash').value = total.toFixed(2);
    document.getElementById('splitCard').value = '0';
    document.getElementById('splitBkash').value = '0';
    document.getElementById('splitWallet').value = '0';
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

function submitPOSCheckoutFinalist() {
    const keys = Object.keys(touchCart);
    if (keys.length === 0) {
        alert('POS Cart is empty.');
        return;
    }
    
    const discount = parseFloat(document.getElementById('posCartDiscount').value) || 0;
    const cash = parseFloat(document.getElementById('splitCash').value) || 0;
    const card = parseFloat(document.getElementById('splitCard').value) || 0;
    const bkash = parseFloat(document.getElementById('splitBkash').value) || 0;
    const wallet = parseFloat(document.getElementById('splitWallet').value) || 0;
    
    const customerId = document.getElementById('posCustomerSelect').value;
    const itemsData = keys.map(k => touchCart[k]);
    
    const formData = new FormData();
    formData.append('items', JSON.stringify(itemsData));
    formData.append('discount', discount.toString());
    formData.append('cash', cash.toString());
    formData.append('card', card.toString());
    formData.append('bkash', bkash.toString());
    formData.append('wallet', wallet.toString());
    formData.append('customer_id', customerId);
    formData.append('csrf_token', '<?= csrf_token() ?>');
    
    fetch('checkout.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert('Checkout finalized successfully!');
            // Print receipt
            window.open('receipts.php?id=' + data.order_id, '_blank', 'width=400,height=600');
            touchCart = {};
            renderTouchCart();
            // Refresh customer balance indicators if applicable
            if (customerId !== '0') {
                location.reload();
            }
        } else {
            alert('POS Checkout failed: ' + data.error);
        }
    })
    .catch(err => {
        console.error(err);
        alert('Server communications error during checkout.');
    });
}
</script>
<script src="<?= BASE_URL ?>/../admin/assets/js/pos.js"></script>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
</div>
