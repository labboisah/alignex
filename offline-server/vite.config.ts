import react from '@vitejs/plugin-react';
import { resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vite';

const rootDir = fileURLToPath(new URL('.', import.meta.url));

export default defineConfig({
  plugins: [react()],
  root: '.',
  base: './',
  build: {
    outDir: 'dist/renderer',
    emptyOutDir: false,
    rollupOptions: {
      input: {
        admin: resolve(rootDir, 'index.html'),
        candidate: resolve(rootDir, 'candidate.html'),
      },
    },
  },
  server: {
    port: 5174,
    strictPort: true,
  },
  resolve: {
    alias: {
      '@': '/src/renderer',
    },
  },
});
