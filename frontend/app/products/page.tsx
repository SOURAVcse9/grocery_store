import React from 'react';
import { getProducts } from '@/lib/api/products';
import { getCategories } from '@/lib/api/categories';
import { ProductCard } from '@/components/ProductCard';

export const revalidate = 60;

interface ProductsPageProps {
  searchParams: {
    category_id?: string;
    brand_id?: string;
    search?: string;
    page?: string;
  };
}

export default async function ProductsPage({ searchParams }: ProductsPageProps) {
  const categoryId = searchParams.category_id ? Number(searchParams.category_id) : undefined;
  const brandId = searchParams.brand_id ? Number(searchParams.brand_id) : undefined;
  const search = searchParams.search || undefined;
  const page = searchParams.page ? Number(searchParams.page) : 1;

  let products: any[] = [];
  let total = 0;
  let categories: any[] = [];

  try {
    const [pRes, cRes] = await Promise.all([
      getProducts({ category_id: categoryId, brand_id: brandId, search, page, per_page: 24 }),
      getCategories()
    ]);
    products = pRes.data || [];
    total = pRes.meta?.total || products.length;
    categories = cRes.data || [];
  } catch (err) {
    products = [];
  }

  return (
    <div className="space-y-8">
      <div>
        <h1 className="text-3xl font-extrabold text-neutral-900 tracking-tight">Grocery Catalog</h1>
        <p className="text-sm text-neutral-500 mt-1">Showing {total} products</p>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-4 gap-8">
        {/* Filters Sidebar */}
        <aside className="space-y-6">
          <div className="bg-white border border-neutral-200 rounded-2xl p-5 space-y-4">
            <h3 className="font-bold text-sm text-neutral-900 uppercase tracking-wider">Categories</h3>
            <ul className="space-y-2 text-sm">
              <li>
                <a
                  href="/products"
                  className={`block py-1 px-2 rounded-lg ${!categoryId ? 'bg-brand-50 text-brand-700 font-bold' : 'text-neutral-600 hover:text-brand-600'}`}
                >
                  All Categories
                </a>
              </li>
              {categories.map((cat) => (
                <li key={cat.id}>
                  <a
                    href={`/products?category_id=${cat.id}`}
                    className={`block py-1 px-2 rounded-lg ${categoryId === cat.id ? 'bg-brand-50 text-brand-700 font-bold' : 'text-neutral-600 hover:text-brand-600'}`}
                  >
                    {cat.name}
                  </a>
                </li>
              ))}
            </ul>
          </div>
        </aside>

        {/* Product Grid */}
        <div className="md:col-span-3">
          {products.length === 0 ? (
            <div className="bg-white border border-neutral-200 rounded-2xl p-12 text-center text-neutral-500">
              No products found matching your search.
            </div>
          ) : (
            <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
              {products.map((prod) => (
                <ProductCard key={prod.id} product={prod} />
              ))}
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
