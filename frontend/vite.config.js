import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import path from 'path'

export default defineConfig({
  plugins: [react()],
  resolve: {
    alias: {
      '@': path.resolve(__dirname, './src'),
    },
  },
  server: {
    port: 5173,
    proxy: {
      // Todas las llamadas a /api/* se redirigen al backend PHP
      '/api': {
        target: process.env.BACKEND_URL || 'http://localhost:80',   // en Docker: http://backend
        changeOrigin: true,
        rewrite: (path) => path.replace(/^\/api/, '/backend/Api'), // Si tu backend espera las rutas sin el prefijo /api, descomenta esta línea
      },
    },
  },
})
