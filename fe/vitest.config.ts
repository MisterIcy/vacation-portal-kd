import { defineConfig } from 'vitest/config';
import react from '@vitejs/plugin-react';
import path from 'path';

export default defineConfig({
  plugins: [react()],
  test: {
    globals: true,
    environment: 'jsdom',
    setupFiles: './src/tests/setup.ts',
    coverage: {
      provider: 'v8',
      reporter: ['text', 'json', 'html', 'lcov'],
      exclude: [
        'node_modules/',
        'src/tests/',
        '**/*.test.{ts,tsx}',
        '**/*.spec.{ts,tsx}',
        '**/types.ts',
        'src/main.tsx',
        'vite.config.ts',
        'vitest.config.ts',
      ],
      include: ['src/**/*.{ts,tsx}'],
      thresholds: {
        // Just under what the suite reaches (about 95 / 93 / 93 / 95), so a real drop fails the run.
        lines: 94,
        functions: 92,
        branches: 91,
        statements: 94,
      },
    },
  },
  resolve: {
    alias: {
      '@api': path.resolve(__dirname, './src/api'),
      '@assets': path.resolve(__dirname, './src/assets'),
      '@components': path.resolve(__dirname, './src/components'),
      '@context': path.resolve(__dirname, './src/context'),
      '@domains': path.resolve(__dirname, './src/domains'),
      '@hooks': path.resolve(__dirname, './src/hooks'),
      '@routes': path.resolve(__dirname, './src/routes'),
      '@styles': path.resolve(__dirname, './src/styles'),
      '@utils': path.resolve(__dirname, './src/utils'),
      '@views': path.resolve(__dirname, './src/views'),
      '@tests': path.resolve(__dirname, './src/tests'),
    },
  },
});
