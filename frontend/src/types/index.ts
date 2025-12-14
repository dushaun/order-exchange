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
