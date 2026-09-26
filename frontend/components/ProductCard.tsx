'use client';

import React from 'react';
import Link from 'next/link';
import { ShoppingCart, Star } from 'lucide-react';
import { Product } from '@/types';
import { formatPrice, calculateDiscountPercent } from '@/lib/utils';
import { updateCartItem } from '@/lib/api/cart';

interface ProductCardProps {
  product: Product;
  priority?: boolean;
}

export function ProductCard({ product, priority = false }: ProductCardProps) {
  const discountPercent = calculateDiscountPercent(product.price, product.discount_price);
  const effectivePrice = product.discount_price ? Number(product.discount_price) : Number(product.price);
  const isOutOfStock = product.stock <= 0;

  const handleAddToCart = async (e: React.MouseEvent) => {
    e.preventDefault();
    try {
      await updateCartItem(product.id, 1);
      alert(`Added "${product.name}" to cart!`);
    } catch (err: any) {
      alert(err.message || 'Failed to add item to cart');
    }
  };

  return (
    <div className="product-card group relative bg-white border border-neutral-200 rounded-xl overflow-hidden hover:border-brand-500 hover:shadow-lg transition-all duration-200 flex flex-col justify-between">
      {/* Discount Badge */}
      {discountPercent > 0 && (
        <span className="absolute top-2.5 left-2.5 bg-red-500 text-white text-[11px] font-bold px-2 py-0.5 rounded-full z-10">
          -{discountPercent}%
        </span>
      )}

      {/* Stock Status Badge */}
      {isOutOfStock && (
        <span className="absolute top-2.5 right-2.5 bg-neutral-800 text-white text-[10px] font-bold px-2 py-0.5 rounded-full z-10">
          Out of Stock
        </span>
      )}

      {/* Product Image Link */}
      <Link href={`/products/${product.id}`} className="block relative aspect-square w-full overflow-hidden bg-neutral-100">
        <img
          src={product.image_url}
          alt={product.name}
          loading={priority ? 'eager' : 'lazy'}
          className="w-full h-full object-cover object-center group-hover:scale-105 transition-transform duration-300"
        />
      </Link>

      {/* Product Content */}
      <div className="p-4 flex-1 flex flex-col justify-between">
        <div>
          {product.category_name && (
            <p className="text-[11px] text-neutral-400 font-semibold uppercase tracking-wider mb-1">
              {product.category_name}
            </p>
          )}

          <Link href={`/products/${product.id}`} className="block">
            <h3 className="text-sm font-bold text-neutral-900 group-hover:text-brand-600 line-clamp-2 transition-colors">
              {product.name}
            </h3>
          </Link>

          {/* Rating */}
          <div className="flex items-center gap-1 mt-1.5 text-xs text-neutral-500">
            <Star className="w-3.5 h-3.5 text-amber-400 fill-amber-400" />
            <span className="font-semibold text-neutral-700">{Number(product.avg_rating || 5).toFixed(1)}</span>
            <span>({product.review_count || 0})</span>
          </div>
        </div>

        {/* Pricing & Add to Cart */}
        <div className="mt-4 pt-3 border-t border-neutral-100 flex items-center justify-between">
          <div>
            <div className="flex items-baseline gap-1.5">
              <span className="text-base font-extrabold text-neutral-900">
                {formatPrice(effectivePrice)}
              </span>
              {discountPercent > 0 && (
                <span className="text-xs text-neutral-400 line-through">
                  {formatPrice(product.price)}
                </span>
              )}
            </div>
            <p className="text-[11px] text-neutral-400">Zero-VAT included</p>
          </div>

          <button
            onClick={handleAddToCart}
            disabled={isOutOfStock}
            className={`p-2.5 rounded-full transition-colors ${
              isOutOfStock
                ? 'bg-neutral-100 text-neutral-400 cursor-not-allowed'
                : 'bg-brand-50 text-brand-600 hover:bg-brand-500 hover:text-white'
            }`}
            title={isOutOfStock ? 'Out of stock' : 'Add to cart'}
          >
            <ShoppingCart className="w-4 h-4" />
          </button>
        </div>
      </div>
    </div>
  );
}
