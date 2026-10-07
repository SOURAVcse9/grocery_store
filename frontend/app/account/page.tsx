'use client';

import React, { useState, useEffect } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { getMe, logout, UserProfile } from '@/lib/api/auth';
import { getAddresses, Address } from '@/lib/api/addresses';
import { getUserOrders } from '@/lib/api/orders';
import { Order } from '@/types';
import { formatPrice } from '@/lib/utils';
import { User, Package, MapPin, LogOut, ArrowRight, ShieldCheck, Clock } from 'lucide-react';

export default function AccountDashboardPage() {
  const router = useRouter();
  const [user, setUser] = useState<UserProfile | null>(null);
  const [orders, setOrders] = useState<Order[]>([]);
  const [addresses, setAddresses] = useState<Address[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    async function loadData() {
      try {
        const [meRes, ordersRes, addrRes] = await Promise.all([
          getMe(),
          getUserOrders(1, 5).catch(() => ({ data: [] })),
          getAddresses().catch(() => ({ data: [] }))
        ]);

        if (meRes.data) {
          setUser(meRes.data);
        } else {
          router.push('/login');
          return;
        }

        if (ordersRes.data) setOrders(ordersRes.data);
        if (addrRes.data) setAddresses(addrRes.data);
      } catch (err) {
        router.push('/login');
      } finally {
        setLoading(false);
      }
    }
    loadData();
  }, [router]);

  const handleLogout = async () => {
    try {
      await logout();
      router.push('/login');
      router.refresh();
    } catch (err) {
      router.push('/login');
    }
  };

  if (loading) {
    return (
      <div className="max-w-7xl mx-auto px-4 py-16 text-center">
        <div className="inline-block animate-spin rounded-full h-10 w-10 border-4 border-emerald-600 border-t-transparent mb-4" />
        <p className="text-gray-600 font-medium">Loading your profile...</p>
      </div>
    );
  }

  return (
    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
      {/* Header Profile Section */}
      <div className="bg-white border rounded-2xl p-6 sm:p-8 shadow-sm mb-8 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div className="flex items-center gap-4">
          <div className="w-16 h-16 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center font-bold text-2xl">
            {user?.name?.charAt(0) || 'U'}
          </div>
          <div>
            <h1 className="text-2xl font-black text-gray-900">{user?.name}</h1>
            <p className="text-sm text-gray-500">{user?.email}</p>
            {user?.phone && <p className="text-xs text-gray-400 mt-0.5">Phone: {user?.phone}</p>}
          </div>
        </div>
        <button
          onClick={handleLogout}
          className="inline-flex items-center gap-2 px-4 py-2 border border-red-200 text-red-600 rounded-xl hover:bg-red-50 text-sm font-semibold transition"
        >
          <LogOut className="w-4 h-4" /> Sign Out
        </button>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
        {/* Left 2 Cols: Recent Orders */}
        <div className="lg:col-span-2 space-y-6">
          <div className="bg-white border rounded-2xl p-6 shadow-sm">
            <div className="flex items-center justify-between mb-6">
              <div className="flex items-center gap-2">
                <Package className="w-5 h-5 text-emerald-600" />
                <h2 className="text-lg font-bold text-gray-900">Recent Orders</h2>
              </div>
              <Link href="/account/orders" className="text-xs font-semibold text-emerald-600 hover:text-emerald-700 flex items-center gap-1">
                View All <ArrowRight className="w-3 h-3" />
              </Link>
            </div>

            {orders.length === 0 ? (
              <div className="text-center py-8 text-gray-500">
                <Package className="w-10 h-10 mx-auto text-gray-300 mb-2" />
                <p className="text-sm">No orders placed yet.</p>
                <Link href="/products" className="inline-block mt-3 px-4 py-2 bg-emerald-600 text-white rounded-lg text-xs font-semibold">
                  Start Shopping
                </Link>
              </div>
            ) : (
              <div className="divide-y">
                {orders.map((order) => (
                  <div key={order.id} className="py-4 flex items-center justify-between">
                    <div>
                      <span className="font-bold text-gray-900 text-sm">#{order.order_number}</span>
                      <div className="flex items-center gap-2 text-xs text-gray-500 mt-1">
                        <Clock className="w-3 h-3" />
                        <span>{new Date(order.created_at).toLocaleDateString()}</span>
                        <span>•</span>
                        <span className="capitalize font-medium text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-md">
                          {order.status}
                        </span>
                      </div>
                    </div>
                    <div className="text-right">
                      <span className="font-bold text-gray-900 text-sm">{formatPrice(order.total_amount)}</span>
                      <Link
                        href={`/account/orders/${order.id}`}
                        className="block text-xs text-emerald-600 hover:underline mt-1"
                      >
                        Details →
                      </Link>
                    </div>
                  </div>
                ))}
              </div>
            )}
          </div>
        </div>

        {/* Right 1 Col: Saved Addresses & Security */}
        <div className="space-y-6">
          <div className="bg-white border rounded-2xl p-6 shadow-sm">
            <div className="flex items-center gap-2 mb-4">
              <MapPin className="w-5 h-5 text-emerald-600" />
              <h2 className="text-lg font-bold text-gray-900">Saved Addresses</h2>
            </div>
            {addresses.length === 0 ? (
              <p className="text-xs text-gray-500">No addresses saved.</p>
            ) : (
              <div className="space-y-3">
                {addresses.map((addr) => (
                  <div key={addr.id} className="p-3 border rounded-xl bg-gray-50/50">
                    <div className="flex justify-between items-center mb-1">
                      <span className="font-semibold text-xs text-gray-900">{addr.label}</span>
                      {addr.is_default && (
                        <span className="text-[10px] bg-emerald-100 text-emerald-800 font-bold px-1.5 py-0.5 rounded">
                          Default
                        </span>
                      )}
                    </div>
                    <p className="text-xs text-gray-600">{addr.address_line1}, {addr.city}</p>
                  </div>
                ))}
              </div>
            )}
          </div>

          <div className="bg-emerald-50/60 border border-emerald-100 rounded-2xl p-6">
            <div className="flex items-center gap-2 text-emerald-800 font-bold text-sm mb-2">
              <ShieldCheck className="w-5 h-5 text-emerald-600" /> Account Protected
            </div>
            <p className="text-xs text-emerald-700 leading-relaxed">
              Your customer account is guarded by GroCo Dual-Layer Auth & Multi-Device Session Invalidation protocols.
            </p>
          </div>
        </div>
      </div>
    </div>
  );
}
