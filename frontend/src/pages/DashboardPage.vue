<script setup lang="ts">
import { onMounted, onUnmounted, ref, watch } from 'vue'
import { useAuth } from '@/composables/useAuth'
import { useEcho } from '@/composables/useEcho'
import OrderForm from '@/components/trading/OrderForm.vue'
import OrderbookPanel from '@/components/trading/OrderbookPanel.vue'
import WalletPanel from '@/components/trading/WalletPanel.vue'
import OrderHistoryPanel from '@/components/trading/OrderHistoryPanel.vue'
import type { OrderMatchedEventPayload } from '@/types'
import type { Channel } from 'laravel-echo'

const { user, logout, isLoading } = useAuth()
const {
  initEcho,
  getEcho,
  disconnectEcho,
  subscribeToUserChannel,
  leaveUserChannel,
  connectionStatus,
} = useEcho()

const walletPanelRef = ref<InstanceType<typeof WalletPanel> | null>(null)
const orderbookPanelRef = ref<InstanceType<typeof OrderbookPanel> | null>(null)
const orderHistoryPanelRef = ref<InstanceType<typeof OrderHistoryPanel> | null>(null)

const userChannel = ref<Channel | null>(null)

const showUpdateFlash = ref(false)

function triggerUpdateFlash() {
  showUpdateFlash.value = true
  setTimeout(() => {
    showUpdateFlash.value = false
  }, 1500)
}

let refreshTimeout: ReturnType<typeof setTimeout> | null = null

function debouncedRefresh() {
  if (refreshTimeout) {
    clearTimeout(refreshTimeout)
  }
  refreshTimeout = setTimeout(() => {
    walletPanelRef.value?.refresh()
    orderbookPanelRef.value?.refresh()
    orderHistoryPanelRef.value?.refresh()
    refreshTimeout = null
  }, 100)
}

function handleOrderMatched(payload: OrderMatchedEventPayload) {
  try {
    console.log('Order matched event received:', {
      symbol: payload.order_details.symbol,
      price: payload.order_details.executed_price,
      amount: payload.order_details.amount,
    })

    debouncedRefresh()

    triggerUpdateFlash()
  } catch (error) {
    console.error('Error handling OrderMatched event:', error)
  }
}

function handleWalletUpdated() {
  walletPanelRef.value?.refresh()
}

function handleOrderPlaced() {
  walletPanelRef.value?.refresh()
  orderbookPanelRef.value?.refresh()
  orderHistoryPanelRef.value?.refresh()
}

onMounted(() => {
  const echo = initEcho()
  if (echo && user.value) {
    userChannel.value = subscribeToUserChannel(user.value.id)
    userChannel.value.listen('.order.matched', handleOrderMatched)
  }
})

watch(user, (newUser) => {
  if (newUser && getEcho() && connectionStatus.value !== 'error') {
    userChannel.value = subscribeToUserChannel(newUser.id)
    userChannel.value.listen('.order.matched', handleOrderMatched)
  }
})

onUnmounted(() => {
  if (userChannel.value) {
    userChannel.value.stopListening('.order.matched')
  }
  if (refreshTimeout) {
    clearTimeout(refreshTimeout)
  }
  if (user.value) {
    leaveUserChannel(user.value.id)
  }
  disconnectEcho()
})
</script>

<template>
  <div class="min-h-screen bg-gray-100">
    <header class="bg-white shadow">
      <div class="max-w-7xl mx-auto px-4 py-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between">
          <div>
            <h1 class="text-xl font-semibold text-gray-900">Limit Order Exchange</h1>
            <p v-if="user" class="text-sm text-gray-600">
              Welcome, {{ user.name || user.email }}
            </p>
            <span
              class="inline-flex items-center gap-1.5 text-xs"
              :class="{
                'text-green-600': connectionStatus === 'connected',
                'text-yellow-600': connectionStatus === 'connecting',
                'text-red-600': connectionStatus === 'error' || connectionStatus === 'disconnected',
              }"
            >
              <span
                class="h-2 w-2 rounded-full"
                :class="{
                  'bg-green-500': connectionStatus === 'connected',
                  'bg-yellow-500': connectionStatus === 'connecting',
                  'bg-red-500': connectionStatus === 'error' || connectionStatus === 'disconnected',
                }"
              ></span>
              {{
                connectionStatus === 'connected'
                  ? 'Live'
                  : connectionStatus === 'connecting'
                    ? 'Connecting...'
                    : 'Offline'
              }}
            </span>
            <span
              v-if="showUpdateFlash"
              class="ml-2 inline-flex items-center gap-1 text-xs text-blue-600 animate-pulse"
            >
              <span class="h-2 w-2 rounded-full bg-blue-500"></span>
              Updated
            </span>
          </div>
          <button
            type="button"
            :disabled="isLoading"
            class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 disabled:bg-red-400 disabled:cursor-not-allowed"
            @click="logout"
          >
            <svg
              v-if="isLoading"
              class="animate-spin -ml-1 mr-2 h-4 w-4 text-white"
              xmlns="http://www.w3.org/2000/svg"
              fill="none"
              viewBox="0 0 24 24"
              stroke="currentColor"
              stroke-width="1.5"
              aria-hidden="true"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99"
              />
            </svg>
            {{ isLoading ? 'Signing out...' : 'Sign out' }}
          </button>
        </div>
      </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 py-6 sm:px-6 lg:px-8">
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 lg:gap-6">
        <OrderForm @order-placed="handleOrderPlaced" />
        <OrderbookPanel ref="orderbookPanelRef" />
        <WalletPanel ref="walletPanelRef" />
      </div>

      <div class="mt-4 lg:mt-6">
        <OrderHistoryPanel ref="orderHistoryPanelRef" @wallet-updated="handleWalletUpdated" />
      </div>
    </main>
  </div>
</template>
