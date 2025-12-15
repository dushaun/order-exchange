import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import {
  getCsrfCookie,
  login as apiLogin,
  logout as apiLogout,
  getUser,
  normalizeApiError,
} from '@/services/api'
import type { User } from '@/types'

const user = ref<User | null>(null)
const isLoading = ref(false)
const authChecked = ref(false)
const error = ref<string | null>(null)

export function useAuth() {
  const router = useRouter()

  const isAuthenticated = computed(() => !!user.value)

  function clearError() {
    error.value = null
  }

  async function login(email: string, password: string) {
    isLoading.value = true
    error.value = null
    try {
      await getCsrfCookie()
      const response = await apiLogin(email, password)
      user.value = response.data.user
      router.push('/dashboard')
    } catch (e) {
      const apiError = normalizeApiError(e)
      error.value = apiError.message
      throw apiError
    } finally {
      isLoading.value = false
    }
  }

  async function logout() {
    isLoading.value = true
    error.value = null
    try {
      await getCsrfCookie()
      await apiLogout()
      user.value = null
      router.push('/login')
    } catch (e) {
      const apiError = normalizeApiError(e)
      error.value = apiError.message
      throw apiError
    } finally {
      isLoading.value = false
    }
  }

  async function checkAuth() {
    if (authChecked.value) {
      return
    }
    isLoading.value = true
    error.value = null
    try {
      const response = await getUser()
      user.value = response.data
    } catch (e) {
      const apiError = normalizeApiError(e)
      if (apiError.status !== 401) {
        error.value = apiError.message
      }
      user.value = null
    } finally {
      isLoading.value = false
      authChecked.value = true
    }
  }

  return {
    user,
    isAuthenticated,
    isLoading,
    authChecked,
    error,
    login,
    logout,
    checkAuth,
    clearError,
  }
}
