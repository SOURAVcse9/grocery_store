<?php
/**
 * ==============================================================================
 * GroCo Enterprise POS — Cart Engine Service
 * ==============================================================================
 * High-precision cart calculation supporting decimal/weighted units,
 * line-item discounts, cart promotions, and tax computations.
 * ==============================================================================
 */

declare(strict_types=1);

namespace Groco\Pos;

use InvalidArgumentException;

class PosCartService
{
    /**
     * Compute full cart breakdown with strict decimal precision.
     *
     * @param array $items Array of cart items:
     *                     [['id' => 1, 'name' => 'Rice', 'price' => 85.00, 'qty' => 2.75, 'unit' => 'kg', 'discount' => 0], ...]
     * @param float $cartDiscount Overall cart discount override
     * @param float $couponDiscount Coupon deduction amount
     * @param float $taxRate Tax rate percentage (e.g. 0.00 for grocery exempt or 5.00)
     * @return array
     */
    public function calculate(
        array $items,
        float $cartDiscount = 0.00,
        float $couponDiscount = 0.00,
        float $taxRate = 0.00
    ): array {
        if (empty($items)) {
            return [
                'items'            => [],
                'item_count'       => 0,
                'total_quantity'   => 0.000,
                'subtotal'         => 0.00,
                'line_discounts'   => 0.00,
                'cart_discount'    => 0.00,
                'coupon_discount'  => 0.00,
                'total_discount'   => 0.00,
                'taxable_amount'   => 0.00,
                'tax_amount'       => 0.00,
                'grand_total'      => 0.00,
                'rounding'         => 0.00
            ];
        }

        $processedItems = [];
        $subtotal = 0.00;
        $totalQuantity = 0.000;
        $totalLineDiscounts = 0.00;

        foreach ($items as $item) {
            $id = (int)($item['id'] ?? 0);
            $name = trim((string)($item['name'] ?? 'Product'));
            $unitPrice = (float)($item['price'] ?? 0.00);
            $qty = (float)($item['qty'] ?? ($item['quantity'] ?? 1.0));
            $unit = trim((string)($item['unit'] ?? 'pcs'));
            $isWeighted = !empty($item['is_weighted']) || in_array(strtolower($unit), ['kg', 'gram', 'gm', 'liter', 'l', 'ml'], true);
            $lineDiscount = (float)($item['discount'] ?? 0.00);
            $sku = (string)($item['sku'] ?? 'N/A');
            $barcode = (string)($item['barcode'] ?? '');

            if ($id <= 0) {
                throw new InvalidArgumentException("Invalid product ID in cart item: {$name}");
            }
            if ($qty <= 0) {
                throw new InvalidArgumentException("Product '{$name}' quantity must be greater than zero.");
            }
            if ($unitPrice < 0) {
                throw new InvalidArgumentException("Product '{$name}' price cannot be negative.");
            }

            // Line calculations
            $grossLineTotal = round($unitPrice * $qty, 2);
            $appliedLineDiscount = min($lineDiscount, $grossLineTotal);
            $netLineTotal = max(0.00, $grossLineTotal - $appliedLineDiscount);

            $subtotal += $grossLineTotal;
            $totalQuantity += $qty;
            $totalLineDiscounts += $appliedLineDiscount;

            $processedItems[] = [
                'id'              => $id,
                'name'            => $name,
                'sku'             => $sku,
                'barcode'         => $barcode,
                'unit'            => $unit,
                'is_weighted'     => $isWeighted ? 1 : 0,
                'unit_price'      => $unitPrice,
                'quantity'        => $qty,
                'gross_total'     => $grossLineTotal,
                'discount'        => $appliedLineDiscount,
                'line_total'      => $netLineTotal,
                'price_override'  => !empty($item['price_override']) ? 1 : 0,
                'override_reason' => (string)($item['override_reason'] ?? '')
            ];
        }

        $cartDiscount = max(0.00, $cartDiscount);
        $couponDiscount = max(0.00, $couponDiscount);
        $totalDiscounts = round($totalLineDiscounts + $cartDiscount + $couponDiscount, 2);
        
        $taxableAmount = max(0.00, round($subtotal - $totalDiscounts, 2));
        $taxAmount = ($taxRate > 0) ? round($taxableAmount * ($taxRate / 100), 2) : 0.00;
        
        $rawGrandTotal = round($taxableAmount + $taxAmount, 2);
        $roundedGrandTotal = round($rawGrandTotal, 2);
        $rounding = round($roundedGrandTotal - $rawGrandTotal, 2);

        return [
            'items'            => $processedItems,
            'item_count'       => count($processedItems),
            'total_quantity'   => $totalQuantity,
            'subtotal'         => round($subtotal, 2),
            'line_discounts'   => round($totalLineDiscounts, 2),
            'cart_discount'    => $cartDiscount,
            'coupon_discount'  => $couponDiscount,
            'total_discount'   => $totalDiscounts,
            'taxable_amount'   => $taxableAmount,
            'tax_amount'       => $taxAmount,
            'grand_total'      => $roundedGrandTotal,
            'rounding'         => $rounding
        ];
    }
}
