<?php

// A reader's help (`pages/reader/Help.vue`). `:limit` is the account's device limit.
return [
    'meta' => ['title' => 'Help · Arcadia'],
    'title' => 'Help',
    'intro' => 'How Arcadia works, in short. If something here does not answer it, write to us.',
    'items' => [
        'open' => ['title' => 'Opening a guide', 'body' => 'Every guide shared with you is under My guides. Open it from there, or from the link you were sent, signed in with the same account it was shared with.'],
        'progress' => ['title' => 'Your progress', 'body' => 'Tick each video, link or practice when you finish it. Your ticks are saved to your account, so you pick up where you left off on any device.'],
        'notes' => ['title' => 'Your notes', 'body' => 'Write notes on any step or stage of a guide. Only you see them.'],
        'colours' => ['title' => 'Your colours', 'body' => 'Choose the colours you read a guide in, from the guide or from My guides. Only you see them.'],
        'devices' => ['title' => 'Devices', 'body' => 'You can read on up to :limit devices. At the limit, you can swap one for a new one once every 30 days; past that, write to us.'],
        'account' => ['title' => 'Your account', 'body' => 'If a guide stops opening, or you want your account deleted, write to us from the email you sign in with.'],
    ],
    'contact' => [
        'title' => 'Contact support',
        'body' => 'Write to us and we will answer you as soon as we can.',
        'copy' => 'Copy email',
        'copied' => 'Copied',
    ],
];
