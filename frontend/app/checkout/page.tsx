'use client';

import React, { useState, useEffect } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { getCart, CartResponse } from '@/lib/api/cart';
import { createOrder } from '@/lib/api/orders';
import { getAddresses, Address } from '@/lib/api/addresses';
import { validateCoupon } from '@/lib/api/coupons';
import { formatPrice } from '@/lib/utils';
import { ShieldCheck, Truck, CreditCard, CheckCircle2, AlertCircle, ArrowLeft } from 'lucide-react';

export default function CheckoutPage() {
  const router = useRouter();
  const [cart, setCart] = useState<CartResponse | null>(null);
  const [addresses, setAddresses] = useState<Address[]>([]);
  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [orderComplete, setOrderComplete] = useState<{ id: number; number: string } | null>(null);
  const [error, setError] = useState<string | null>(null);

  // Form states
  const [selectedAddressId, setSelectedAddressId] = useState<number | null>(null);
  const [paymentMethod, setPaymentMethod] = useState<'cod' | 'bkash' | 'nagad' | 'stripe'>('cod');
  const [couponCode, setCouponCode] = useState('');
  const [discountAmount, setDiscountAmount] = useState(0);
  const [couponApplied, setCouponApplied] = useState(false);
  const [couponError, setCouponError] = useState('');
  const [customerEmail, setCustomerEmail] = useState('');
  const [note, setNote] = useState('');

  const deliveryCharge = 5.0;

  useEffect(() => {
    async function loadCheckoutData() {
      try {
        const [cartRes, addrRes] = await Promise.all([
          getCart(),
          getAddresses().catch(() => ({ data: [] }))
        ]);

        if (cartRes.data) {
          setCart(cartRes.data);
        }
        if (addrRes.data && Array.isArray(addrRes.data)) {
          setAddresses(addrRes.data);
          const defaultAddr = addrRes.data.find(a => a.is_default);
          if (defaultAddr) setSelectedAddressId(defaultAddr.id);
          else if (addrRes.data.length > 0) setSelectedAddressId(addrRes.data[0].id);
        }
      } catch (err: any) {
        setError('Failed to load cart. Please try again.');
      } finally {
        setLoading(false);
      }
    }
    loadCheckoutData();
  }, []);

  const handleApplyCoupon = async () => {
    if (!couponCode.trim() || !cart) return;
    setCouponError('');
    try {
      const res = await validateCoupon(couponCode.trim(), cart.subtotal);
      if (res.data && res.data.valid) {
        setDiscountAmount(res.data.discount_amount);
        setCouponApplied(true);
      } else {
        setCouponError(res.message || 'Invalid coupon code');
      }
    } catch (err: any) {
      setCouponError(err.message || 'Invalid coupon');
    }
  };

  const handleSubmitOrder = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!cart || cart.items.length === 0) return;

    setSubmitting(true);
    setError(null);

    try {
      const orderPayload = {
        items: cart.items.map(item => ({
          product_id: item.product_id,
          quantity: item.quantity
        })),
        address_id: selectedAddressId || undefined,
        discount_amount: discountAmount,
        delivery_charge: deliveryCharge,
        payment_method: paymentMethod,
        note: note.trim() || undefined,
        customer_email: customerEmail.trim() || undefined
      };

      const res = await createOrder(orderPayload);
      if (res.data) {
        setOrderComplete({
          id: res.data.order_id,
          number: res.data.order_number
        });
      }
    } catch (err: any) {
      setError(err.message || 'Order creation failed. Please check your details and try again.');
    } finally {
      setSubmitting(false);
    }
  };

  if (loading) {
    return (
      <div className="max-w-7xl mx-auto px-4 py-16 text-center">
        <div className="inline-block animate-spin rounded-full h-10 w-10 border-4 border-emerald-600 border-t-transparent mb-4" />
        <p className="text-gray-600 font-medium">Securing checkout session...</p>
      </div>
    );
  }

  if (orderComplete) {
    return (
      <div className="max-w-3xl mx-auto px-4 py-16 text-center">
        <div className="w-20 h-20 bg-emerald-100 text-emerald-600 rounded-full flex items-center justify-center mx-auto mb-6">
          <CheckCircle2 className="w-12 h-12" />
        </div>
        <h1 className="text-3xl font-extrabold text-gray-900 mb-2">Order Confirmed!</h1>
        <p className="text-gray-600 mb-6">
          Thank you for choosing GroCo. Your order reference is{' '}
          <span className="font-bold text-gray-900">#{orderComplete.number}</span>.
        </p>
        <div className="bg-white border rounded-xl p-6 shadow-sm mb-8 text-left">
          <h3 className="font-semibold text-gray-900 mb-2">What happens next?</h3>
          <p className="text-sm text-gray-600 mb-2">
            • Our fulfillment team has received your order and is packing your fresh items.
          </p>
          <p className="text-sm text-gray-600">
            • You will receive a confirmation message once dispatched.
          </p>
        </div>
        <div className="flex justify-center gap-4">
          <Link
            href="/products"
            className="px-6 py-3 bg-emerald-600 text-white rounded-lg font-semibold hover:bg-emerald-700 transition"
          >
            Continue Shopping
          </Link>
          <Link
            href={`/account/orders`}
            className="px-6 py-3 bg-gray-100 text-gray-800 rounded-lg font-semibold hover:bg-gray-200 transition"
          >
            View Order History
          </Link>
        </div>
      </div>
    );
  }

  const subtotal = cart ? cart.subtotal : 0;
  const finalTotal = Math.max(0, subtotal + deliveryCharge - discountAmount);

  return (
    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <div className="mb-6 flex items-center justify-between">
        <Link href="/cart" className="inline-flex items-center text-sm font-medium text-emerald-600 hover:text-emerald-700">
          <ArrowLeft className="w-4 h-4 mr-1" /> Return to Cart
        </Link>
        <div className="flex items-center text-xs text-gray-500 font-medium">
          <ShieldCheck className="w-4 h-4 text-emerald-600 mr-1" /> 256-Bit SSL Encrypted Checkout
        </div>
      </div>

      <h1 className="text-2xl sm:text-3xl font-extrabold text-gray-900 mb-8">Secure Checkout</h1>

      {error && (
        <div className="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl flex items-center gap-2">
          <AlertCircle className="w-5 h-5 flex-shrink-0" />
          <span>{error}</span>
        </div>
      )}

      <form onSubmit={handleSubmitOrder} className="grid grid-cols-1 lg:grid-cols-12 gap-8">
        {/* Left Column: Shipping & Payment */}
        <div className="lg:col-span-7 space-y-6">
          {/* Contact Details */}
          <div className="bg-white border rounded-xl p-6 shadow-sm">
            <h2 className="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
              <span className="w-6 h-6 rounded-full bg-emerald-600 text-white text-xs flex items-center justify-center font-bold">1</span>
              Contact Information
            </h2>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Email Address (for receipt)</label>
              <input
                type="email"
                required
                placeholder="you@example.com"
                value={customerEmail}
                onChange={(e) => setCustomerEmail(e.target.value)}
                className="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none text-sm"
              />
            </div>
          </div>

          {/* Delivery Address */}
          <div className="bg-white border rounded-xl p-6 shadow-sm">
            <h2 className="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
              <span className="w-6 h-6 rounded-full bg-emerald-600 text-white text-xs flex items-center justify-center font-bold">2</span>
              Delivery Address
            </h2>
            {addresses.length > 0 ? (
              <div className="space-y-3">
                {addresses.map((addr) => (
                  <label
                    key={addr.id}
                    className={`block p-4 border rounded-xl cursor-pointer transition ${
                      selectedAddressId === addr.id ? 'border-emerald-600 bg-emerald-50/40 ring-1 ring-emerald-600' : 'hover:border-gray-300'
                    }`}
                  >
                    <div className="flex items-start">
                      <input
                        type="radio"
                        name="address_id"
                        checked={selectedAddressId === addr.id}
                        onChange={() => setSelectedAddressId(addr.id)}
                        className="mt-1 text-emerald-600 focus:ring-emerald-500"
                      />
                      <div className="ml-3">
                        <span className="font-semibold text-gray-900 text-sm">{addr.label || 'Home'}</span>
                        {addr.is_default && (
                          <span className="ml-2 text-xs bg-emerald-100 text-emerald-800 font-semibold px-2 py-0.5 rounded-full">
                            Default
                          </span>
                        )}
                        <p className="text-xs text-gray-600 mt-1">{addr.address_line1}, {addr.city}, {addr.postal_code}</p>
                        <p className="text-xs text-gray-500">Phone: {addr.phone}</p>
                      </div>
                    </div>
                  </label>
                ))}
              </div>
            ) : (
              <p className="text-sm text-gray-500 italic">
                Using standard delivery address. You can update your address book in account settings.
              </p>
            )}

            <div className="mt-4">
              <label className="block text-sm font-medium text-gray-700 mb-1">Delivery Instructions / Notes</label>
              <textarea
                rows={2}
                placeholder="e.g. Leave by front door, gate code 1234"
                value={note}
                onChange={(e) => setNote(e.target.value)}
                className="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none text-sm"
              />
            </div>
          </div>

          {/* Payment Method */}
          <div className="bg-white border rounded-xl p-6 shadow-sm">
            <h2 className="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
              <span className="w-6 h-6 rounded-full bg-emerald-600 text-white text-xs flex items-center justify-center font-bold">3</span>
              Payment Method
            </h2>
            <div className="space-y-3">
              <label
                className={`block p-4 border rounded-xl cursor-pointer transition ${
                  paymentMethod === 'cod' ? 'border-emerald-600 bg-emerald-50/40 ring-1 ring-emerald-600' : 'hover:border-gray-300'
                }`}
              >
                <div className="flex items-center">
                  <input
                    type="radio"
                    name="payment_method"
                    checked={paymentMethod === 'cod'}
                    onChange={() => setPaymentMethod('cod')}
                    className="text-emerald-600 focus:ring-emerald-500"
                  />
                  <div className="ml-3">
                    <span className="font-semibold text-gray-900 text-sm">Cash on Delivery (COD)</span>
                    <p className="text-xs text-gray-500">Pay cash upon doorstep delivery</p>
                  </div>
                </div>
              </label>

              <label
                className={`block p-4 border rounded-xl cursor-pointer transition ${
                  paymentMethod === 'bkash' ? 'border-emerald-600 bg-emerald-50/40 ring-1 ring-emerald-600' : 'hover:border-gray-300'
                }`}
              >
                <div className="flex items-center">
                  <input
                    type="radio"
                    name="payment_method"
                    checked={paymentMethod === 'bkash'}
                    onChange={() => setPaymentMethod('bkash')}
                    className="text-emerald-600 focus:ring-emerald-500"
                  />
                  <div className="ml-3">
                    <span className="font-semibold text-gray-900 text-sm">bKash Online Payment</span>
                    <p className="text-xs text-gray-500">Instant digital wallet checkout</p>
                  </div>
                </div>
              </label>

              <label
                className={`block p-4 border rounded-xl cursor-pointer transition ${
                  paymentMethod === 'stripe' ? 'border-emerald-600 bg-emerald-50/40 ring-1 ring-emerald-600' : 'hover:border-gray-300'
                }`}
              >
                <div className="flex items-center">
                  <input
                    type="radio"
                    name="payment_method"
                    checked={paymentMethod === 'stripe'}
                    onChange={() => setPaymentMethod('stripe')}
                    className="text-emerald-600 focus:ring-emerald-500"
                  />
                  <div className="ml-3">
                    <span className="font-semibold text-gray-900 text-sm">Credit / Debit Card (Stripe)</span>
                    <p className="text-xs text-gray-500">Visa, Mastercard, American Express</p>
                  </div>
                </div>
              </label>
            </div>
          </div>
        </div>

        {/* Right Column: Order Summary */}
        <div className="lg:col-span-5">
          <div className="bg-white border rounded-xl p-6 shadow-sm sticky top-24">
            <h2 className="text-lg font-bold text-gray-900 mb-4">Order Summary</h2>

            {/* Cart items mini-list */}
            <div className="divide-y max-h-60 overflow-y-auto mb-4 pr-1">
              {cart?.items.map((item) => (
                <div key={item.product_id} className="py-2.5 flex items-center justify-between text-sm">
                  <div className="flex-1 pr-2">
                    <p className="font-medium text-gray-800 line-clamp-1">{item.name}</p>
                    <p className="text-xs text-gray-500">Qty: {item.quantity} × {formatPrice(item.price)}</p>
                  </div>
                  <span className="font-semibold text-gray-900">{formatPrice(item.line_total)}</span>
                </div>
              ))}
            </div>

            {/* Promo Code Input */}
            <div className="pt-4 border-t mb-4">
              <label className="block text-xs font-semibold text-gray-700 mb-1">Coupon Code</label>
              <div className="flex gap-2">
                <input
                  type="text"
                  placeholder="e.g. GROCO10"
                  value={couponCode}
                  onChange={(e) => setCouponCode(e.target.value.toUpperCase())}
                  disabled={couponApplied}
                  className="flex-1 px-3 py-1.5 border rounded-lg text-sm uppercase focus:ring-2 focus:ring-emerald-500 outline-none"
                />
                <button
                  type="button"
                  onClick={handleApplyCoupon}
                  disabled={couponApplied || !couponCode.trim()}
                  className="px-3 py-1.5 bg-gray-900 text-white rounded-lg text-xs font-semibold hover:bg-gray-800 disabled:opacity-50 transition"
                >
                  {couponApplied ? 'Applied' : 'Apply'}
                </button>
              </div>
              {couponError && <p className="text-xs text-red-600 mt-1">{couponError}</p>}
              {couponApplied && <p className="text-xs text-emerald-600 mt-1">✓ Coupon discount applied!</p>}
            </div>

            {/* Price Calculations */}
            <div className="space-y-2 border-t pt-4 text-sm text-gray-600">
              <div className="flex justify-between">
                <span>Subtotal</span>
                <span className="font-semibold text-gray-900">{formatPrice(subtotal)}</span>
              </div>
              <div className="flex justify-between">
                <span>Delivery Charge</span>
                <span className="font-semibold text-gray-900">{formatPrice(deliveryCharge)}</span>
              </div>
              {discountAmount > 0 && (
                <div className="flex justify-between text-emerald-600 font-semibold">
                  <span>Discount</span>
                  <span>-{formatPrice(discountAmount)}</span>
                </div>
              )}
              <div className="flex justify-between text-xs text-gray-400">
                <span>VAT / Tax (Strictly Zero-VAT)</span>
                <span>$0.00</span>
              </div>
              <div className="border-t pt-3 flex justify-between text-base font-extrabold text-gray-900">
                <span>Total Amount</span>
                <span className="text-emerald-700">{formatPrice(finalTotal)}</span>
              </div>
            </div>

            <button
              type="submit"
              disabled={submitting || !cart || cart.items.length === 0}
              className="mt-6 w-full py-3.5 bg-emerald-600 text-white rounded-xl font-bold hover:bg-emerald-700 disabled:opacity-50 transition shadow-sm flex items-center justify-center gap-2"
            >
              {submitting ? (
                <>
                  <div className="animate-spin rounded-full h-4 w-4 border-2 border-white border-t-transparent" />
                  Processing Order...
                </>
              ) : (
                `Place Order • ${formatPrice(finalTotal)}`
              )}
            </button>
          </div>
        </div>
      </form>
    </div>
  );
}
