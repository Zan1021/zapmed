{{--
| ZAPMED WEB FONTS — single source of truth for @font-face loading.
|
| The moodboard brand fonts (ALL self-hosted from /public/fonts — no external CDN):
|   • Manrope   — body text (weights 400/500/600/700/800)
|   • Prata     — emphasis / display headings (400)
|   • Comfortaa — the "zapmed." wordmark ONLY (700)
|
| Why self-hosted: the old bunny.net <link> pulled the actual font files from
| cdn.jsdelivr.net, which our Content-Security-Policy (font-src 'self'
| fonts.bunny.net fonts.gstatic.com) blocked — so the brand fonts never
| rendered and the console filled with CSP errors. Serving from 'self' fixes
| that, is faster, and needs no CSP change.
|
| To change / add a font site-wide:
|   1. drop the font file into public/fonts/
|   2. add an @font-face below
|   3. update the matching --font-* variable in resources/css/app.css
| Every layout @includes this one partial, so nothing else needs to change.
--}}
<style>
    /* Manrope — body */
    @font-face {
        font-family: 'Manrope';
        font-style: normal;
        font-weight: 400;
        font-display: swap;
        src: url('{{ asset('fonts/manrope-400.ttf') }}') format('truetype');
    }
    @font-face {
        font-family: 'Manrope';
        font-style: normal;
        font-weight: 500;
        font-display: swap;
        src: url('{{ asset('fonts/manrope-500.ttf') }}') format('truetype');
    }
    @font-face {
        font-family: 'Manrope';
        font-style: normal;
        font-weight: 600;
        font-display: swap;
        src: url('{{ asset('fonts/manrope-600.ttf') }}') format('truetype');
    }
    @font-face {
        font-family: 'Manrope';
        font-style: normal;
        font-weight: 700;
        font-display: swap;
        src: url('{{ asset('fonts/manrope-700.ttf') }}') format('truetype');
    }
    @font-face {
        font-family: 'Manrope';
        font-style: normal;
        font-weight: 800;
        font-display: swap;
        src: url('{{ asset('fonts/manrope-800.woff2') }}') format('woff2');
    }

    /* Prata — display / emphasis headings */
    @font-face {
        font-family: 'Prata';
        font-style: normal;
        font-weight: 400;
        font-display: swap;
        src: url('{{ asset('fonts/prata-400.ttf') }}') format('truetype');
    }

    /* Comfortaa — wordmark ONLY */
    @font-face {
        font-family: 'Comfortaa';
        font-style: normal;
        font-weight: 700;
        font-display: swap;
        src: url('{{ asset('fonts/comfortaa-700.woff2') }}') format('woff2');
    }
</style>
