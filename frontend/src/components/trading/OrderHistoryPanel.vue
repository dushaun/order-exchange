<script setup lang="ts">
import { ref, onMounted } from 'vue'
import type { Order, OrderStatus, OrderSide } from '@/types'
import { ORDER_STATUS } from '@/types'
import { getMyOrders, cancelOrder } from '@/services/api'

const emit = defineEmits<{ walletUpdated: [] }>()

const orders = ref<Order[]>([])
const loading = ref(false)
const error = ref<string | null>(null)
const cancellingOrderId = ref<number | null>(null)
const confirmingOrderId = ref<number | null>(null)

const getSideClass = (side: OrderSide) => {
  return side === 'buy' ? 'text-green-600 font-medium' : 'text-red-600 font-medium'
}

const getSideLabel = (side: OrderSide) => {
  return side === 'buy' ? 'Buy' : 'Sell'
}

const getStatusClass = (status: OrderStatus) => {
  switch (status) {
    case ORDER_STATUS.OPEN:
      return 'text-blue-600 bg-blue-50 px-2 py-0.5 rounded text-xs font-medium'
    case ORDER_STATUS.FILLED:
      return 'text-green-600 bg-green-50 px-2 py-0.5 rounded text-xs font-medium'
    case ORDER_STATUS.CANCELLED:
      return 'text-red-600 bg-red-50 px-2 py-0.5 rounded text-xs font-medium'
    default:
      return ''
  }
}

const getStatusLabel = (status: OrderStatus) => {
  switch (status) {
    case ORDER_STATUS.OPEN:
      return 'Open'
    case ORDER_STATUS.FILLED:
      return 'Filled'
    case ORDER_STATUS.CANCELLED:
      return 'Cancelled'
    default:
      return ''
  }
}

const formatPrice = (price: string) =>
  parseFloat(price).toLocaleString('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })

const formatAmount = (amount: string) =>
  parseFloat(amount).toLocaleString('en-US', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 8,
  })

const formatDate = (dateString: string) =>
  new Date(dateString).toLocaleString('en-US', {
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })

const fetchOrders = async () => {
  loading.value = true
  error.value = null
  try {
    const response = await getMyOrders()
    orders.value = response.data.orders
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Failed to load orders'
  } finally {
    loading.value = false
  }
}

onMounted(fetchOrders)

const handleCancelOrder = async (orderId: number) => {
  cancellingOrderId.value = orderId
  try {
    await cancelOrder(orderId)
    const order = orders.value.find((o) => o.id === orderId)
    if (order) {
      order.status = ORDER_STATUS.CANCELLED
    }
    emit('walletUpdated')
  } catch (e) {
    alert(e instanceof Error ? e.message : 'Failed to cancel order')
  } finally {
    cancellingOrderId.value = null
  }
}

const promptCancelOrder = (orderId: number) => {
  confirmingOrderId.value = orderId
}

const confirmCancelOrder = () => {
  if (confirmingOrderId.value !== null) {
    handleCancelOrder(confirmingOrderId.value)
    confirmingOrderId.value = null
  }
}

const cancelConfirmation = () => {
  confirmingOrderId.value = null
}

defineExpose({ refresh: fetchOrders })
</script>

<template>
  <div class="bg-white border border-gray-200 rounded-lg p-4 min-h-[200px]">
    <h2 class="text-lg font-semibold text-gray-900 mb-4">Order History</h2>

    <p v-if="loading" class="text-gray-500 text-center py-4">Loading...</p>

    <div v-else-if="error" class="text-red-600 bg-red-50 p-2 rounded">
      <p>{{ error }}</p>
      <button
        type="button"
        class="mt-2 text-sm underline hover:no-underline"
        @click="fetchOrders"
      >
        Retry
      </button>
    </div>

    <p v-else-if="orders.length === 0" class="text-gray-400 text-center py-4">No orders yet</p>

    <table v-else aria-label="Order history" class="w-full text-sm">
      <thead>
        <tr class="bg-gray-50 text-left text-gray-600 font-medium">
          <th class="px-3 py-2">Symbol</th>
          <th class="px-3 py-2">Side</th>
          <th class="px-3 py-2">Price</th>
          <th class="px-3 py-2">Amount</th>
          <th class="px-3 py-2">Status</th>
          <th class="px-3 py-2">Date</th>
          <th class="px-3 py-2">Actions</th>
        </tr>
      </thead>
      <tbody>
        <tr
          v-for="order in orders"
          :key="order.id"
          class="border-b border-gray-100 hover:bg-gray-50"
        >
          <td class="px-3 py-2">{{ order.symbol }}</td>
          <td class="px-3 py-2" :class="getSideClass(order.side)">{{ getSideLabel(order.side) }}</td>
          <td class="px-3 py-2">{{ formatPrice(order.price) }}</td>
          <td class="px-3 py-2">{{ formatAmount(order.amount) }}</td>
          <td class="px-3 py-2"><span :class="getStatusClass(order.status)">{{ getStatusLabel(order.status) }}</span></td>
          <td class="px-3 py-2">{{ formatDate(order.created_at) }}</td>
          <td class="px-3 py-2">
            <button
              v-if="order.status === ORDER_STATUS.OPEN"
              type="button"
              aria-label="Cancel order"
              class="text-red-600 hover:text-red-800 text-xs font-medium disabled:opacity-50"
              :disabled="cancellingOrderId === order.id"
              @click="promptCancelOrder(order.id)"
            >
              {{ cancellingOrderId === order.id ? 'Cancelling...' : 'Cancel' }}
            </button>
          </td>
        </tr>
      </tbody>
    </table>

    <div
      v-if="confirmingOrderId !== null"
      class="fixed inset-0 bg-black/25 flex items-center justify-center z-50"
    >
      <div
        role="dialog"
        aria-labelledby="cancel-dialog-title"
        class="bg-white rounded-lg p-6 max-w-sm mx-4 shadow-xl"
      >
        <h3 id="cancel-dialog-title" class="text-lg font-semibold text-gray-900 mb-2">
          Cancel Order
        </h3>
        <p class="text-gray-600">
          Are you sure you want to cancel this order? This action cannot be undone.
        </p>
        <div class="flex gap-3 mt-4">
          <button
            type="button"
            class="flex-1 px-4 py-2 border border-gray-300 rounded text-gray-700 hover:bg-gray-50"
            @click="cancelConfirmation"
          >
            Keep Order
          </button>
          <button
            type="button"
            class="flex-1 px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700"
            @click="confirmCancelOrder"
          >
            Cancel Order
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
