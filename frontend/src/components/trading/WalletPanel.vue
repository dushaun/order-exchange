<script setup lang="ts">
import { ref, onMounted } from 'vue'
import type { Asset } from '@/types'
import { getProfile } from '@/services/api'

const loading = ref(true)
const error = ref<string | null>(null)
const balance = ref<string>('')
const assets = ref<Asset[]>([])

async function fetchProfile() {
  loading.value = true
  try {
    const response = await getProfile()
    balance.value = response.data.user.balance
    assets.value = response.data.assets
    error.value = null
  } catch (err) {
    console.error('Failed to fetch profile:', err)
    error.value = 'Failed to load wallet data. Please try again.'
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  fetchProfile()
})

function refresh() {
  return fetchProfile()
}

defineExpose({
  refresh,
})
</script>

<template>
  <div class="bg-white border border-gray-200 rounded-lg p-4 min-h-[200px]">
    <h2 class="text-lg font-semibold text-gray-900 mb-4">Wallet Overview</h2>

    <div v-if="loading" class="flex items-center justify-center py-8">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-gray-900"></div>
      <span class="ml-3 text-gray-500">Loading...</span>
    </div>

    <div v-else-if="error" class="bg-red-50 border border-red-200 rounded-lg p-4">
      <p class="text-red-600 mb-3">{{ error }}</p>
      <button
        type="button"
        class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700 transition-colors"
        @click="fetchProfile"
      >
        Retry
      </button>
    </div>

    <template v-else>
      <!-- USD Balance -->
      <div class="mb-4">
        <p class="text-sm text-gray-500">USD Balance</p>
        <p class="text-2xl font-semibold text-gray-900">
          ${{ parseFloat(balance).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
        </p>
      </div>

      <div class="space-y-3">
        <p class="text-sm text-gray-500">Crypto Holdings</p>
        <div v-for="asset in assets" :key="asset.id" class="flex items-center justify-between">
          <span class="font-medium text-gray-900">{{ asset.symbol }}</span>
          <div class="text-right">
            <p class="text-green-600">
              <span class="text-xs text-gray-500">Available:</span>
              {{ parseFloat(asset.amount).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 8 }) }}
            </p>
            <p v-if="parseFloat(asset.locked_amount) > 0" class="text-amber-600">
              <span class="text-xs text-gray-500">Locked:</span>
              {{ parseFloat(asset.locked_amount).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 8 }) }}
            </p>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>
