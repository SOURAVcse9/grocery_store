<?php
/**
 * ==============================================================================
 * GroCo Coupon Service Layer
 * ==============================================================================
 * Centralized business logic for coupon validation, discount computation,
 * minimum order limits, expiration dates, and usage counting.
 * ==============================================================================
 */

declare(strict_types=1);

class CouponService
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getConnection();
    }

    /**
     * Validate a coupon code against cart subtotal
     */
    public function validate(string $code, float $cartSubtotal): array
    {
        $cleanCode = strtoupper(trim($code));
        if ($cleanCode === '') {
            return ['valid' => false, 'message' => 'Please enter a coupon code.'];
        }

        $stmt = $this->pdo->prepare("
            SELECT * FROM coupons 
            WHERE UPPER(code) = ? AND is_active = 1
            LIMIT 1
        ");
        $stmt->execute([$cleanCode]);
        $coupon = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$coupon) {
            return ['valid' => false, 'message' => 'Invalid or expired coupon code.'];
        }

        // Check start and end dates if defined
        $now = date('Y-m-d H:i:s');
        if (!empty($coupon['valid_from']) && $coupon['valid_from'] > $now) {
            return ['valid' => false, 'message' => 'This coupon promotion has not started yet.'];
        }
        if (!empty($coupon['valid_until']) && $coupon['valid_until'] < $now) {
            return ['valid' => false, 'message' => 'This coupon has expired.'];
        }

        // Check minimum purchase amount
        $minSpend = (float)($coupon['min_order_amount'] ?? 0);
        if ($minSpend > 0 && $cartSubtotal < $minSpend) {
            return ['valid' => false, 'message' => sprintf('Minimum order amount of $%.2f required to use this coupon.', $minSpend)];
        }

        // Compute discount amount
        $discountType = $coupon['type'] ?? 'fixed';
        $discountAmount = 0.0;

        if ($discountType === 'percentage' || (float)($coupon['discount_percent'] ?? 0) > 0) {
            $percent = (float)($coupon['discount_percent'] ?? 0);
            $discountAmount = ($cartSubtotal * $percent) / 100;
            $maxDiscount = (float)($coupon['max_discount_amount'] ?? 0);
            if ($maxDiscount > 0 && $discountAmount > $maxDiscount) {
                $discountAmount = $maxDiscount;
            }
        } else {
            $fixed = (float)($coupon['discount_amount'] ?? 0);
            $discountAmount = min($cartSubtotal, $fixed);
        }

        return [
            'valid'           => true,
            'coupon_id'       => (int)$coupon['id'],
            'code'            => $cleanCode,
            'discount_type'   => $discountType,
            'discount_amount' => round($discountAmount, 2),
            'final_subtotal'  => max(0.00, round($cartSubtotal - $discountAmount, 2)),
            'message'         => 'Coupon applied successfully!'
        ];
    }
}
