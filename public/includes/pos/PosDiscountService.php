<?php
/**
 * ==============================================================================
 * GroCo Enterprise POS — Discount & Promotions Service
 * ==============================================================================
 * Discount rules, coupon code verification, and manager override limit checks.
 * ==============================================================================
 */

declare(strict_types=1);

namespace Groco\Pos;

use PDO;
use Exception;

class PosDiscountService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Get maximum allowed discount percentage for cashier without supervisor override.
     */
    public function getMaxCashierDiscountPct(): float
    {
        try {
            $stmt = $this->pdo->query("SELECT value FROM settings WHERE key_name = 'pos_max_discount_pct'");
            $val = $stmt->fetchColumn();
            return ($val !== false && (float)$val > 0) ? (float)$val : 15.00;
        } catch (Exception $e) {
            return 15.00;
        }
    }

    /**
     * Validate requested discounts against cashier authority.
     */
    public function validateDiscounts(
        float $subtotal,
        float $totalDiscount,
        bool $hasOverridePermission = false
    ): void {
        if ($subtotal <= 0.00 || $totalDiscount <= 0.00) {
            return;
        }

        $discountPct = ($totalDiscount / $subtotal) * 100;
        $maxAllowed = $this->getMaxCashierDiscountPct();

        if ($discountPct > $maxAllowed && !$hasOverridePermission) {
            throw new Exception(
                "Requested discount (" . round($discountPct, 1) . "%) exceeds cashier threshold limit ({$maxAllowed}%). Manager / Supervisor override key required."
            );
        }
    }

    /**
     * Validate and apply a promotional coupon code.
     */
    public function applyCoupon(string $couponCode, float $subtotal): array
    {
        $couponCode = strtoupper(trim($couponCode));
        if ($couponCode === '') {
            return ['is_valid' => false, 'discount' => 0.00, 'coupon_id' => null];
        }

        $stmt = $this->pdo->prepare("
            SELECT id, code, discount_type, discount_value, min_order_amount, max_discount, starts_at, expires_at, is_active 
            FROM coupons 
            WHERE code = ? AND is_active = 1
            LIMIT 1
        ");
        $stmt->execute([$couponCode]);
        $coupon = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$coupon) {
            throw new Exception("Coupon code '{$couponCode}' not found or is inactive.");
        }

        $now = date('Y-m-d H:i:s');
        if (!empty($coupon['starts_at']) && $coupon['starts_at'] > $now) {
            throw new Exception("Coupon '{$couponCode}' is not yet active.");
        }
        if (!empty($coupon['expires_at']) && $coupon['expires_at'] < $now) {
            throw new Exception("Coupon '{$couponCode}' has expired.");
        }

        $minOrder = (float)($coupon['min_order_amount'] ?? 0.00);
        if ($subtotal < $minOrder) {
            throw new Exception("Coupon requires minimum cart subtotal of ৳{$minOrder}.");
        }

        $discountValue = (float)$coupon['discount_value'];
        $discountAmount = 0.00;

        if ($coupon['discount_type'] === 'percentage') {
            $discountAmount = ($subtotal * ($discountValue / 100));
            $maxDiscount = (float)($coupon['max_discount'] ?? 0.00);
            if ($maxDiscount > 0 && $discountAmount > $maxDiscount) {
                $discountAmount = $maxDiscount;
            }
        } else {
            $discountAmount = min($discountValue, $subtotal);
        }

        return [
            'is_valid'   => true,
            'coupon_id'  => (int)$coupon['id'],
            'code'       => $coupon['code'],
            'discount'   => round($discountAmount, 2)
        ];
    }
}
