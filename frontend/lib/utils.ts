import { type ClassValue, clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs));
}

export function formatPrice(amount: number | string | null | undefined): string {
  if (amount === null || amount === undefined || isNaN(Number(amount))) {
    return '৳0.00';
  }
  return `৳${Number(amount).toFixed(2)}`;
}

export function calculateDiscountPercent(price: number | string, discountPrice: number | string | null | undefined): number {
  const p = Number(price);
  const dp = Number(discountPrice);
  if (!dp || dp >= p || p <= 0) return 0;
  return Math.round(((p - dp) / p) * 100);
}
