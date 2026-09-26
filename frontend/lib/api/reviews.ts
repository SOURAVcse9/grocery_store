import { fetchApi } from './client';
import { Review, ApiResponse } from '@/types';

export async function getProductReviews(productId: number): Promise<ApiResponse<Review[]>> {
  return fetchApi<Review[]>(`/reviews?product_id=${productId}`, {
    next: { revalidate: 60 }
  });
}

export async function submitReview(productId: number, rating: number, comment: string): Promise<ApiResponse<any>> {
  return fetchApi('/reviews', {
    method: 'POST',
    body: JSON.stringify({ product_id: productId, rating, comment }),
    credentials: 'include'
  });
}
