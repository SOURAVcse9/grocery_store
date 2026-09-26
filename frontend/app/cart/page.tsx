'use client';

import React, { useState, useEffect } from 'react';
import Link from 'next/link';
import { getCart, updateCartItem } from '@/lib/api/cart';
import { validateCoupon } from '@/lib/api/coupons';
import { formatPrice } from '@/lib/utils';
import { Trash2, ShoppingBag, ArrowRight, Tag } from 'lucide-react';
import { CartSummary } from '@/types';

export default function CartPage() {
  const [cart, setCart] = useState<CartSummary | null>(null);
  const [loading, setLoading] = useState(true);
  const [couponCode, setCouponCode] = useState('');
  const [couponDiscount, setCouponDiscount] = useState(0);
  const [couponMsg, setCouponMsg] = useState<{ text: string; isError: boolean } | null>(null);

  const loadCart = async () => {
    try {
      const res = await getCart();
      setCart(res.data);
    } catch (err) {
      console.error(err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadCart();
  }, []);

  const handleUpdateQty = async (productId: number, qty: number) => {
    try {
      await updateCartItem(productId, qty);
      loadCart();
    } catch (err: any) {
      alert(err.message || 'Error updating quantity');
    }
  };

  const handleApplyCoupon = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!cart || !couponCode.trim()) return;

    try {
      const res = await validateCoupon(couponCode.trim(), cart.subtotal);
      if (res.data.valid) {
        setCouponDiscount(res.data.discount_amount || 0);
        setCouponMsg({ text: res.data.message || 'Coupon applied!', isError: false });
      } else {
        setCouponDiscount(0);
        setCouponMsg({ text: res.data.message || 'Invalid coupon', isError: true });
      }
    } catch (err: any) {
      setCouponDiscount(0);
      setCouponMsg({ text: err.message || 'Error applying coupon', isError: true });
    }
  };

  if (loading) {
    return <div className="py-20 text-center text-neutral-500">Loading your shopping cart...</div>;
  }

  const items = cart?.items || [];
  const subtotal = cart?.subtotal || 0;
  const finalTotal = Math.max(0, subtotal - couponDiscount);

  if (items.length === 0) {
    return (
      <div className="py-20 text-center max-w-md mx-auto space-y-4">
        <ShoppingBag className="w-16 h-16 text-neutral-300 mx-auto" />
        <h2 className="text-2xl font-extrabold text-neutral-900">Your Cart is Empty</h2>
        <p className="text-sm text-neutral-500">Looks like you haven't added any fresh groceries to your cart yet.</p>
        <Link
          href="/products"
          className="inline-flex items-center gap-2 bg-brand-600 text-white font-bold text-sm px-6 py-3 rounded-full hover:bg-brand-700 shadow-md"
        >
          Start Shopping <ArrowRight className="w-4 h-4" />
        </Link>
      </div>
    );
  }

  return (
    <div className="space-y-8">
      <h1 className="text-3xl font-extrabold text-neutral-900 tracking-tight">Shopping Cart</h1>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
        {/* Cart Items List */}
        <div className="lg:col-span-2 space-y-4">
          <div className="bg-white border border-neutral-200 rounded-3xl p-6 divide-y divide-neutral-100 shadow-sm">
            {items.map((item) => (
              <div key={item.product_id} className="py-4 first:pt-0 last:pb-0 flex items-center gap-4">
                <img
                  src={item.image_url}
                  alt={item.name}
                  className="w-20 h-20 object-cover rounded-xl border border-neutral-100 bg-neutral-50"
                />

                <div className="flex-1 min-w-0">
                  <h3 className="text-sm font-bold text-neutral-900 truncate">{item.name}</h3>
                  <p className="text-xs text-brand-600 font-semibold">{formatPrice(item.unit_price)} each</p>
                  <p className="text-xs text-neutral-400 mt-1">Total: {formatPrice(item.line_total)}</p>
                </div>

                <div className="flex items-center gap-2">
                  <select
                    value={item.quantity}
                    onChange={(e) => handleUpdateQty(item.product_id, parseInt(e.target.value, 10))}
                    className="text-xs border border-neutral-300 rounded-lg p-1.5 focus:ring-brand-500 focus:border-brand-500"
                  >
                    {[...Array(Math.min(10, item.stock || 10))].map((_, i) => (
                      <option key={i + 1} value={i + 1}>
                        {i + 1}
                      </option>
                    ))}
                  </select>

                  <button
                    onClick={() => handleUpdateQty(item.product_id, 0)}
                    className="p-2 text-neutral-400 hover:text-red-500 transition-colors"
                    title="Remove item"
                  >
                    <Trash2 className="w-4 h-4" />
                  </button>
                </div>
              </div>
            ))}
          </div>
        </div>

        {/* Order Summary & Coupon Card */}
        <div className="space-y-6">
          <div className="bg-white border border-neutral-200 rounded-3xl p-6 space-y-6 shadow-sm">
            <h2 className="text-lg font-extrabold text-neutral-900">Order Summary</h2>

            {/* Coupon Code Input */}
            <form onSubmit={handleApplyCoupon} className="space-y-2">
              <div className="flex gap-2">
                <input
                  type="text"
                  placeholder="Coupon code"
                  value={couponCode}
                  onChange={(e) => setCouponCode(e.target.value)}
                  className="flex-1 text-xs border border-neutral-300 rounded-xl px-3 py-2 uppercase focus:ring-brand-500 focus:border-brand-500"
                />
                <button
                  type="submit"
                  className="bg-neutral-800 text-white text-xs font-bold px-4 py-2 rounded-xl hover:bg-neutral-900 transition-colors"
                >
                  Apply
                </button>
              </div>
              {couponMsg && (
                <p className={`text-xs font-semibold ${couponMsg.isError ? 'text-red-600' : 'text-green-600'}`}>
                  {couponMsg.text}
                </p>
              )}
            </form>

            <div className="space-y-3 text-sm pt-4 border-t border-neutral-100">
              <div className="flex justify-between text-neutral-600">
                <span>Subtotal ({items.length} items)</span>
                <span>{formatPrice(subtotal)}</span>
              </div>

              {couponDiscount > 0 && (
                <div className="flex justify-between text-green-600 font-semibold">
                  <span>Coupon Discount</span>
                  <span>-{formatPrice(couponDiscount)}</span>
                </div>
              )}

              <div className="flex justify-between text-neutral-600">
                <span>Estimated Delivery</span>
                <span className="text-brand-600 font-semibold">Calculated at Checkout</span>
              </div>

              <div className="flex justify-between text-neutral-600">
                <span>VAT (Zero-VAT Compliant)</span>
                <span>৳0.00</span>
              </div>

              <div className="flex justify-between text-base font-extrabold text-neutral-900 pt-3 border-t border-neutral-200">
                <span>Estimated Total</span>
                <span>{formatPrice(finalTotal)}</span>
              </div>
            </div>

            <Link
              href="/checkout"
              className="block w-full text-center bg-brand-600 text-white py-3.5 rounded-full font-bold text-sm hover:bg-brand-700 shadow-md transition-all active:scale-95"
            >
              Proceed to Checkout
            </Link>
          </div>
        </div>
      </div>
    </div>
  );
}
