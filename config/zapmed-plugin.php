<?php

return [

    /*
    |--------------------------------------------------------------------------
    | WordPress Plugin Version
    |--------------------------------------------------------------------------
    |
    | IMPORTANT: When you update the WP plugin code, bump this version number.
    | Partner WordPress sites check this to know if they need to update.
    |
    | CHECKLIST BEFORE BUMPING VERSION:
    | 1. Test plugin on a fresh WordPress install (latest WP + popular themes)
    | 2. Test with Elementor, Divi, and default theme (Twenty Twenty-Four)
    | 3. Verify shortcode renders correctly
    | 4. Verify Gutenberg block renders in editor and frontend
    | 5. Verify settings page works
    | 6. Create new ZIP file and place in storage/app/downloads/
    | 7. THEN bump this version number
    |
    | REMINDER: Captain Zan must test before pushing to partners!
    |
    */

    'version' => '1.0.0',

    /*
    |--------------------------------------------------------------------------
    | Minimum WordPress Version
    |--------------------------------------------------------------------------
    */
    'requires_wp' => '5.8',
    'tested_wp' => '6.7',
    'requires_php' => '7.4',

];
