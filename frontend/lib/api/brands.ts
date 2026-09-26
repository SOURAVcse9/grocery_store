import { fetchApi } from './client';
import { Brand, ApiResponse } from '@/types';

export async function getBrands(): Promise<ApiResponse<Brand[]>> {
  return fetchApi<Brand[]>('/brands', {
    next: { revalidate: 300 }
  });
}
