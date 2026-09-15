import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            // CSS only — Alpine ships with Livewire 3, so no app.js entry is
            // needed for the standalone SPAR app.
            input: ['resources/css/app.css'],
            refresh: true,
        }),
    ],
});
