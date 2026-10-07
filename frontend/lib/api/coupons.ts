import { fetchApi } from './client';
import { CouponValidation, ApiResponse } from '@/types';

export async function validateCoupon(code: string, subtotal: number): Promise<ApiResponse<CouponValidation>> {
  return fetchApi<CouponValidation>('/coupons/validate', {
    method: 'POST',
    body: JSON.stringify({ code, subtotal }),
    cache: 'no-store'
  });
}
