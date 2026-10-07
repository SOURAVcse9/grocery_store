import React from 'react';
import Link from 'next/link';

export function Footer() {
  return (
    <footer className="bg-neutral-900 text-neutral-300 mt-20">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div className="grid grid-cols-1 md:grid-cols-4 gap-8">
          {/* Brand Info */}
          <div>
            <div className="flex items-center gap-2 text-brand-400 font-extrabold text-2xl tracking-tight mb-4">
              <span>🛒</span>
              <span>GroCo Grocery Store</span>
            </div>
            <p className="text-sm text-neutral-400 leading-relaxed">
              Bangladesh’s premier online supermarket delivering 100% farm-fresh produce, daily essentials, and grocery staples straight to your door.
            </p>
          </div>

          {/* Quick Links */}
          <div>
            <h3 className="text-white font-semibold text-sm uppercase tracking-wider mb-4">Quick Links</h3>
            <ul className="space-y-2 text-sm text-neutral-400">
              <li><Link href="/products" className="hover:text-white transition-colors">All Products</Link></li>
              <li><Link href="/cart" className="hover:text-white transition-colors">My Cart</Link></li>
              <li><Link href="/account" className="hover:text-white transition-colors">My Account</Link></li>
              <li><Link href="/account/orders" className="hover:text-white transition-colors">Order History</Link></li>
            </ul>
          </div>

          {/* Customer Service */}
          <div>
            <h3 className="text-white font-semibold text-sm uppercase tracking-wider mb-4">Customer Support</h3>
            <ul className="space-y-2 text-sm text-neutral-400">
              <li>Email: support@groco.site.je</li>
              <li>Phone: +880 1700-000000</li>
              <li>Hours: 8:00 AM – 10:00 PM (Daily)</li>
              <li>Zero-VAT Retail Compliance</li>
            </ul>
          </div>

          {/* Security & Guarantees */}
          <div>
            <h3 className="text-white font-semibold text-sm uppercase tracking-wider mb-4">Trust & Security</h3>
            <p className="text-sm text-neutral-400 mb-3">
              🔒 100% Secure Checkout, Argon2ID Customer Protection & RSA-2048 Cryptographic Licensing Core.
            </p>
            <div className="text-xs text-neutral-500">
              Headless Next.js Storefront & REST API v1
            </div>
          </div>
        </div>

        <div className="border-t border-neutral-800 mt-12 pt-8 text-center text-xs text-neutral-500">
          © {new Date().getFullYear()} GroCo Grocery Store. All rights reserved. Powered by Next.js & PHP 8.2 Modern Architecture.
        </div>
      </div>
    </footer>
  );
}
