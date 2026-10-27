import { defineConfig } from 'vite';
import { svelte } from '@sveltejs/vite-plugin-svelte';

// One IIFE + one stylesheet into ../dist, referenced by
// sacda_fast_search.libraries.yml. Fixed names, no hashes: Drupal adds its
// own cache-busting query string.
export default defineConfig({
  plugins: [svelte()],
  build: {
    outDir: '../dist',
    emptyOutDir: true,
    cssCodeSplit: false,
    lib: {
      entry: 'src/main.js',
      name: 'SacdaFastSearch',
      formats: ['iife'],
      fileName: () => 'fast-search.js',
      cssFileName: 'fast-search',
    },
  },
});
