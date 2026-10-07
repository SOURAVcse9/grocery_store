import { fetchApi } from './client';
import { Category, ApiResponse } from '@/types';

export async function getCategories(): Promise<ApiResponse<Category[]>> {
  return fetchApi<Category[]>('/categories', {
    next: { revalidate: 300 } // ISR: 5 minutes
  });
}
