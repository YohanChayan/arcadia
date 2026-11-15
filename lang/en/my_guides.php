<?php

// A signed-in reader's own guides (`pages/reader/Dashboard.vue`), and the colours they
// change there and on the guide page.
return [
    'meta' => ['title' => 'My guides · Arcadia'],
    'title' => 'My guides',
    'sign_out' => 'Sign out',
    'open' => 'Open guide',
    'preparing' => 'We are preparing it. We will let you know when it is ready.',
    'progress' => [
        'done' => ':done of :total steps done',
        'videos' => ':done/:total videos',
        'links' => ':done/:total links',
        'practices' => ':done/:total practices',
        'stages' => ':done/:total stages',
        'last' => 'Last ticked :date. Tick each video or link when you finish it to keep track.',
        'none' => 'Nothing ticked yet. Tick each video or link as done when you finish it: that is how your progress is kept.',
    ],
    // The line to do next (`GuideProgressSummary` `next`): after a first tick, and before it.
    'continue' => ['next' => 'Continue with', 'first' => 'Start with'],
    // Days in a row with a line ticked (`ReadingStreak`): the run, then how to keep or start it.
    'streak' => [
        'days' => '{1} :count day in a row|[2,*] :count days in a row',
        'keep' => 'Tick one line today to keep it going.',
        'start' => 'Tick one line today to start your streak.',
    ],
    'empty' => ['title' => 'No guides yet', 'hint' => 'When a guide is shared with you, it will appear here.'],
    'colours' => [
        'action' => 'Colours',
        'title' => 'Your colours',
        'hint' => 'Only you see the guide in these colours.',
        'save' => 'Save',
        'cancel' => 'Cancel',
    ],
    'flash' => ['saved' => 'Colours saved.'],
];
