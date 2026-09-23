import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        // Standalone app's own Blade views + Livewire components.
        './resources/views/**/*.blade.php',
        './app/**/*.php',
        // CRITICAL: the spar-core PACKAGE ships most of the SPAR screens. Its
        // Blade views must be scanned too, or Tailwind's JIT purges every class
        // that only appears in the package and the SPAR UI renders unstyled.
        '../../packages/spar-core/resources/views/**/*.blade.php',
        '../../packages/spar-core/src/**/*.php',
    ],
    theme: {
        extend: {
            colors: {
                // SPAR brand green (matches config('spar.branding.primary_color')).
                spar: {
                    DEFAULT: '#038c46',
                    600: '#038c46',
                    700: '#027a3c',
                },
                // Remap the Tailwind `green` scale to the SPAR brand green so the
                // existing bg-green-600 / hover:bg-green-700 buttons across all
                // screens adopt the brand colour without editing every view.
                // 600 = base brand green, 700 = ~12% darker for hover/press.
                green: {
                    50: '#e9f7ef',
                    100: '#c9ecd6',
                    200: '#95d9ad',
                    300: '#5ec283',
                    400: '#2ea862',
                    500: '#0a9a50',
                    600: '#038c46',
                    700: '#027a3c',
                    800: '#026032',
                    900: '#014a28',
                },
            },
        },
    },
    plugins: [forms],
};
