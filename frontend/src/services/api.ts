import axios from 'axios'
import type { AxiosError } from 'axios'
import type { ApiError } from '@/types'

const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL,
  withCredentials: true,
  withXSRFToken: true,
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json',
  },
})

api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      if (window.location.pathname !== '/login') {
        window.location.href = '/login'
      }
    }
    return Promise.reject(error)
  }
)

export function normalizeApiError(error: unknown): ApiError {
  const axiosError = error as AxiosError<{ message?: string; errors?: Record<string, string[]> }>

  if (axiosError.response) {
    return {
      message: axiosError.response.data?.message || 'An error occurred',
      errors: axiosError.response.data?.errors,
      status: axiosError.response.status,
    }
  }

  if (axiosError.request) {
    return {
      message: 'Network error. Please check your connection.',
      status: 0,
    }
  }

  return {
    message: 'An unexpected error occurred',
    status: 0,
  }
}

export const getCsrfCookie = () =>
  axios.get('/sanctum/csrf-cookie', {
    withCredentials: true,
  })

export const login = (email: string, password: string) =>
  api.post<{ user: import('@/types').User }>('/login', { email, password })

export const logout = () => api.post('/logout')

export const getUser = () => api.get<import('@/types').User>('/user')

export interface ProfileResponse {
  user: import('@/types').User
  assets: import('@/types').Asset[]
}

export const getProfile = () => api.get<ProfileResponse>('/profile')

export interface CreateOrderRequest {
  symbol: import('@/types').AssetSymbol
  side: import('@/types').OrderSide
  price: string
  amount: string
}

export interface CreateOrderResponse {
  message: string
  order: import('@/types').Order
}

export const createOrder = (data: CreateOrderRequest) =>
  api.post<CreateOrderResponse>('/orders', data)

export interface OrderbookResponse {
  buy_orders: import('@/types').OrderbookOrder[]
  sell_orders: import('@/types').OrderbookOrder[]
}

export const getOrderbook = (symbol: import('@/types').AssetSymbol) =>
  api.get<OrderbookResponse>(`/orders?symbol=${symbol}`)

export interface MyOrdersResponse {
  orders: import('@/types').Order[]
}

export const getMyOrders = () => api.get<MyOrdersResponse>('/my-orders')

export interface CancelOrderResponse {
  message: string
  order: { id: number; status: import('@/types').OrderStatus }
}

export const cancelOrder = (orderId: number) =>
  api.post<CancelOrderResponse>(`/orders/${orderId}/cancel`)

export default api
