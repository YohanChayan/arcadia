<?php

return [
    'history' => 'Versions',
    'viewing_old' => 'You are looking at an earlier version. Only the latest is edited.',
    'latest' => 'Go to the latest',
    'release' => [
        'action' => 'Release this version',
        'title' => 'Release version :number?',
        'body' => 'From then on it is frozen: whoever receives it keeps it as it is. Only "fix in every version" can touch it afterwards.',
        'confirm' => 'Release',
    ],
    'released' => [
        'title' => 'Version :number released',
        'body' => 'This is the guide\'s link. Its readers open it with their Google account and see this version.',
    ],
    'discard' => [
        'action' => 'Discard draft',
        'title' => 'Discard the draft of version :number?',
        'body' => 'Its content and change log are deleted. Version :previous stays as it is.',
        'confirm' => 'Discard',
    ],
    'draft_next' => 'New version',
    'draft_next_hint' => 'Copies this version into a new draft to change it.',
    'changes' => [
        'title' => 'Changes in this version',
        'hint' => 'What changed since the previous one. It decides whether someone with an earlier version is offered this one.',
        'add' => 'Log a change',
        'empty' => 'No changes logged.',
        'fields' => ['kind' => 'Kind', 'title' => 'What changed', 'notes' => 'Notes'],
    ],
    'fix' => [
        'action' => 'Fix in every version',
        'title' => 'Fix in every version',
        'body' => '{1} This line is in 1 version.|[2,*] This line is in :count versions, released ones included. The fix reaches all of them.',
        'save' => 'Fix',
    ],
    'flash' => [
        'released' => 'Version :number released.',
        'released_and_published' => 'Version :number released, and the guide published with it: its readers can open it now.',
        'drafted' => 'Draft of version :number ready.',
        'discarded' => 'Draft of version :number discarded.',
        'fixed' => '{1} Fixed in 1 version.|[2,*] Fixed in :count versions.',
    ],
];
