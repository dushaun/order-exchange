<script setup lang="ts">
import { useAuth } from '@/composables/useAuth'
import OrderForm from '@/components/trading/OrderForm.vue'
import OrderbookPanel from '@/components/trading/OrderbookPanel.vue'
import WalletPanel from '@/components/trading/WalletPanel.vue'
import OrderHistoryPanel from '@/components/trading/OrderHistoryPanel.vue'

const { user, logout, isLoading } = useAuth()
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
        <OrderForm />
        <OrderbookPanel />
        <WalletPanel />
      </div>

      <div class="mt-4 lg:mt-6">
        <OrderHistoryPanel />
      </div>
    </main>
  </div>
</template>
