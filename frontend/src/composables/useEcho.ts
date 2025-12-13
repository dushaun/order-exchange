import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

declare global {
  interface Window {
    Pusher: typeof Pusher
    Echo: Echo<'pusher'>
  }
}

let echoInstance: Echo<'pusher'> | null = null

export function useEcho() {
  function initEcho() {
    if (echoInstance) {
      return echoInstance
    }

    window.Pusher = Pusher

    echoInstance = new Echo({
      broadcaster: 'pusher',
      key: import.meta.env.VITE_PUSHER_APP_KEY,
      cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER,
      forceTLS: true,
    })

    window.Echo = echoInstance
    return echoInstance
  }

  function getEcho() {
    return echoInstance
  }

  function disconnectEcho() {
    if (echoInstance) {
      echoInstance.disconnect()
      echoInstance = null
    }
  }

  return {
    initEcho,
    getEcho,
    disconnectEcho,
  }
}
