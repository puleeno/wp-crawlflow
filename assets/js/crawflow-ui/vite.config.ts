import path from 'path';
import { defineConfig, loadEnv } from 'vite';
import react from '@vitejs/plugin-react';

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, '.', '');
    
    // WordPress plugin paths
    const buildDir = path.resolve(__dirname, 'dist');
    
    return {
      server: {
        port: 3000,
        host: '0.0.0.0',
      },
      plugins: [react()],
      define: {
        'process.env.API_KEY': JSON.stringify(env.GEMINI_API_KEY),
        'process.env.GEMINI_API_KEY': JSON.stringify(env.GEMINI_API_KEY)
      },
      resolve: {
        alias: {
          '@': path.resolve(__dirname, '.'),
        }
      },
      build: {
        outDir: buildDir,
        emptyOutDir: true,
        sourcemap: true, // Enable source maps for debugging
        rollupOptions: {
          input: path.resolve(__dirname, 'index.html'),
          output: {
            entryFileNames: 'crawflow-ui.[hash].js',
            chunkFileNames: 'chunks/[name].[hash].js',
            assetFileNames: 'assets/[name].[hash].[ext]',
            format: 'iife',
            name: 'CrawlFlowUI',
          },
        },
        manifest: true,
        manifestFileName: 'manifest.json',
      },
      base: mode === 'production' ? '/wp-content/plugins/wp-crawlflow/assets/js/crawflow-ui/dist/' : '/',
    };
});
