<script setup lang="ts">
import { onMounted, watch } from 'vue'
import { RouterView, useRouter, useRoute } from 'vue-router'
import { useAuth } from '@/composables/useAuth'

const router = useRouter()
const route = useRoute()
const { checkAuth, authChecked, isAuthenticated } = useAuth()

onMounted(() => {
  checkAuth()
})

watch(authChecked, (checked) => {
  if (!checked) return

  if (route.meta.requiresAuth && !isAuthenticated.value) {
    router.replace({ name: 'login' })
  }
  else if (route.meta.guest && isAuthenticated.value) {
    router.replace({ name: 'dashboard' })
  }
})
</script>

<template>
  <div v-if="!authChecked" class="min-h-screen bg-gray-100 flex items-center justify-center">
    <div class="text-center">
      <svg
        class="animate-spin h-8 w-8 text-blue-600 mx-auto"
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
      <p class="mt-2 text-sm text-gray-600">Loading...</p>
    </div>
  </div>

  <RouterView v-else />
</template>
