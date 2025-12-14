import axios from 'axios'

const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL,
  withCredentials: true,
  withXSRFToken: true,
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json',
  },
})

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

export default api
