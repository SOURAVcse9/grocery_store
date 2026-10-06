'use client';

import React from 'react';
import Link from 'next/link';
import { ShoppingCart, Star } from 'lucide-react';
import { Product } from '@/types';
import { formatPrice, calculateDiscountPercent } from '@/lib/utils';
import { updateCartItem } from '@/lib/api/cart';
import { resolveProductImage, getProductImageFallback } from '@/lib/image';

interface ProductCardProps {
  product: Product;
  priority?: boolean;
}

export function ProductCard({ product, priority = false }: ProductCardProps) {
  const discountPercent = calculateDiscountPercent(product.price, product.discount_price);
  const effectivePrice = product.discount_price ? Number(product.discount_price) : Number(product.price);
  const isOutOfStock = product.stock <= 0;
  const imageSrc = resolveProductImage(product.image_url);

  const handleAddToCart = async (e: React.MouseEvent) => {
    e.preventDefault();
    try {
      await updateCartItem(product.id, 1);
      alert(`Added "${product.name}" to cart!`);
    } catch (err: any) {
      alert(err.message || 'Failed to add item to cart');
    }
  };

  const handleImageError = (event: React.SyntheticEvent<HTMLImageElement>) => {
    const target = event.currentTarget;
    if (target.dataset.fallbackApplied === 'true') {
      return;
    }
    target.dataset.fallbackApplied = 'true';
    target.src = getProductImageFallback();
  };

  return (
    <div className="product-card group relative bg-white border border-neutral-200 rounded-xl overflow-hidden hover:border-brand-500 hover:shadow-lg transition-all duration-200 flex flex-col justify-between">
      {discountPercent > 0 && (
        <span className="absolute top-2.5 left-2.5 bg-red-500 text-white text-[11px] font-bold px-2 py-0.5 rounded-full z-10">
          -{discountPercent}%
        </span>
      )}

      {isOutOfStock && (
        <span className="absolute top-2.5 right-2.5 bg-neutral-800 text-white text-[10px] font-bold px-2 py-0.5 rounded-full z-10">
          Out of Stock
        </span>
      )}

      <Link href={`/products/${product.id}`} className="block relative aspect-[4/3] w-full overflow-hidden bg-neutral-100">
        <img
          src={imageSrc}
          alt={product.name}
          width={640}
          height={480}
          loading={priority ? 'eager' : 'lazy'}
          decoding="async"
          className="h-full w-full object-contain p-3 transition-transform duration-300 group-hover:scale-[1.02]"
          onError={handleImageError}
        />
      </Link>

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

          <div className="mt-1.5 flex items-center gap-1 text-xs text-neutral-500">
            <Star className="h-3.5 w-3.5 fill-amber-400 text-amber-400" />
            <span className="font-semibold text-neutral-700">{Number(product.avg_rating || 5).toFixed(1)}</span>
            <span>({product.review_count || 0})</span>
          </div>
        </div>

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
            aria-label={isOutOfStock ? 'Out of stock' : `Add ${product.name} to cart`}
          >
            <ShoppingCart className="w-4 h-4" />
          </button>
        </div>
      </div>
    </div>
  );
}
