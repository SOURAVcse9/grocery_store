import React from 'react';
import Link from 'next/link';
import { getProducts } from '@/lib/api/products';
import { getCategories } from '@/lib/api/categories';
import { ProductCard } from '@/components/ProductCard';
import { ArrowRight, Truck, ShieldCheck, Clock, RefreshCw } from 'lucide-react';

export const revalidate = 60; // ISR 60 seconds

export default async function HomePage() {
  let products: any[] = [];
  let categories: any[] = [];

  try {
    const [pRes, cRes] = await Promise.all([
      getProducts({ per_page: 12 }),
      getCategories()
    ]);
    products = pRes.data || [];
    categories = cRes.data || [];
  } catch (err) {
    products = [];
    categories = [];
  }

  return (
    <div className="space-y-12">
      {/* Hero Banner */}
      <section className="relative bg-gradient-to-r from-brand-600 to-brand-800 rounded-3xl overflow-hidden text-white shadow-xl">
        <div className="px-8 py-16 md:py-24 max-w-2xl space-y-6">
          <span className="inline-block bg-white/20 backdrop-blur-md px-3.5 py-1 rounded-full text-xs font-bold uppercase tracking-wider text-brand-100">
            🌿 100% Organic & Farm Fresh
          </span>
          <h1 className="text-4xl md:text-5xl font-extrabold tracking-tight leading-tight">
            Daily Groceries Delivered to Your Doorstep.
          </h1>
          <p className="text-brand-100 text-base md:text-lg">
            Shop fresh produce, dairy, bakery, snacks, and household essentials at everyday low prices with zero VAT.
          </p>
          <div className="flex gap-4 pt-2">
            <Link
              href="/products"
              className="bg-white text-brand-700 px-6 py-3 rounded-full font-bold text-sm hover:bg-brand-50 transition-colors shadow-md inline-flex items-center gap-2"
            >
              Shop Catalog <ArrowRight className="w-4 h-4" />
            </Link>
          </div>
        </div>
      </section>

      {/* Feature Badges */}
      <section className="grid grid-cols-2 md:grid-cols-4 gap-4">
        {[
          { icon: Truck, title: 'Fast Delivery', desc: 'Same-day home drop' },
          { icon: ShieldCheck, title: '100% Safe', desc: 'Secure payments' },
          { icon: Clock, title: '24/7 Support', desc: 'Always available' },
          { icon: RefreshCw, title: 'Easy Returns', desc: 'Instant replacement' },
        ].map((feat, idx) => (
          <div key={idx} className="bg-white border border-neutral-200 rounded-2xl p-5 flex items-center gap-3.5 shadow-sm">
            <div className="p-3 bg-brand-50 text-brand-600 rounded-xl">
              <feat.icon className="w-5 h-5" />
            </div>
            <div>
              <h4 className="font-bold text-sm text-neutral-900">{feat.title}</h4>
              <p className="text-xs text-neutral-500">{feat.desc}</p>
            </div>
          </div>
        ))}
      </section>

      {/* Categories Grid */}
      <section className="space-y-6">
        <div className="flex items-center justify-between">
          <div>
            <h2 className="text-2xl font-extrabold text-neutral-900 tracking-tight">Shop by Category</h2>
            <p className="text-sm text-neutral-500">Explore our wide selection of fresh grocery categories</p>
          </div>
          <Link href="/products" className="text-sm font-bold text-brand-600 hover:text-brand-700 inline-flex items-center gap-1">
            View All <ArrowRight className="w-4 h-4" />
          </Link>
        </div>

        <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-6 gap-4">
          {categories.slice(0, 6).map((cat) => (
            <Link
              key={cat.id}
              href={`/categories/${cat.id}`}
              className="group bg-white border border-neutral-200 rounded-2xl p-4 text-center hover:border-brand-500 hover:shadow-md transition-all flex flex-col items-center justify-center gap-3"
            >
              <div className="w-16 h-16 rounded-full bg-neutral-100 overflow-hidden group-hover:scale-110 transition-transform">
                <img src={cat.image_url} alt={cat.name} className="w-full h-full object-cover" />
              </div>
              <span className="text-xs font-bold text-neutral-800 group-hover:text-brand-600 transition-colors">
                {cat.name}
              </span>
            </Link>
          ))}
        </div>
      </section>

      {/* Featured Products */}
      <section className="space-y-6">
        <div className="flex items-center justify-between">
          <div>
            <h2 className="text-2xl font-extrabold text-neutral-900 tracking-tight">Popular Grocery Items</h2>
            <p className="text-sm text-neutral-500">Top-rated items favored by our shoppers</p>
          </div>
          <Link href="/products" className="text-sm font-bold text-brand-600 hover:text-brand-700 inline-flex items-center gap-1">
            Browse All <ArrowRight className="w-4 h-4" />
          </Link>
        </div>

        <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
          {products.map((prod, idx) => (
            <ProductCard key={prod.id} product={prod} priority={idx < 4} />
          ))}
        </div>
      </section>
    </div>
  );
}
