import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/*
| COLOURS & FONTS ARE DEFINED ONCE, in resources/css/app.css (CSS variables).
| This file only MAPS Tailwind class names onto those variables. To re-colour
| or re-font the site, edit the tokens in app.css — not here, not the Blade.
|
| `rgb(var(--x) / <alpha-value>)` would need numeric channels; the moodboard
| tokens are hex, so we reference them directly. Tailwind opacity modifiers
| (e.g. bg-brand/85) still work on the hex values.
*/

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        // SPAR close-the-loop UI lives in the spar-core package, not the host
        // app's resources/views. Without this glob Tailwind's JIT never sees the
        // package Blade classes and purges them from the production build,
        // leaving the SPAR admin/patient screens partially unstyled (B7 finding).
        './packages/spar-core/resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['var(--font-body)', ...defaultTheme.fontFamily.sans],
                serif: ['var(--font-heading)', ...defaultTheme.fontFamily.serif],
                logo: ['var(--font-logo)', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                /* Brand anchor = Dark teal. Hover = the darker signature stop. */
                brand: {
                    DEFAULT: 'var(--color-dark-teal)',
                    hover: 'var(--color-teal-900)',
                },

                /* Semantic palette names (prefer these in NEW markup) */
                teal: {
                    50:  'var(--color-teal-50)',
                    100: 'var(--color-teal-100)',
                    200: 'var(--color-teal-200)',
                    300: 'var(--color-teal-300)',
                    400: 'var(--color-teal-400)',
                    500: 'var(--color-teal-500)',
                    600: 'var(--color-teal-600)',
                    700: 'var(--color-teal-700)',
                    800: 'var(--color-teal-800)',
                    900: 'var(--color-teal-900)',
                    950: 'var(--color-teal-950)',
                    DEFAULT: 'var(--color-primary-teal)',
                },
                ivory: 'var(--color-ivory)',

                /* Men's accents */
                navy:  'var(--color-navy)',
                steel: 'var(--color-steel)',
                dusty: 'var(--color-dusty)',

                /* Women's accents (sage scale: pale → soft → deep) */
                sage: {
                    100: 'var(--color-pale-sage)',
                    200: 'var(--color-pale-sage)',
                    300: 'var(--color-soft-sage)',
                    400: 'var(--color-soft-sage)',
                    500: 'var(--color-deep-sage)',
                    DEFAULT: 'var(--color-deep-sage)',
                },

                /* LEGACY alias — the frontend/admin still use `zapmed-*` classes
                   everywhere. Re-pointed from the old lime green onto the brand
                   teal ramp so existing markup adopts the new palette with no
                   Blade edits. New work should use `teal-*` instead. */
                zapmed: {
                    50:  'var(--color-teal-50)',
                    100: 'var(--color-teal-100)',
                    200: 'var(--color-teal-200)',
                    300: 'var(--color-teal-300)',
                    400: 'var(--color-teal-400)',
                    500: 'var(--color-teal-500)',
                    600: 'var(--color-teal-600)',
                    700: 'var(--color-teal-700)',
                    800: 'var(--color-teal-800)',
                    900: 'var(--color-teal-900)',
                    950: 'var(--color-teal-950)',
                },

                /* Admin sidebar — kept as a role, now tinted to the dark-teal brand
                   so the backend matches. Adjust these three tokens in app.css if
                   you want a different sidebar shade later. */
                sidebar: {
                    DEFAULT: 'var(--color-dark-teal)',
                    hover:   'var(--color-teal-700)',
                    active:  'var(--color-teal-950)',
                },
            },
            backgroundImage: {
                'brand-gradient':        'var(--gradient-signature)',
                'brand-gradient-dark':   'var(--gradient-signature-dark)',
                'soft-gradient':         'var(--gradient-soft-primary)',
                'mens-gradient':         'var(--gradient-mens)',
                'mens-gradient-deep':    'var(--gradient-mens-deep)',
                'womens-gradient':       'var(--gradient-womens)',
                'womens-gradient-deep':  'var(--gradient-womens-deep)',
            },
        },
    },

    plugins: [forms],
};
