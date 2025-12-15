import Echo from 'laravel-echo'
import Pusher from 'pusher-js'
import { ref } from 'vue'
import type { EchoConnectionStatus } from '@/types'

declare global {
  interface Window {
    Pusher: typeof Pusher
    Echo: Echo<'pusher'>
  }
}

function getXsrfToken(): string {
  const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/)
  return match?.[1] ? decodeURIComponent(match[1]) : ''
}

let echoInstance: Echo<'pusher'> | null = null
const connectionStatus = ref<EchoConnectionStatus>('disconnected')

export function useEcho() {
  function initEcho(): Echo<'pusher'> | null {
    if (echoInstance) {
      return echoInstance
    }

    window.Pusher = Pusher
    connectionStatus.value = 'connecting'

    try {
      echoInstance = new Echo({
        broadcaster: 'pusher',
        key: import.meta.env.VITE_PUSHER_APP_KEY,
        cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER,
        forceTLS: true,
        authEndpoint: '/broadcasting/auth',
        auth: {
          headers: {
            Accept: 'application/json',
            'X-XSRF-TOKEN': getXsrfToken(),
          },
        },
      })
    } catch (error) {
      console.error('Failed to initialize Echo:', error)
      connectionStatus.value = 'error'
      return null
    }

    const pusher = echoInstance.connector.pusher

    pusher.connection.bind('connected', () => {
      connectionStatus.value = 'connected'
    })

    pusher.connection.bind('disconnected', () => {
      connectionStatus.value = 'disconnected'
    })

    pusher.connection.bind('error', () => {
      connectionStatus.value = 'error'
    })

    pusher.connection.bind('connecting', () => {
      connectionStatus.value = 'connecting'
    })

    window.Echo = echoInstance
    return echoInstance
  }

  function getEcho() {
    return echoInstance
  }

  function disconnectEcho() {
    if (echoInstance) {
      const pusher = echoInstance.connector.pusher
      pusher.connection.unbind('connected')
      pusher.connection.unbind('disconnected')
      pusher.connection.unbind('error')
      pusher.connection.unbind('connecting')

      echoInstance.disconnect()
      echoInstance = null
    }
    connectionStatus.value = 'disconnected'
  }

  function subscribeToUserChannel(userId: number) {
    if (!echoInstance) {
      throw new Error('Echo not initialized. Call initEcho() first.')
    }
    return echoInstance.private(`user.${userId}`)
  }

  function leaveUserChannel(userId: number) {
    if (echoInstance) {
      echoInstance.leave(`user.${userId}`)
    }
  }

  return {
    initEcho,
    getEcho,
    disconnectEcho,
    subscribeToUserChannel,
    leaveUserChannel,
    connectionStatus,
  }
}
