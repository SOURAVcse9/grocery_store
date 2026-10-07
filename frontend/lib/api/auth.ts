import { fetchApi } from './client';
import { ApiResponse } from '@/types';

export interface UserProfile {
  id: number;
  name: string;
  email: string;
  phone?: string;
  avatar?: string;
  status?: string;
  created_at?: string;
}

export interface AuthResponse {
  message: string;
  user: UserProfile;
}

export async function login(email: string, password: string): Promise<ApiResponse<AuthResponse>> {
  return fetchApi<AuthResponse>('/auth/login', {
    method: 'POST',
    body: JSON.stringify({ email, password }),
    credentials: 'include'
  });
}

export async function register(name: string, email: string, password: string, phone?: string): Promise<ApiResponse<AuthResponse>> {
  return fetchApi<AuthResponse>('/auth/register', {
    method: 'POST',
    body: JSON.stringify({ name, email, password, phone }),
    credentials: 'include'
  });
}

export async function logout(): Promise<ApiResponse<{ message: string }>> {
  return fetchApi<{ message: string }>('/auth/logout', {
    method: 'POST',
    credentials: 'include'
  });
}

export async function getMe(): Promise<ApiResponse<UserProfile>> {
  return fetchApi<UserProfile>('/auth/me', {
    cache: 'no-store',
    credentials: 'include'
  });
}
