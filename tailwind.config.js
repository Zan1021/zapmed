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
                sans: ['Plus Jakarta Sans', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                zapmed: {
                    50: '#f4fce6',
                    100: '#e6f9c8',
                    200: '#cdf396',
                    300: '#ade85a',
                    400: '#93db2a',
                    500: '#81cf00',
                    600: '#6ab300',
                    700: '#508a00',
                    800: '#406d07',
                    900: '#365c0b',
                    950: '#1a3300',
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
