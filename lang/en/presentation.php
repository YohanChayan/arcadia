<?php

// A signed-in account at a guide it may not read (`pages/GuidePresentation.vue`).
return [
    'preview' => 'Preview: this is what the link shows someone who may not read the guide. Nothing here is sent.',
    'back' => 'Arcadia',
    'goals' => 'What you will be able to do',
    'sign_in' => [
        'title' => 'Sign in to enroll',
        'body' => 'Sign in with your Google account to ask for this guide. If it is already yours, signing in opens it.',
        'action' => 'Sign in',
    ],
    'open' => [
        'title' => 'Ask to enroll',
        'body' => 'Send your request and we will write to you by email to arrange it. You can read the guide as soon as it is approved.',
        'message' => 'Message',
        'message_placeholder' => 'Anything you want to tell us — optional',
        'action' => 'Ask to enroll',
        'hint' => 'We will write to you at this account\'s email.',
    ],
    'pending' => ['title' => 'Request sent', 'body' => 'We will write to you by email, and let you know when you can read the guide.'],
    'closed' => ['title' => 'This guide is not available for enrollment', 'body' => 'This guide does not take enrollment requests. If it was shared with you, sign in with the account it was sent to.'],
    'removed' => ['title' => 'This guide is no longer available to you', 'body' => 'You no longer have access to this guide. Your account is still active.'],
    'declined' => ['title' => 'You do not have access to this guide', 'body' => 'Your request to enroll was not approved. Your account is still active.'],
    'signed_in_as' => 'Signed in as :email',
    'other_account' => 'Use another Google account',
    'my_guides' => 'My guides',
    'flash' => ['requested' => 'Request sent. We will write to you by email.'],
];
