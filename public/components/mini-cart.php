<?php
/**
 * ==========================================================================
 * public/components/mini-cart.php
 * ==========================================================================
 * Reusable Mini-Cart content template.
 * Expects:
 *   - $cartItems (array): list of items in the cart.
 *   - $subtotal (float): total sum of item prices * quantity.
 * ==========================================================================
 */

declare(strict_types=1);

$itemCount = 0;
if (isset($cartItems) && is_array($cartItems)) {
    foreach ($cartItems as $item) {
        $itemCount += (int) $item['quantity'];
    }
}
?>
<div class="mini-cart-header">
    <span class="mini-cart-title"><i class="fas fa-shopping-basket"></i> <?= t('cart') ?? 'My Cart' ?> (<?= $itemCount ?>)</span>
    <button type="button" class="mini-cart-close-btn" id="miniCartCloseBtn" aria-label="Close Cart">&times;</button>
</div>

<div class="mini-cart-items-wrapper">
    <?php if (!empty($cartItems)): ?>
        <ul class="mini-cart-list">
            <?php foreach ($cartItems as $item): 
                $price = (float) $item['price'];
                $qty = (int) $item['quantity'];
                $thumbUrl = image_url($item['thumbnail'], 'products');
                $prodUrl = url_for('product.php?slug=' . e($item['slug']));
            ?>
                <li class="mini-cart-item" data-product-id="<?= (int) $item['product_id'] ?>">
                    <a href="<?= $prodUrl ?>" class="mini-cart-img-box">
                        <img src="<?= e($thumbUrl) ?>" alt="<?= e($item['name']) ?>">
                    </a>
                    <div class="mini-cart-info">
                        <h4 class="mini-cart-name"><a href="<?= $prodUrl ?>"><?= e($item['name']) ?></a></h4>
                        <div class="mini-cart-qty-price">
                            <span class="mini-cart-qty"><?= $qty ?></span> x <span class="mini-cart-price"><?= format_price($price) ?></span>
                        </div>
                    </div>
                    <button type="button" class="mini-cart-remove-btn btn-remove-cart-item" data-product-id="<?= (int) $item['product_id'] ?>" title="Remove item" aria-label="Remove Item">
                        <i class="far fa-trash-can"></i>
                    </button>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <div class="mini-cart-empty">
            <div class="mini-cart-empty-icon"><i class="fas fa-cart-shopping"></i></div>
            <p>Your cart is empty</p>
            <a href="<?= url_for('products.php') ?>" class="btn btn-primary" style="font-size:11px; padding:6px 16px; border-radius:var(--radius-pill); border:none;">Shop Now</a>
        </div>
    <?php endif; ?>
</div>

<?php if (!empty($cartItems)): ?>
    <div class="mini-cart-footer">
        <div class="mini-cart-subtotal-row">
            <span>Subtotal:</span>
            <strong><?= format_price($subtotal) ?></strong>
        </div>
        <div class="mini-cart-buttons">
            <a href="<?= url_for('cart.php') ?>" class="btn btn-secondary mini-cart-btn">View Cart</a>
            <a href="<?= url_for('checkout.php') ?>" class="btn btn-primary mini-cart-btn">Checkout</a>
        </div>
    </div>
<?php endif; ?>
