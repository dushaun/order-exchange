<script setup lang="ts">
import { ref, watch, onMounted } from 'vue'
import type { AssetSymbol, OrderbookOrder } from '@/types'
import { getOrderbook } from '@/services/api'

const symbols = ['BTC', 'ETH'] as const

const selectedSymbol = ref<AssetSymbol>('BTC')
const buyOrders = ref<OrderbookOrder[]>([])
const sellOrders = ref<OrderbookOrder[]>([])
const loading = ref(false)
const error = ref<string | null>(null)

const fetchOrderbook = async () => {
  loading.value = true
  error.value = null

  try {
    const response = await getOrderbook(selectedSymbol.value)
    buyOrders.value = response.data.buy_orders
    sellOrders.value = response.data.sell_orders
  } catch {
    error.value = 'Failed to load orderbook. Please try again.'
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  fetchOrderbook()
})

watch(selectedSymbol, () => {
  fetchOrderbook()
})

const formatPrice = (price: string) =>
  parseFloat(price).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })

const formatAmount = (amount: string) =>
  parseFloat(amount).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 8 })

defineExpose({ refresh: fetchOrderbook })
</script>

<template>
  <div class="bg-white border border-gray-200 rounded-lg p-4 min-h-[200px]">
    <div class="flex items-center justify-between mb-4">
      <h2 class="text-lg font-semibold text-gray-900">Orderbook</h2>
      <div class="flex gap-1">
        <button
          v-for="symbol in symbols"
          :key="symbol"
          :disabled="loading"
          :class="[
            'px-3 py-1 text-sm font-medium rounded transition-colors',
            selectedSymbol === symbol
              ? 'bg-blue-600 text-white'
              : 'bg-gray-100 text-gray-600 hover:bg-gray-200',
            loading ? 'opacity-50 cursor-not-allowed' : '',
          ]"
          @click="selectedSymbol = symbol"
        >
          {{ symbol }}
        </button>
      </div>
    </div>

    <div v-if="error" class="text-red-600 bg-red-50 p-2 rounded mb-3 flex items-center justify-between">
      <span>{{ error }}</span>
      <button
        :disabled="loading"
        :class="[
          'px-3 py-1 text-sm font-medium rounded bg-red-600 text-white hover:bg-red-700 transition-colors',
          loading ? 'opacity-50 cursor-not-allowed' : '',
        ]"
        @click="fetchOrderbook"
      >
        {{ loading ? 'Retrying...' : 'Retry' }}
      </button>
    </div>

    <div v-if="loading" class="text-gray-500 text-center py-4">
      Loading...
    </div>

    <template v-else-if="!error">
      <div class="mb-3">
        <h3 class="text-sm font-medium text-red-600 mb-2">Sell Orders</h3>
        <div v-if="sellOrders.length > 0">
          <div class="grid grid-cols-2 gap-2 text-xs font-medium text-gray-500 mb-1">
            <span>Price</span>
            <span class="text-right">Amount</span>
          </div>
          <div
            v-for="order in sellOrders"
            :key="order.id"
            class="grid grid-cols-2 gap-2 text-sm bg-red-50 text-red-600 px-1 py-0.5 rounded"
          >
            <span>{{ formatPrice(order.price) }}</span>
            <span class="text-right">{{ formatAmount(order.amount) }}</span>
          </div>
        </div>
        <p v-else class="text-gray-400 text-sm">No sell orders</p>
      </div>

      <div>
        <h3 class="text-sm font-medium text-green-600 mb-2">Buy Orders</h3>
        <div v-if="buyOrders.length > 0">
          <div class="grid grid-cols-2 gap-2 text-xs font-medium text-gray-500 mb-1">
            <span>Price</span>
            <span class="text-right">Amount</span>
          </div>
          <div
            v-for="order in buyOrders"
            :key="order.id"
            class="grid grid-cols-2 gap-2 text-sm bg-green-50 text-green-600 px-1 py-0.5 rounded"
          >
            <span>{{ formatPrice(order.price) }}</span>
            <span class="text-right">{{ formatAmount(order.amount) }}</span>
          </div>
        </div>
        <p v-else class="text-gray-400 text-sm">No buy orders</p>
      </div>
    </template>
  </div>
</template>
