import { fetchApi } from './client';
import { CartSummary, ApiResponse } from '@/types';

export async function getCart(): Promise<ApiResponse<CartSummary>> {
  return fetchApi<CartSummary>('/cart', {
    cache: 'no-store',
    credentials: 'include'
  });
}

export async function updateCartItem(productId: number, quantity: number): Promise<ApiResponse<any>> {
  return fetchApi('/cart', {
    method: 'POST',
    body: JSON.stringify({ product_id: productId, quantity }),
    credentials: 'include'
  });
}
