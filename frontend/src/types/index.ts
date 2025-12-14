export interface User {
  id: number
  name: string
  email: string
  balance: string
  created_at: string
  updated_at: string
}

export type AssetSymbol = 'BTC' | 'ETH'

export interface Asset {
  id: number
  user_id: number
  symbol: AssetSymbol
  amount: string
  locked_amount: string
  created_at: string
  updated_at: string
}

export type OrderSide = 'buy' | 'sell'

export type OrderStatus = 1 | 2 | 3

export const ORDER_STATUS = {
  OPEN: 1,
  FILLED: 2,
  CANCELLED: 3,
} as const

export interface Order {
  id: number
  user_id: number
  symbol: AssetSymbol
  side: OrderSide
  price: string
  amount: string
  status: OrderStatus
  created_at: string
  updated_at: string
}

// Simplified order for orderbook display (matches API response)
// Excludes user_id, status, updated_at per Epic 2 AC:3
export interface OrderbookOrder {
  id: number
  symbol: AssetSymbol
  side: OrderSide
  price: string
  amount: string
  created_at: string
}
