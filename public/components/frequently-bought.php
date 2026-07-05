<?php
/**
 * ==========================================================================
 * public/components/frequently-bought.php
 * ==========================================================================
 * Frequently Bought Together bundle recommendations block.
 * Expects:
 *   - $product (array): the active detail product details.
 * ==========================================================================
 */

declare(strict_types=1);

if (!isset($product) || !is_array($product)) {
    return;
}

$productId = (int) $product['id'];
$fbtItems = [];

try {
    $pdo = db();

    // Query FBT items based on order lines matching orders containing this product
    $fbtStmt = $pdo->prepare('
        SELECT p.id, p.name, p.price, p.discount_price, p.thumbnail, p.slug, p.stock, p.unit, COUNT(*) as occurrence
        FROM order_items oi
        JOIN products p ON p.id = oi.product_id
        WHERE oi.order_id IN (
            SELECT order_id FROM order_items WHERE product_id = :pid1
        )
        AND oi.product_id != :pid2
        AND p.is_active = 1 AND p.stock > 0
        GROUP BY p.id
        ORDER BY occurrence DESC
        LIMIT 2
    ');
    $fbtStmt->execute(['pid1' => $productId, 'pid2' => $productId]);
    $fbtItems = $fbtStmt->fetchAll();

} catch (PDOException $e) {
    error_log('[components/frequently-bought.php] Query failed: ' . $e->getMessage());
}

// Skip rendering if no bought-together items are resolved
if (empty($fbtItems)) {
    return;
}

// Prepare bundle elements list (always starts with the current product)
$bundle = [];
$bundle[] = [
    'id'        => $productId,
    'name'      => $product['name'],
    'price'     => (float) ($product['discount_price'] ?? $product['price']),
    'thumbnail' => image_url($product['thumbnail'], 'products'),
    'slug'      => $product['slug'],
    'is_main'   => true
];

foreach ($fbtItems as $item) {
    $bundle[] = [
        'id'        => (int) $item['id'],
        'name'      => $item['name'],
        'price'     => (float) ($item['discount_price'] ?? $item['price']),
        'thumbnail' => image_url($item['thumbnail'], 'products'),
        'slug'      => $item['slug'],
        'is_main'   => false
    ];
}

$bundleTotal = array_sum(array_column($bundle, 'price'));
?>

<div class="frequently-bought-together-wrapper" style="margin-top:var(--space-5); margin-bottom:var(--space-5); background:var(--color-surface); border:1px solid var(--color-border); border-radius:var(--radius-lg); padding:var(--space-4) var(--space-5); box-shadow:var(--shadow-sm);">
    <h3 style="font-size:var(--fs-xs); font-weight:800; text-transform:uppercase; color:var(--color-text-faint); margin:0 0 var(--space-3) 0; letter-spacing:0.5px;">Frequently Bought Together</h3>
    
    <div class="fbt-grid-layout" style="display:flex; align-items:center; gap:var(--space-3); flex-wrap:wrap;">
        <!-- Images Line with plus sign connections -->
        <div class="fbt-images-line" style="display:flex; align-items:center; gap:var(--space-3); flex-wrap:wrap;">
            <?php foreach ($bundle as $idx => $bItem): ?>
                <?php if ($idx > 0): ?>
                    <span class="fbt-plus" style="font-size:20px; font-weight:800; color:var(--color-text-faint);">+</span>
                <?php endif; ?>
                <div class="fbt-img-box <?= $bItem['is_main'] ? 'is-main' : '' ?>" style="width:74px; height:74px; background:var(--color-bg); border:1px solid var(--color-border); border-radius:var(--radius-sm); padding:4px; display:flex; align-items:center; justify-content:center; relative;">
                    <img src="<?= e($bItem['thumbnail']) ?>" alt="<?= e($bItem['name']) ?>" style="max-width:100%; max-height:100%; object-fit:contain;">
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Summary panel calculations -->
        <div class="fbt-summary-panel" style="flex-grow:1; min-width:240px; border-left:1px solid var(--color-border); padding-left:var(--space-4); display:flex; flex-direction:column; gap:var(--space-2);">
            
            <!-- Checkbox togglers -->
            <div class="fbt-checkboxes-list" style="display:flex; flex-direction:column; gap:6px;">
                <?php foreach ($bundle as $bItem): ?>
                    <label class="auth-remember-checkbox" style="font-size:11px; font-weight:700; color:var(--color-text-muted);">
                        <input type="checkbox" 
                               class="fbt-bundle-checkbox" 
                               data-id="<?= $bItem['id'] ?>" 
                               data-price="<?= $bItem['price'] ?>" 
                               value="<?= $bItem['id'] ?>" 
                               checked 
                               <?= $bItem['is_main'] ? 'disabled' : '' ?>>
                        <span>
                            <?= $bItem['is_main'] ? '<strong>This Item:</strong>' : '' ?> 
                            <?= e(mb_strimwidth($bItem['name'], 0, 36, '...')) ?> 
                            (<strong style="color:var(--color-primary);"><?= format_price($bItem['price']) ?></strong>)
                        </span>
                    </label>
                <?php endforeach; ?>
            </div>

            <!-- Price & Button -->
            <div style="margin-top:var(--space-2); border-top:1px dashed var(--color-border); padding-top:var(--space-2); display:flex; justify-content:space-between; align-items:center;">
                <div style="font-size:11px; font-weight:700; color:var(--color-text-muted);">
                    <span>Bundle Total:</span>
                    <strong class="fbt-bundle-price" style="font-size:15px; color:var(--color-primary); font-weight:800; display:block; margin-top:2px;" data-base="<?= $bundleTotal ?>">
                        <?= format_price($bundleTotal) ?>
                    </strong>
                </div>
                <button type="button" class="btn btn-primary btn-add-fbt-bundle" style="font-size:11px; padding:10px 18px; border:none; border-radius:var(--radius-pill); font-weight:700;">
                    <i class="fas fa-cart-shopping"></i> Add Bundle to Cart
                </button>
            </div>

        </div>
    </div>
</div>

<script>
(function() {
    // Dynamic FBT checkbox total updates
    const wrapper = document.querySelector('.frequently-bought-together-wrapper');
    if (!wrapper) return;

    const checkboxes = wrapper.querySelectorAll('.fbt-bundle-checkbox');
    const priceEl = wrapper.querySelector('.fbt-bundle-price');
    const btnAdd = wrapper.querySelector('.btn-add-fbt-bundle');

    function updateFbtTotal() {
        let total = 0;
        checkboxes.forEach(cb => {
            if (cb.checked) {
                total += parseFloat(cb.dataset.price);
            }
        });
        
        // Format price (assuming standard Bangladeshi format: ৳xx.xx)
        if (priceEl) {
            priceEl.textContent = '৳' + total.toFixed(2);
        }
    }

    checkboxes.forEach(cb => {
        cb.addEventListener('change', updateFbtTotal);
    });

    // Add FBT checked items to cart via AJAX POST calls sequentially
    btnAdd?.addEventListener('click', async () => {
        btnAdd.disabled = true;
        const origHtml = btnAdd.innerHTML;
        btnAdd.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';

        let addedCount = 0;
        let lastCartCount = 0;

        for (const cb of checkboxes) {
            if (cb.checked) {
                const pid = cb.dataset.id;
                try {
                    const json = await window.apiPost('ajax/add_to_cart.php', {
                        product_id: pid,
                        quantity: '1'
                    });
                    if (json.success) {
                        addedCount++;
                        lastCartCount = json.data?.cart_count ?? lastCartCount;
                    }
                } catch (err) {
                    console.error('Failed to add bundle product ID ' + pid, err);
                }
            }
        }

        btnAdd.disabled = false;
        btnAdd.innerHTML = origHtml;

        if (addedCount > 0) {
            window.showToast?.('Successfully added ' + addedCount + ' bundle items to your cart!', 'success');
            
            // Update cart header counter
            const badge = document.getElementById('cartCount');
            if (badge && lastCartCount > 0) {
                badge.textContent = lastCartCount.toString();
            }
        } else {
            window.showToast?.('Please check at least one product to add.', 'error');
        }
    });

})();
</script>
