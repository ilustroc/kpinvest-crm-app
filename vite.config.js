import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
  plugins: [
    laravel({
      input: [
        'resources/css/app.css',
        'resources/js/app.js',

        'resources/css/clientes/show.css',
        'resources/js/clientes/show/index.js',

        'resources/css/auth/login.css',
        'resources/js/auth/login.js',

        'resources/css/dashboard-stats.css',
        'resources/js/dashboard-stats.js',

        'resources/css/auth/autorizacion.css',
        'resources/js/auth/autorizacion.js',

        'resources/css/panel/resumen.css',
        'resources/js/panel/resumen.js',

        'resources/css/clientes/show.css',
        'resources/js/clientes/show.js',
      ],
      refresh: true,
    }),
  ],
});