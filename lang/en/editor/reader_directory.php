<?php

return [
    'title' => 'Readers',
    'description' => 'Everyone an English guide was given to, one per email. They are added and removed on each guide\'s readers.',
    'access' => [
        'label' => 'Filter by access',
        'all' => 'All',
        'account' => 'With account',
        'pending' => 'Pending',
        'pending_hint' => 'They can open a guide, invited by email or not, but have not signed in yet: they have not opened it.',
    ],
    'search' => 'Search by email or name',
    'search_submit' => 'Search',
    'columns' => ['reader' => 'Reader', 'account' => 'Account', 'guides' => 'Guides'],
    'account' => ['yes' => 'Has an account', 'no' => 'No account yet'],
    'invited' => 'invitation sent on :date',
    'not_invited' => 'no email invitation',
    'show' => 'View guides',
    'guides_count' => '{0} No guides|{1} 1 guide assigned|[2,*] :count guides assigned',
    'assigned' => 'Assigned on :date',
    'empty' => [
        'title' => 'No readers yet.',
        'hint' => 'Give a guide to someone by email from that guide\'s readers.',
        'search' => 'Nobody matches that search.',
    ],
];
