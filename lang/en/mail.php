<?php

/*
| The account emails — verify the address, reset the password — (`ArcadiaAccountMail`,
| view `mail/account`). `:site` is the brand's name: Arcadia.
*/

return [
    'account' => [
        'verify' => [
            'subject' => 'Confirm your email · :site',
            'heading' => 'Confirm your email',
            'body' => 'To finish creating your :site account, confirm that this email is yours.',
            'expires' => 'The link expires in :minutes minutes.',
            'action' => 'Confirm my email',
            'ignore' => 'If you did not create a :site account, ignore this email: nothing will happen.',
        ],
        'reset' => [
            'subject' => 'Choose a new password · :site',
            'heading' => 'Choose a new password',
            'body' => 'Someone asked to change the password of your :site account. If it was you, choose a new one here.',
            'expires' => 'The link expires in :minutes minutes.',
            'action' => 'Choose a password',
            'ignore' => 'If you did not ask for this, ignore this email: your password stays the same.',
        ],
        'fallback' => 'If the button does not work, paste this link into your browser:',
        'signature' => 'Warmly, the :site team',
    ],
    'guide_shared' => [
        'subject' => 'Your guide ":title" is ready · :site',
        'heading' => 'You have a new guide: :title',
        'heading_access' => 'You now have access to :title',
        'body' => 'The guide ":title" was shared with you on :site. It is ready whenever you want to start.',
        'body_access' => 'Your request was accepted: the guide ":title" is yours on :site.',
        'sign_in' => 'To open it, sign in with this address: :email. You can sign in with Google or create your :site account with it.',
        'hello' => 'Hi :name!',
        'action' => 'Open my guide',
        'help' => 'Questions, or was this not meant for you? Write to us at :contact.',
    ],
];
