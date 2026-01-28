import { defineConfig } from 'vite'

export default defineConfig({
  server: {
    host: true,          // важно для Docker/WSL
    port: 5173,
    strictPort: true,
    watch: {
      usePolling: true   // часто нужно в Docker/WSL, на mac/linux может работать и без
    },
    hmr: true
  },

  build: {
    outDir: 'dist',
    emptyOutDir: true,
    manifest: true,
    rollupOptions: {
      input: {
        app: '/src/main.js'
      }
    }
  },

  plugins: [
    {
      name: 'winter-htm-full-reload',
      handleHotUpdate({ file, server }) {
        // pages/layouts/partials в Winter обычно .htm
        if (file.endsWith('.htm')) {
          server.ws.send({ type: 'full-reload' })
        }
      }
    }
  ]
})
