import { fetchApi } from './client';
import { Product, ApiResponse } from '@/types';

export interface ProductFilters {
  category_id?: number;
  brand_id?: number;
  search?: string;
  in_stock?: number;
  page?: number;
  per_page?: number;
}

export async function getProducts(filters: ProductFilters = {}): Promise<ApiResponse<Product[]>> {
  const params = new URLSearchParams();
  if (filters.category_id) params.set('category_id', String(filters.category_id));
  if (filters.brand_id) params.set('brand_id', String(filters.brand_id));
  if (filters.search) params.set('search', filters.search);
  if (filters.in_stock) params.set('in_stock', String(filters.in_stock));
  if (filters.page) params.set('page', String(filters.page));
  if (filters.per_page) params.set('per_page', String(filters.per_page));

  const query = params.toString() ? `?${params.toString()}` : '';
  return fetchApi<Product[]>(`/products${query}`, {
    next: { revalidate: 60 } // ISR caching: 60 seconds
  });
}

export async function getProductById(id: number): Promise<ApiResponse<Product>> {
  return fetchApi<Product>(`/products/${id}`, {
    next: { revalidate: 60 }
  });
}

export async function autocompleteSearch(query: string): Promise<ApiResponse<Product[]>> {
  if (!query || query.trim().length < 2) {
    return {
      status: 'success',
      code: 200,
      request_id: 'local',
      timestamp: new Date().toISOString(),
      data: []
    };
  }
  return fetchApi<Product[]>(`/search/autocomplete?q=${encodeURIComponent(query.trim())}`, {
    cache: 'no-store'
  });
}
