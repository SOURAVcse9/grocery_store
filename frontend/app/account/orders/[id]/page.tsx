'use client';

import React, { useState, useEffect } from 'react';
import Link from 'next/link';
import { useParams, useRouter } from 'next/navigation';
import { getOrderById } from '@/lib/api/orders';
import { Order } from '@/types';
import { formatPrice } from '@/lib/utils';
import { ArrowLeft, Package, Clock, ShieldCheck, Truck, CheckCircle2 } from 'lucide-react';

export default function OrderDetailPage() {
  const params = useParams();
  const router = useRouter();
  const orderId = Number(params?.id);

  const [order, setOrder] = useState<Order | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!orderId) return;

    async function loadOrder() {
      try {
        const res = await getOrderById(orderId);
        if (res.data) {
          setOrder(res.data);
        } else {
          setError('Order not found or access denied');
        }
      } catch (err: any) {
        setError(err.message || 'Failed to fetch order details');
      } finally {
        setLoading(false);
      }
    }
    loadOrder();
  }, [orderId]);

  if (loading) {
    return (
      <div className="max-w-4xl mx-auto px-4 py-16 text-center">
        <div className="inline-block animate-spin rounded-full h-8 w-8 border-4 border-emerald-600 border-t-transparent mb-4" />
        <p className="text-sm text-gray-500">Loading order #{orderId}...</p>
      </div>
    );
  }

  if (error || !order) {
    return (
      <div className="max-w-3xl mx-auto px-4 py-16 text-center">
        <h2 className="text-xl font-bold text-gray-900 mb-2">Unable to load order</h2>
        <p className="text-sm text-gray-600 mb-6">{error || 'Order record not found.'}</p>
        <Link href="/account/orders" className="px-6 py-2.5 bg-emerald-600 text-white rounded-xl text-sm font-semibold">
          Back to Orders
        </Link>
      </div>
    );
  }

  return (
    <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
      <div className="mb-6 flex items-center justify-between">
        <Link href="/account/orders" className="inline-flex items-center text-sm font-medium text-emerald-600 hover:text-emerald-700">
          <ArrowLeft className="w-4 h-4 mr-1" /> All Orders
        </Link>
      </div>

      {/* Header Info */}
      <div className="bg-white border rounded-2xl p-6 sm:p-8 shadow-sm mb-6">
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-6 border-b">
          <div>
            <span className="text-xs font-bold uppercase tracking-wider text-gray-400">Order Reference</span>
            <h1 className="text-2xl font-black text-gray-900 mt-0.5">#{order.order_number}</h1>
            <p className="text-xs text-gray-500 mt-1">Placed on {new Date(order.created_at).toLocaleString()}</p>
          </div>
          <div className="flex items-center gap-2">
            <span className="capitalize text-xs font-bold px-3 py-1 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-100">
              Status: {order.status}
            </span>
            <span className="capitalize text-xs font-semibold px-3 py-1 rounded-full bg-gray-100 text-gray-700">
              Payment: {order.payment_status}
            </span>
          </div>
        </div>

        {/* Items List */}
        <div className="py-6 border-b">
          <h2 className="font-bold text-gray-900 text-base mb-4">Ordered Items</h2>
          <div className="divide-y">
            {order.items?.map((item) => (
              <div key={item.id} className="py-3 flex items-center justify-between text-sm">
                <div>
                  <p className="font-semibold text-gray-900">{item.product_name}</p>
                  <p className="text-xs text-gray-500">
                    Qty: {item.quantity} × {formatPrice(item.price)}
                    {item.product_sku && ` (SKU: ${item.product_sku})`}
                  </p>
                </div>
                <span className="font-bold text-gray-900">{formatPrice(item.line_total)}</span>
              </div>
            ))}
          </div>
        </div>

        {/* Calculation Summary */}
        <div className="pt-6">
          <div className="max-w-xs ml-auto space-y-2 text-sm text-gray-600">
            <div className="flex justify-between">
              <span>Subtotal</span>
              <span className="font-semibold text-gray-900">{formatPrice(order.subtotal)}</span>
            </div>
            <div className="flex justify-between">
              <span>Delivery Fee</span>
              <span className="font-semibold text-gray-900">{formatPrice(order.delivery_charge)}</span>
            </div>
            {order.discount_amount > 0 && (
              <div className="flex justify-between text-emerald-600 font-semibold">
                <span>Discount</span>
                <span>-{formatPrice(order.discount_amount)}</span>
              </div>
            )}
            <div className="border-t pt-2 flex justify-between text-base font-extrabold text-gray-900">
              <span>Total Paid</span>
              <span className="text-emerald-700">{formatPrice(order.total_amount)}</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
