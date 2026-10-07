'use client';

import React, { useState, useEffect } from 'react';
import Link from 'next/link';
import { ShoppingCart, Search, Menu, X, User, Heart } from 'lucide-react';
import { Category, Product } from '@/types';
import { autocompleteSearch } from '@/lib/api/products';

interface HeaderProps {
  categories?: Category[];
}

export function Header({ categories = [] }: HeaderProps) {
  const [searchQuery, setSearchQuery] = useState('');
  const [suggestions, setSuggestions] = useState<Product[]>([]);
  const [isSearching, setIsSearching] = useState(false);
  const [isMobileMenuOpen, setIsMobileMenuOpen] = useState(false);

  useEffect(() => {
    if (searchQuery.trim().length < 2) {
      setSuggestions([]);
      return;
    }

    const timer = setTimeout(async () => {
      setIsSearching(true);
      try {
        const res = await autocompleteSearch(searchQuery);
        setSuggestions(res.data || []);
      } catch (err) {
        setSuggestions([]);
      } finally {
        setIsSearching(false);
      }
    }, 200);

    return () => clearTimeout(timer);
  }, [searchQuery]);

  return (
    <header className="sticky top-0 z-50 bg-white border-b border-neutral-200 shadow-sm">
      {/* Top Notification Bar */}
      <div className="bg-brand-500 text-white text-xs py-1.5 px-4 text-center font-medium">
        ⚡ Order now for fast doorstep grocery delivery across Bangladesh | Zero-VAT Guarantee
      </div>

      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="flex items-center justify-between h-16 gap-4">
          {/* Logo */}
          <Link href="/" className="flex items-center gap-2 text-brand-600 font-extrabold text-2xl tracking-tight flex-shrink-0">
            <span>🛒</span>
            <span>Gro<span className="text-neutral-900">Co</span></span>
          </Link>

          {/* Autocomplete Search Bar */}
          <div className="relative flex-1 max-w-xl hidden md:block">
            <div className="relative">
              <input
                type="text"
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                placeholder="Search fresh fruits, vegetables, dairy, snacks..."
                className="w-full pl-10 pr-4 py-2 text-sm border border-neutral-300 rounded-full focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-brand-500 transition-all"
              />
              <Search className="absolute left-3.5 top-2.5 h-4 w-4 text-neutral-400" />
            </div>

            {/* Suggestions Dropdown */}
            {suggestions.length > 0 && (
              <div className="absolute top-full left-0 right-0 mt-1.5 bg-white border border-neutral-200 rounded-2xl shadow-xl z-50 overflow-hidden">
                <div className="p-2 text-xs font-semibold text-neutral-400 uppercase tracking-wider">
                  Suggestions
                </div>
                <div className="divide-y divide-neutral-100 max-h-80 overflow-y-auto">
                  {suggestions.map((item) => (
                    <Link
                      key={item.id}
                      href={`/products/${item.id}`}
                      onClick={() => {
                        setSearchQuery('');
                        setSuggestions([]);
                      }}
                      className="flex items-center gap-3 p-3 hover:bg-neutral-50 transition-colors"
                    >
                      <img
                        src={item.image_url}
                        alt={item.name}
                        className="w-10 h-10 object-cover rounded-lg border border-neutral-200"
                      />
                      <div className="flex-1 min-w-0">
                        <p className="text-sm font-medium text-neutral-900 truncate">{item.name}</p>
                        <p className="text-xs text-brand-600 font-bold">৳{Number(item.discount_price || item.price).toFixed(2)}</p>
                      </div>
                    </Link>
                  ))}
                </div>
              </div>
            )}
          </div>

          {/* Actions */}
          <div className="flex items-center gap-4">
            <Link
              href="/account"
              className="hidden sm:flex items-center gap-1.5 text-sm font-medium text-neutral-700 hover:text-brand-600 transition-colors"
            >
              <User className="h-5 w-5" />
              <span>Account</span>
            </Link>

            <Link
              href="/cart"
              className="relative flex items-center gap-1.5 p-2 text-neutral-700 hover:text-brand-600 transition-colors"
            >
              <ShoppingCart className="h-6 w-6" />
              <span className="sr-only">Shopping Cart</span>
            </Link>

            <button
              onClick={() => setIsMobileMenuOpen(!isMobileMenuOpen)}
              className="md:hidden p-2 text-neutral-700 hover:text-brand-600"
            >
              {isMobileMenuOpen ? <X className="h-6 w-6" /> : <Menu className="h-6 w-6" />}
            </button>
          </div>
        </div>

        {/* Categories Bar */}
        <nav className="hidden md:flex items-center gap-6 py-2.5 overflow-x-auto text-sm font-medium border-t border-neutral-100 scrollbar-none">
          <Link href="/products" className="text-neutral-700 hover:text-brand-600 whitespace-nowrap">
            All Products
          </Link>
          {categories.slice(0, 8).map((cat) => (
            <Link
              key={cat.id}
              href={`/categories/${cat.id}`}
              className="text-neutral-600 hover:text-brand-600 whitespace-nowrap"
            >
              {cat.name}
            </Link>
          ))}
        </nav>
      </div>

      {/* Mobile Menu */}
      {isMobileMenuOpen && (
        <div className="md:hidden bg-white border-b border-neutral-200 px-4 py-3 space-y-3">
          <div className="relative">
            <input
              type="text"
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              placeholder="Search products..."
              className="w-full pl-9 pr-4 py-2 text-sm border border-neutral-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-brand-500"
            />
            <Search className="absolute left-3 top-2.5 h-4 w-4 text-neutral-400" />
          </div>
          <div className="grid grid-cols-2 gap-2 text-sm">
            <Link href="/products" className="p-2 text-neutral-700 hover:bg-neutral-50 rounded">All Products</Link>
            <Link href="/account" className="p-2 text-neutral-700 hover:bg-neutral-50 rounded">My Account</Link>
            <Link href="/cart" className="p-2 text-neutral-700 hover:bg-neutral-50 rounded">View Cart</Link>
          </div>
        </div>
      )}
    </header>
  );
}
