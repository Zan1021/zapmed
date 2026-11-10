import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/css/zapmed-home.css', 'resources/js/zapmed-home.js', 'resources/css/zapmed-onboarding.css'],
            refresh: true,
        }),
    ],
});
