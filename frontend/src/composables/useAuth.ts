import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import {
  getCsrfCookie,
  login as apiLogin,
  logout as apiLogout,
  getUser,
} from '@/services/api'
import type { User } from '@/types'

const user = ref<User | null>(null)
const isLoading = ref(false)
const authChecked = ref(false)

export function useAuth() {
  const router = useRouter()

  const isAuthenticated = computed(() => !!user.value)

  async function login(email: string, password: string) {
    isLoading.value = true
    try {
      await getCsrfCookie()
      const response = await apiLogin(email, password)
      user.value = response.data.user
      router.push('/dashboard')
    } finally {
      isLoading.value = false
    }
  }

  async function logout() {
    isLoading.value = true
    try {
      await getCsrfCookie()
      await apiLogout()
      user.value = null
      router.push('/login')
    } finally {
      isLoading.value = false
    }
  }

  async function checkAuth() {
    if (authChecked.value) {
      return
    }
    isLoading.value = true
    try {
      const response = await getUser()
      user.value = response.data
    } catch {
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
    login,
    logout,
    checkAuth,
  }
}
