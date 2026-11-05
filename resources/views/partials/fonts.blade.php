{{--
| ZAPMED WEB FONTS — single source of truth for @font-face loading.
|
| The moodboard brand fonts:
|   • Manrope   — body text (all weights)
|   • Prata     — emphasis / display headings
|   • Comfortaa — the "zapmed." wordmark ONLY
|
| To change a font site-wide: swap the family loaded here, then update the
| matching --font-* variable in resources/css/app.css. Nothing else needs to
| change — every layout @includes this one partial.
--}}
<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=manrope:400,500,600,700,800|prata:400|comfortaa:700&display=swap" rel="stylesheet" />
