import React from 'react';
import { notFound } from 'next/navigation';
import { getProductById, getProducts } from '@/lib/api/products';
import { getProductReviews } from '@/lib/api/reviews';
import { formatPrice, calculateDiscountPercent } from '@/lib/utils';
import { JsonLd } from '@/components/JsonLd';
import { ProductCard } from '@/components/ProductCard';
import { Star, ShieldCheck, Truck, RefreshCw, CheckCircle2 } from 'lucide-react';

export const revalidate = 60;

interface ProductDetailPageProps {
  params: {
    slug: string;
  };
}

export default async function ProductDetailPage({ params }: ProductDetailPageProps) {
  const productId = parseInt(params.slug, 10);
  if (isNaN(productId) || productId <= 0) {
    notFound();
  }

  let product: any = null;
  let reviews: any[] = [];
  let relatedProducts: any[] = [];

  try {
    const [pRes, rRes] = await Promise.all([
      getProductById(productId),
      getProductReviews(productId)
    ]);
    product = pRes.data;
    reviews = rRes.data || [];

    if (product?.category_id) {
      const relRes = await getProducts({ category_id: product.category_id, per_page: 4 });
      relatedProducts = (relRes.data || []).filter((p: any) => p.id !== product.id);
    }
  } catch (err) {
    notFound();
  }

  if (!product) {
    notFound();
  }

  const discountPercent = calculateDiscountPercent(product.price, product.discount_price);
  const effectivePrice = product.discount_price ? Number(product.discount_price) : Number(product.price);
  const isOutOfStock = product.stock <= 0;

  const productSchema = {
    '@context': 'https://schema.org',
    '@type': 'Product',
    name: product.name,
    image: product.image_url,
    description: product.description || product.short_description || product.name,
    sku: product.sku || `GROCO-${product.id}`,
    brand: {
      '@type': 'Brand',
      name: product.brand_name || 'GroCo',
    },
    offers: {
      '@type': 'Offer',
      url: `http://localhost:8080/grocery-store/public/products.php?id=${product.id}`,
      priceCurrency: 'BDT',
      price: effectivePrice.toFixed(2),
      availability: !isOutOfStock ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
      seller: {
        '@type': 'Organization',
        name: 'GroCo Grocery Store',
      },
    },
    ...(Number(product.avg_rating || 0) > 0 && {
      aggregateRating: {
        '@type': 'AggregateRating',
        ratingValue: Number(product.avg_rating).toFixed(1),
        reviewCount: product.review_count || 1,
      },
    }),
  };

  return (
    <div className="space-y-12">
      <JsonLd data={productSchema} />

      {/* Main Product Info */}
      <div className="grid grid-cols-1 md:grid-cols-2 gap-12 bg-white border border-neutral-200 rounded-3xl p-8 shadow-sm">
        {/* Product Image */}
        <div className="relative aspect-square w-full bg-neutral-100 rounded-2xl overflow-hidden border border-neutral-100 flex items-center justify-center">
          <img
            src={product.image_url}
            alt={product.name}
            className="w-full h-full object-contain p-4"
          />
          {discountPercent > 0 && (
            <span className="absolute top-4 left-4 bg-red-500 text-white text-xs font-extrabold px-3 py-1 rounded-full shadow">
              Save {discountPercent}%
            </span>
          )}
        </div>

        {/* Product Details */}
        <div className="space-y-6">
          <div>
            {product.category_name && (
              <span className="text-xs font-bold text-brand-600 uppercase tracking-wider">
                {product.category_name}
              </span>
            )}
            <h1 className="text-3xl font-extrabold text-neutral-900 mt-1">{product.name}</h1>
            <p className="text-xs text-neutral-400 mt-1">SKU: {product.sku || `GROCO-${product.id}`}</p>
          </div>

          {/* Rating */}
          <div className="flex items-center gap-2">
            <div className="flex text-amber-400">
              {[...Array(5)].map((_, i) => (
                <Star
                  key={i}
                  className={`w-4 h-4 ${i < Math.floor(Number(product.avg_rating || 5)) ? 'fill-amber-400' : 'text-neutral-300'}`}
                />
              ))}
            </div>
            <span className="text-sm font-bold text-neutral-700">
              {Number(product.avg_rating || 5).toFixed(1)}
            </span>
            <span className="text-sm text-neutral-400">({reviews.length} customer reviews)</span>
          </div>

          {/* Pricing */}
          <div className="bg-neutral-50 p-4 rounded-2xl border border-neutral-200/60">
            <div className="flex items-baseline gap-3">
              <span className="text-3xl font-black text-neutral-900">{formatPrice(effectivePrice)}</span>
              {discountPercent > 0 && (
                <span className="text-base text-neutral-400 line-through">{formatPrice(product.price)}</span>
              )}
            </div>
            <p className="text-xs text-brand-700 font-semibold mt-1">✓ Zero-VAT Retail Price • Tax Included</p>
          </div>

          {/* Stock Availability */}
          <div className="flex items-center gap-2">
            <CheckCircle2 className={`w-5 h-5 ${!isOutOfStock ? 'text-green-600' : 'text-red-500'}`} />
            <span className={`text-sm font-bold ${!isOutOfStock ? 'text-green-700' : 'text-red-600'}`}>
              {!isOutOfStock ? `In Stock (${product.stock} units available)` : 'Currently Out of Stock'}
            </span>
          </div>

          {/* Actions */}
          <div className="flex gap-4">
            <button
              disabled={isOutOfStock}
              className={`flex-1 py-3.5 px-6 rounded-full font-bold text-sm transition-all shadow-md ${
                isOutOfStock
                  ? 'bg-neutral-200 text-neutral-400 cursor-not-allowed'
                  : 'bg-brand-600 text-white hover:bg-brand-700 active:scale-95'
              }`}
            >
              Add to Shopping Cart
            </button>
          </div>

          {/* Trust Highlights */}
          <div className="grid grid-cols-3 gap-3 pt-4 border-t border-neutral-100 text-center text-xs text-neutral-600">
            <div className="p-3 bg-neutral-50 rounded-xl">
              <Truck className="w-5 h-5 mx-auto text-brand-600 mb-1" />
              <span>Same-Day Drop</span>
            </div>
            <div className="p-3 bg-neutral-50 rounded-xl">
              <ShieldCheck className="w-5 h-5 mx-auto text-brand-600 mb-1" />
              <span>100% Quality</span>
            </div>
            <div className="p-3 bg-neutral-50 rounded-xl">
              <RefreshCw className="w-5 h-5 mx-auto text-brand-600 mb-1" />
              <span>Easy Return</span>
            </div>
          </div>
        </div>
      </div>

      {/* Customer Reviews Section */}
      <section className="bg-white border border-neutral-200 rounded-3xl p-8 space-y-6">
        <h2 className="text-2xl font-extrabold text-neutral-900 tracking-tight">
          Customer Ratings & Reviews ({reviews.length})
        </h2>

        {reviews.length === 0 ? (
          <p className="text-sm text-neutral-500">No customer reviews yet. Be the first to review this product!</p>
        ) : (
          <div className="divide-y divide-neutral-100 space-y-4">
            {reviews.map((rev) => (
              <div key={rev.id} className="pt-4 space-y-2">
                <div className="flex items-center justify-between">
                  <div className="flex items-center gap-2">
                    <span className="font-bold text-sm text-neutral-800">{rev.customer_name || 'Verified Buyer'}</span>
                    <span className="text-[11px] bg-green-50 text-green-700 px-2 py-0.5 rounded-full font-semibold">
                      Verified Purchase
                    </span>
                  </div>
                  <div className="flex text-amber-400">
                    {[...Array(rev.rating || 5)].map((_, i) => (
                      <Star key={i} className="w-3.5 h-3.5 fill-amber-400" />
                    ))}
                  </div>
                </div>
                <h4 className="text-sm font-bold text-neutral-900">{rev.review_title}</h4>
                <p className="text-sm text-neutral-600">{rev.review_comment}</p>
              </div>
            ))}
          </div>
        )}
      </section>

      {/* Related Products */}
      {relatedProducts.length > 0 && (
        <section className="space-y-6">
          <h2 className="text-2xl font-extrabold text-neutral-900 tracking-tight">Similar Grocery Items</h2>
          <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
            {relatedProducts.map((p) => (
              <ProductCard key={p.id} product={p} />
            ))}
          </div>
        </section>
      )}
    </div>
  );
}
