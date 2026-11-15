<?php

return [
    'edit' => 'Colours',
    'default_title' => "The guide's colours",
    'default_hint' => 'What each new reader starts with. Changing them does not change readers it already has.',
    'reader_title' => "This reader's colours",
    'preview' => 'How they see it',
    'save' => 'Save colours',
    'by' => ['admin' => 'Me', 'reader' => 'Reader'],
    'overwrite_title' => 'This reader chose their own colours.',
    'overwrite_body' => 'Saving replaces their choice with yours.',
    'overwrite_confirm' => 'Replace their colours',
    'errors' => ['overwrite' => 'This reader chose their own colours: confirm that you want to replace them.'],
    'flash' => [
        'default' => "The guide's colours are saved. New readers start with them.",
        'reader' => 'Colours for :email saved.',
    ],
];
