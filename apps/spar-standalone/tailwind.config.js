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
                    DEFAULT: '#006B3F',
                    600: '#006B3F',
                    700: '#005a34',
                },
            },
        },
    },
    plugins: [forms],
};
