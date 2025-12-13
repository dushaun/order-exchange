import { createRouter, createWebHistory } from 'vue-router'
import { useAuth } from '@/composables/useAuth'
import LoginPage from '@/pages/LoginPage.vue'
import DashboardPage from '@/pages/DashboardPage.vue'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/',
      redirect: '/dashboard',
    },
    {
      path: '/login',
      name: 'login',
      component: LoginPage,
      meta: { guest: true },
    },
    {
      path: '/dashboard',
      name: 'dashboard',
      component: DashboardPage,
      meta: { requiresAuth: true },
    },
  ],
})

router.beforeEach((to) => {
  const { isAuthenticated, authChecked } = useAuth()

  if (!authChecked.value) {
    return true
  }

  if (to.meta.guest && isAuthenticated.value) {
    return { name: 'dashboard' }
  }

  if (to.meta.requiresAuth && !isAuthenticated.value) {
    return { name: 'login' }
  }

  return true
})

export default router
