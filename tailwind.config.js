import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                zapmed: {
                    50: '#f3ffe6',
                    100: '#e4ffc9',
                    200: '#cbff99',
                    300: '#a6f55c',
                    400: '#84e62e',
                    500: '#64cc0f',
                    600: '#64cc0f',
                    700: '#4ba307',
                    800: '#31620f',
                    900: '#2a5311',
                    950: '#132e03',
                },
                sidebar: {
                    DEFAULT: '#1e293b',
                    hover: '#334155',
                    active: '#0f172a',
                },
            },
        },
    },

    plugins: [forms],
};
