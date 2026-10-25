<?php

// The signed-in sidebar's own words — the same in every dashboard layout. Each layout's nav
// item labels stay with their surface (`admin.nav.items` for the admin's).
return [
    'collapse' => 'Collapse menu',
    'expand' => 'Open menu',
    'profile' => 'Profile',
    'logout' => 'Sign out',
    'theme' => 'Theme',
    'themes' => [
        'dark' => 'Dark',
        'light' => 'Light',
    ],
    'soon' => 'Soon',
    'badges' => [
        'new' => 'New',
    ],
    // What an account with nothing in it yet sees on its home, instead of a blank page.
    'home' => [
        'account' => [
            'title' => 'Welcome, :name',
            'description' => 'Your account is ready. When there is something for you here — a project, a meeting, a guide — it will appear on this page.',
        ],
        'unassigned' => [
            'title' => 'Welcome, :name',
            'description' => 'Your account is approved, but no area has been opened for it yet. When one is, it will appear here.',
        ],
    ],
    'nav' => [
        'account' => 'Your account',
        'unassigned' => 'Your account',
        'unapproved' => 'Your account',
    ],
];
