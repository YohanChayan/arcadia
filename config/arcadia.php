<?php

/*
|--------------------------------------------------------------------------
| Arcadia
|--------------------------------------------------------------------------
|
| One host, and the URL decides the language — `/es` and `/en`, each page under its own
| language's slug. Fortify's `/login` is the one page both languages share. Links, canonicals and
| the Google callback are built on `APP_URL` (`ArcadiaSite::url()`).
|
*/

return [

    // Where `/` and the sign-in go when this browser has not used Arcadia in a language yet.
    // Never the account's language nor the browser's: Arcadia's market speaks Spanish.
    'default_locale' => 'es',

    'locales' => [
        'es' => [
            // A guide's link: /guia/{link token}.
            'guide_path' => 'guia',
            // The guides anyone may ask for, listed: /es/guias.
            'catalogue_path' => 'guias',
            'privacy_path' => 'privacidad',
            // Where the landing page's popup posts a request for a guide.
            'inquiry_path' => 'pedir',
            // A reader's own guides, under the language: /es/mis-guias.
            'reader_path' => 'mis-guias',
            // A request to read a guide not shared with the account: /guia/{token}/acceso.
            'access_path' => 'acceso',
            // An account at its device limit chooses here: /es/dispositivos.
            'devices_path' => 'dispositivos',
            // How Arcadia works, and who to write to, for a signed-in reader: /es/ayuda.
            'help_path' => 'ayuda',
        ],
        'en' => [
            'guide_path' => 'guide',
            'catalogue_path' => 'guides',
            'privacy_path' => 'privacy',
            'inquiry_path' => 'ask',
            'reader_path' => 'my-guides',
            'access_path' => 'access',
            'devices_path' => 'devices',
            'help_path' => 'help',
        ],
    ],

    // Given to readers in the privacy note and the request popup. `?:`, not env()'s default:
    // `.env.example` ships the key empty, and an empty key would hide the address.
    'contact_email' => env('ARCADIA_CONTACT_EMAIL') ?: 'yhndevside@gmail.com',

    // How many devices one account may read on, unless its membership says otherwise
    // (option D). Three: a phone, a laptop, and WhatsApp's own browser on that phone,
    // which keeps cookies apart from the phone's. `?:` for the reason above.
    'device_limit' => (int) (env('ARCADIA_DEVICE_LIMIT') ?: 3),

];
