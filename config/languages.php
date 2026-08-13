<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Available Languages
    |--------------------------------------------------------------------------
    |
    | All languages the platform supports. Each can be enabled/disabled from
    | the admin panel (stored in DB settings). Only enabled languages show
    | in the language picker.
    |
    | To add a new language:
    | 1. Add it here
    | 2. Create the translation file: lang/{code}.json
    | 3. Enable it in Admin → Settings → Languages
    |
    */

    'available' => [
        'en' => [
            'name' => 'English',
            'native' => 'English',
            'flag' => '🇬🇧',
            'rtl' => false,
        ],
        'af' => [
            'name' => 'Afrikaans',
            'native' => 'Afrikaans',
            'flag' => '🇿🇦',
            'rtl' => false,
        ],
        'zu' => [
            'name' => 'Zulu',
            'native' => 'isiZulu',
            'flag' => '🇿🇦',
            'rtl' => false,
        ],
        'st' => [
            'name' => 'Sotho',
            'native' => 'Sesotho',
            'flag' => '🇿🇦',
            'rtl' => false,
        ],
        'xh' => [
            'name' => 'Xhosa',
            'native' => 'isiXhosa',
            'flag' => '🇿🇦',
            'rtl' => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Language
    |--------------------------------------------------------------------------
    */
    'default' => 'en',

    /*
    |--------------------------------------------------------------------------
    | Fallback Language
    |--------------------------------------------------------------------------
    | If a translation key doesn't exist in the active language, fall back here.
    */
    'fallback' => 'en',

];
