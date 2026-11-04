import { defineConfig } from 'vite';

export default defineConfig({
  build: {
    outDir: 'dist',
    rollupOptions: {
      output: {
        assetFileNames: (assetInfo) => {
          if (assetInfo.name.endsWith('.css')) {
            return 'styles/[name].[hash].css';
          }
          return 'assets/[name].[hash].[ext]';
        },
        entryFileNames: 'js/[name].[hash].js',
      },
    },
  },
  publicDir: false,
  css: {
    postcss: false,
  },
});