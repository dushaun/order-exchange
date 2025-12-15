import { fileURLToPath, URL } from 'node:url'

import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import vueDevTools from 'vite-plugin-vue-devtools'
import tailwindcss from '@tailwindcss/vite'

const backendUrl = process.env.VITE_BACKEND_URL || 'http://localhost'

// https://vite.dev/config/
export default defineConfig({
  plugins: [vue(), vueDevTools(), tailwindcss()],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url)),
    },
  },
  server: {
    proxy: {
      '/api': {
        target: backendUrl,
        changeOrigin: true,
        secure: false,
      },
      '/sanctum': {
        target: backendUrl,
        changeOrigin: true,
        secure: false,
      },
      '/broadcasting': {
        target: backendUrl,
        changeOrigin: true,
        secure: false,
      },
    },
  },
})
