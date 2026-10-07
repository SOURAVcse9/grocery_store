import { fetchApi } from './client';
import { Order, ApiResponse } from '@/types';

export interface CreateOrderPayload {
  items: Array<{ product_id: number; quantity: number }>;
  address_id?: number;
  coupon_id?: number;
  discount_amount?: number;
  delivery_charge?: number;
  payment_method: 'cod' | 'bkash' | 'nagad' | 'stripe';
  note?: string;
  customer_email?: string;
}

export interface CreateOrderResponse {
  order_id: number;
  order_number: string;
  total_amount: number;
}

export async function createOrder(payload: CreateOrderPayload): Promise<ApiResponse<CreateOrderResponse>> {
  return fetchApi<CreateOrderResponse>('/orders', {
    method: 'POST',
    body: JSON.stringify(payload),
    credentials: 'include'
  });
}

export async function getUserOrders(page = 1, perPage = 10): Promise<ApiResponse<Order[]>> {
  return fetchApi<Order[]>(`/orders?page=${page}&per_page=${perPage}`, {
    cache: 'no-store',
    credentials: 'include'
  });
}

export async function getOrderById(orderId: number): Promise<ApiResponse<Order>> {
  return fetchApi<Order>(`/orders/${orderId}`, {
    cache: 'no-store',
    credentials: 'include'
  });
}
