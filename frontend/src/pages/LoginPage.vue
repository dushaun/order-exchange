<script setup lang="ts">
import { ref, computed } from 'vue'
import { useAuth } from '@/composables/useAuth'

const { login, isLoading } = useAuth()

const email = ref('')
const password = ref('')
const error = ref('')
const touched = ref({ email: false, password: false })

const emailError = computed(() => {
  if (!touched.value.email) return ''
  if (!email.value) return 'Email is required'
  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
  if (!emailRegex.test(email.value)) return 'Please enter a valid email address'
  return ''
})

const passwordError = computed(() => {
  if (!touched.value.password) return ''
  if (!password.value) return 'Password is required'
  if (password.value.length < 8) return 'Password must be at least 8 characters'
  return ''
})

const isFormValid = computed(() => {
  return (
    email.value &&
    password.value &&
    !emailError.value &&
    !passwordError.value &&
    password.value.length >= 8
  )
})

async function handleSubmit() {
  touched.value = { email: true, password: true }

  if (!isFormValid.value) {
    return
  }

  error.value = ''

  try {
    await login(email.value, password.value)
  } catch (err: unknown) {
    if (err && typeof err === 'object' && 'response' in err) {
      const axiosError = err as { response?: { status?: number; data?: { message?: string } } }
      if (axiosError.response?.status === 422 || axiosError.response?.status === 401) {
        error.value = axiosError.response.data?.message || 'Invalid email or password'
      } else {
        error.value = 'An error occurred. Please try again.'
      }
    } else {
      error.value = 'Network error. Please check your connection.'
    }
  }
}
</script>

<template>
  <div class="min-h-screen flex items-center justify-center bg-gray-100 px-4">
    <div class="w-full max-w-md">
      <div class="bg-white rounded-lg shadow-md p-8">
        <h1 class="text-2xl font-bold text-center text-gray-900 mb-8">
          Sign in to your account
        </h1>

        <form @submit.prevent="handleSubmit" novalidate>
          <div class="mb-4">
            <label for="email" class="block text-sm font-medium text-gray-700 mb-1">
              Email address
            </label>
            <input
              id="email"
              v-model="email"
              type="email"
              autocomplete="email"
              required
              :disabled="isLoading"
              :aria-invalid="!!emailError"
              :aria-describedby="emailError ? 'email-error' : undefined"
              class="w-full px-3 py-2 border rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 disabled:bg-gray-100 disabled:cursor-not-allowed"
              :class="emailError ? 'border-red-500' : 'border-gray-300'"
              @blur="touched.email = true"
            />
            <p v-if="emailError" id="email-error" class="mt-1 text-sm text-red-600">
              {{ emailError }}
            </p>
          </div>

          <div class="mb-6">
            <label for="password" class="block text-sm font-medium text-gray-700 mb-1">
              Password
            </label>
            <input
              id="password"
              v-model="password"
              type="password"
              autocomplete="current-password"
              required
              :disabled="isLoading"
              :aria-invalid="!!passwordError"
              :aria-describedby="passwordError ? 'password-error' : undefined"
              class="w-full px-3 py-2 border rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 disabled:bg-gray-100 disabled:cursor-not-allowed"
              :class="passwordError ? 'border-red-500' : 'border-gray-300'"
              @blur="touched.password = true"
            />
            <p v-if="passwordError" id="password-error" class="mt-1 text-sm text-red-600">
              {{ passwordError }}
            </p>
          </div>

          <div
            v-if="error"
            role="alert"
            class="mb-4 p-3 bg-red-50 border border-red-200 rounded-md"
          >
            <p class="text-sm text-red-600">{{ error }}</p>
          </div>

          <button
            type="submit"
            :disabled="isLoading || !isFormValid"
            class="w-full flex justify-center items-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:bg-blue-400 disabled:cursor-not-allowed"
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
            {{ isLoading ? 'Signing in...' : 'Sign in' }}
          </button>
        </form>
      </div>
    </div>
  </div>
</template>
