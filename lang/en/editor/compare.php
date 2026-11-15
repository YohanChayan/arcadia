<?php

return [
    'action' => 'Compare versions',
    'title' => 'Compare versions',
    'from' => 'From',
    'to' => 'To',
    'since_base' => 'Since version 1',
    'previous' => 'With the previous one',
    'show_unchanged' => 'Show what did not change',
    'no_changes' => 'These versions are identical.',
    'same_version' => 'Pick two different versions.',
    'levels' => ['sections' => 'Texts', 'stages' => 'Stages', 'cards' => 'Cards', 'items' => 'Items'],
    'status' => [
        'added' => 'added',
        'removed' => 'removed',
        'changed' => 'changed',
        'moved' => 'moved',
        'reordered' => 'reordered',
    ],
    'summary' => ':added added · :removed removed · :changed changed · :moved moved',
    'logged' => [
        'title' => 'What you logged',
        'hint' => 'The change logs of the versions compared. If the comparison shows something you did not log, check it before releasing.',
        'empty' => 'You logged no changes in these versions.',
    ],
    'computed' => 'What changed',
];
