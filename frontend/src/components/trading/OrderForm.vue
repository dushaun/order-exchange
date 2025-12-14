<script setup lang="ts">
import { ref, computed } from 'vue'
import { AxiosError } from 'axios'
import type { AssetSymbol, OrderSide } from '@/types'
import { createOrder } from '@/services/api'

const symbols = ['BTC', 'ETH'] as const

const symbol = ref<AssetSymbol>('BTC')
const side = ref<OrderSide>('buy')
const price = ref('')
const amount = ref('')
const loading = ref(false)
const error = ref<string | null>(null)
const success = ref<string | null>(null)

const total = computed(() => {
  const priceNum = parseFloat(price.value)
  const amountNum = parseFloat(amount.value)
  if (isNaN(priceNum) || isNaN(amountNum) || priceNum <= 0 || amountNum <= 0) {
    return null
  }
  return (priceNum * amountNum).toFixed(2)
})

function validateForm(): string | null {
  if (!symbol.value || !symbols.includes(symbol.value)) {
    return 'Please select a valid symbol (BTC or ETH)'
  }
  if (!side.value || (side.value !== 'buy' && side.value !== 'sell')) {
    return 'Please select buy or sell'
  }
  const priceNum = parseFloat(price.value)
  if (!price.value || isNaN(priceNum) || priceNum <= 0) {
    return 'Please enter a valid price greater than 0'
  }
  const amountNum = parseFloat(amount.value)
  if (!amount.value || isNaN(amountNum) || amountNum <= 0) {
    return 'Please enter a valid amount greater than 0'
  }
  return null
}

async function handleSubmit() {
  const validationError = validateForm()
  if (validationError) {
    error.value = validationError
    return
  }

  loading.value = true
  error.value = null
  success.value = null

  try {
    await createOrder({
      symbol: symbol.value,
      side: side.value,
      price: price.value,
      amount: amount.value,
    })
    success.value = 'Order placed successfully'
    symbol.value = 'BTC'
    side.value = 'buy'
    price.value = ''
    amount.value = ''
    setTimeout(() => {
      success.value = null
    }, 3000)
  } catch (err) {
    if (err instanceof AxiosError && err.response?.data?.message) {
      error.value = err.response.data.message
    } else {
      error.value = 'An error occurred while placing the order'
    }
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="bg-white border border-gray-200 rounded-lg p-4 min-h-[200px]">
    <h2 class="text-lg font-semibold text-gray-900 mb-4">Place Order</h2>

    <div
      v-if="success"
      class="mb-4 p-3 rounded text-green-600 bg-green-50 border border-green-200"
      role="alert"
    >
      {{ success }}
    </div>

    <div
      v-if="error"
      id="error-message"
      class="mb-4 p-3 rounded text-red-600 bg-red-50 border border-red-200"
      role="alert"
    >
      {{ error }}
    </div>

    <div class="mb-4">
      <label for="symbol" class="block text-sm font-medium text-gray-700 mb-1">Symbol</label>
      <select
        id="symbol"
        v-model="symbol"
        :disabled="loading"
        class="border border-gray-300 rounded px-3 py-2 w-full"
        aria-required="true"
      >
        <option v-for="s in symbols" :key="s" :value="s">{{ s }}</option>
      </select>
    </div>

    <div class="mb-4">
      <span class="block text-sm font-medium text-gray-700 mb-1">Side</span>
      <div class="flex gap-2">
        <button
          type="button"
          :disabled="loading"
          :aria-pressed="side === 'buy'"
          :class="[
            'flex-1 px-3 py-2 text-sm font-medium rounded transition-colors',
            side === 'buy'
              ? 'bg-green-600 text-white'
              : 'bg-green-50 text-green-600 border border-green-500'
          ]"
          @click="side = 'buy'"
        >
          Buy
        </button>
        <button
          type="button"
          :disabled="loading"
          :aria-pressed="side === 'sell'"
          :class="[
            'flex-1 px-3 py-2 text-sm font-medium rounded transition-colors',
            side === 'sell'
              ? 'bg-red-600 text-white'
              : 'bg-red-50 text-red-600 border border-red-500'
          ]"
          @click="side = 'sell'"
        >
          Sell
        </button>
      </div>
    </div>

    <div class="mb-4">
      <label for="price" class="block text-sm font-medium text-gray-700 mb-1">Price (USD)</label>
      <input
        id="price"
        v-model="price"
        type="text"
        inputmode="decimal"
        placeholder="0.00"
        :disabled="loading"
        class="border border-gray-300 rounded px-3 py-2 w-full"
        aria-required="true"
      />
    </div>

    <div class="mb-4">
      <label for="amount" class="block text-sm font-medium text-gray-700 mb-1">Amount ({{ symbol }})</label>
      <input
        id="amount"
        v-model="amount"
        type="text"
        inputmode="decimal"
        placeholder="0.00000000"
        :disabled="loading"
        class="border border-gray-300 rounded px-3 py-2 w-full"
        aria-required="true"
      />
    </div>

    <div class="mb-4">
      <span class="text-sm text-gray-600">
        {{ side === 'buy' ? 'Total Cost:' : 'Total Proceeds:' }}
      </span>
      <span v-if="total" class="ml-2 font-semibold">${{ total }} USD</span>
      <span v-else class="ml-2 text-gray-400">--</span>
    </div>

    <button
      type="button"
      :disabled="loading"
      :class="[
        'w-full py-2 px-4 rounded font-medium text-white transition-colors',
        side === 'buy' ? 'bg-green-600 hover:bg-green-700' : 'bg-red-600 hover:bg-red-700',
        loading ? 'opacity-50 cursor-not-allowed' : ''
      ]"
      @click="handleSubmit"
    >
      {{ loading ? 'Placing...' : 'Place Order' }}
    </button>
  </div>
</template>
