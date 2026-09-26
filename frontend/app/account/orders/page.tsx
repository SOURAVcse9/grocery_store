'use client';

import React, { useState, useEffect } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { getUserOrders } from '@/lib/api/orders';
import { Order } from '@/types';
import { formatPrice } from '@/lib/utils';
import { Package, ArrowLeft, Clock, ChevronRight } from 'lucide-react';

export default function OrderHistoryPage() {
  const router = useRouter();
  const [orders, setOrders] = useState<Order[]>([]);
  const [loading, setLoading] = useState(true);
  const [page, setPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);

  useEffect(() => {
    async function loadOrders() {
      setLoading(true);
      try {
        const res = await getUserOrders(page, 10);
        if (res.data) {
          setOrders(res.data);
          if (res.meta) {
            setTotalPages(res.meta.total_pages);
          }
        }
      } catch (err) {
        router.push('/login');
      } finally {
        setLoading(false);
      }
    }
    loadOrders();
  }, [page, router]);

  return (
    <div className="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
      <div className="mb-6 flex items-center justify-between">
        <Link href="/account" className="inline-flex items-center text-sm font-medium text-emerald-600 hover:text-emerald-700">
          <ArrowLeft className="w-4 h-4 mr-1" /> Back to Account
        </Link>
      </div>

      <div className="flex items-center gap-3 mb-8">
        <div className="p-2.5 bg-emerald-100 text-emerald-700 rounded-xl">
          <Package className="w-6 h-6" />
        </div>
        <div>
          <h1 className="text-2xl font-black text-gray-900">Your Order History</h1>
          <p className="text-xs text-gray-500">Track and manage past online orders</p>
        </div>
      </div>

      {loading ? (
        <div className="py-16 text-center">
          <div className="inline-block animate-spin rounded-full h-8 w-8 border-4 border-emerald-600 border-t-transparent mb-4" />
          <p className="text-sm text-gray-500">Loading orders...</p>
        </div>
      ) : orders.length === 0 ? (
        <div className="bg-white border rounded-2xl p-12 text-center">
          <Package className="w-12 h-12 mx-auto text-gray-300 mb-3" />
          <h3 className="text-lg font-bold text-gray-800 mb-1">No Orders Found</h3>
          <p className="text-sm text-gray-500 mb-6">You have not placed any orders yet.</p>
          <Link
            href="/products"
            className="px-6 py-2.5 bg-emerald-600 text-white font-semibold rounded-xl text-sm hover:bg-emerald-700 transition"
          >
            Start Shopping
          </Link>
        </div>
      ) : (
        <div className="space-y-4">
          {orders.map((order) => (
            <Link
              key={order.id}
              href={`/account/orders/${order.id}`}
              className="block bg-white border rounded-2xl p-6 shadow-sm hover:shadow-md hover:border-emerald-200 transition group"
            >
              <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                  <div className="flex items-center gap-3 mb-1">
                    <span className="font-extrabold text-gray-900 text-base">#{order.order_number}</span>
                    <span className="capitalize text-xs font-bold px-2.5 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-100">
                      {order.status}
                    </span>
                    <span className="capitalize text-xs font-semibold px-2.5 py-0.5 rounded-full bg-gray-100 text-gray-600">
                      {order.payment_method.toUpperCase()} • {order.payment_status}
                    </span>
                  </div>
                  <div className="flex items-center gap-2 text-xs text-gray-500">
                    <Clock className="w-3.5 h-3.5" />
                    <span>Placed on {new Date(order.created_at).toLocaleDateString()}</span>
                  </div>
                </div>

                <div className="flex items-center justify-between sm:justify-end gap-6">
                  <div className="text-left sm:text-right">
                    <span className="block text-xs text-gray-500">Total Amount</span>
                    <span className="text-lg font-extrabold text-gray-900">{formatPrice(order.total_amount)}</span>
                  </div>
                  <ChevronRight className="w-5 h-5 text-gray-400 group-hover:text-emerald-600 transition" />
                </div>
              </div>
            </Link>
          ))}

          {totalPages > 1 && (
            <div className="flex justify-center gap-2 pt-6">
              <button
                disabled={page <= 1}
                onClick={() => setPage((p) => Math.max(1, p - 1))}
                className="px-4 py-2 border rounded-xl text-sm font-semibold disabled:opacity-40"
              >
                Previous
              </button>
              <span className="px-4 py-2 text-sm font-medium text-gray-600">
                Page {page} of {totalPages}
              </span>
              <button
                disabled={page >= totalPages}
                onClick={() => setPage((p) => Math.min(totalPages, p + 1))}
                className="px-4 py-2 border rounded-xl text-sm font-semibold disabled:opacity-40"
              >
                Next
              </button>
            </div>
          )}
        </div>
      )}
    </div>
  );
}
