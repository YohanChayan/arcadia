<?php

// The signed-in sidebar's own words — the same in every dashboard layout. Each layout's nav
// item labels stay with their surface (`admin.nav.items` for the admin's).
return [
    'collapse' => 'Contraer menú',
    'expand' => 'Abrir menú',
    'profile' => 'Perfil',
    'logout' => 'Cerrar sesión',
    'theme' => 'Tema',
    'themes' => [
        'dark' => 'Oscuro',
        'light' => 'Claro',
    ],
    'soon' => 'Pronto',
    'badges' => [
        'new' => 'Nuevo',
    ],
    // What an account with nothing in it yet sees on its home, instead of a blank page.
    'home' => [
        'account' => [
            'title' => 'Te damos la bienvenida, :name',
            'description' => 'Tu cuenta está lista. Cuando haya algo para ti — un proyecto, una reunión, una guía — aparecerá en esta página.',
        ],
        'unassigned' => [
            'title' => 'Te damos la bienvenida, :name',
            'description' => 'Tu cuenta está aprobada, pero todavía no tiene un área asignada. Cuando la tenga, aparecerá aquí.',
        ],
    ],
    'nav' => [
        'account' => 'Tu cuenta',
        'unassigned' => 'Tu cuenta',
        'unapproved' => 'Tu cuenta',
    ],
];
