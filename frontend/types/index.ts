export interface ApiResponse<T> {
  status: 'success' | 'error';
  code: number;
  request_id: string;
  timestamp: string;
  data: T;
  meta?: PaginationMeta;
  error?: {
    code: string;
    message: string;
    details?: any;
  };
}

export interface PaginationMeta {
  page: number;
  per_page: number;
  total: number;
  total_pages: number;
}

export interface Product {
  id: number;
  name: string;
  slug: string;
  sku: string;
  price: number | string;
  discount_price: number | string | null;
  stock: number;
  thumbnail: string | null;
  image_url: string;
  responsive_images?: Record<number, string>;
  avg_rating?: number | string;
  review_count?: number;
  category_name?: string;
  brand_name?: string;
  description?: string;
  short_description?: string;
  unit?: string;
  weight?: number | string;
  is_featured?: number | boolean;
  is_flash_sale?: number | boolean;
}

export interface Category {
  id: number;
  parent_id: number;
  name: string;
  slug: string;
  image: string | null;
  image_url: string;
  icon?: string;
  product_count?: number;
  children?: Category[];
}

export interface Brand {
  id: number;
  name: string;
  slug: string;
  logo: string | null;
  logo_url: string;
  description?: string;
}

export interface CartItem {
  product_id: number;
  name: string;
  sku: string;
  unit_price: number;
  quantity: number;
  stock: number;
  line_total: number;
  in_stock: boolean;
  image_url: string;
}

export interface CartSummary {
  items: CartItem[];
  total_items: number;
  subtotal: number;
  discount: number;
  shipping: number;
  vat_amount: number;
  total: number;
}

export interface OrderItem {
  id?: number;
  order_id?: number;
  product_id: number;
  product_name: string;
  product_sku?: string;
  price: number;
  quantity: number;
  line_total: number;
  thumbnail?: string;
}

export interface Order {
  id: number;
  order_number: string;
  user_id: number | null;
  subtotal: number;
  discount_amount: number;
  delivery_charge: number;
  total_amount: number;
  payment_method: string;
  payment_status: 'unpaid' | 'paid' | 'refunded';
  status: 'pending' | 'processing' | 'shipped' | 'delivered' | 'cancelled';
  note?: string;
  created_at: string;
  items?: OrderItem[];
}

export interface CustomerAddress {
  id: number;
  user_id: number;
  label: string;
  recipient_name: string;
  phone: string;
  address_line1: string;
  address_line2?: string;
  city: string;
  state?: string;
  postal_code: string;
  country: string;
  is_default: boolean | number;
}

export interface CustomerProfile {
  id: number;
  name: string;
  email: string;
  phone?: string;
  avatar?: string;
  email_verified: boolean | number;
  status: string;
  created_at: string;
}

export interface Review {
  id: number;
  product_id: number;
  user_id: number;
  customer_name?: string;
  customer_avatar?: string;
  rating: number;
  review_title: string;
  review_comment: string;
  verified_purchase?: boolean | number;
  created_at: string;
}

export interface CouponValidation {
  valid: boolean;
  coupon_id?: number;
  code?: string;
  discount_type?: string;
  discount_amount?: number;
  final_subtotal?: number;
  message: string;
}
