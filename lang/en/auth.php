<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines are used during authentication for various
    | messages that we need to display to the user. You are free to modify
    | these language lines according to your application's requirements.
    |
    */

    'failed' => 'These credentials do not match our records.',
    'password' => 'The provided password is incorrect.',
    'throttle' => 'Too many login attempts. Please try again in :seconds seconds.',

    // The sign-in and register screens (`AuthFrame.vue`).
    'meta' => ['title' => 'Sign in · Arcadia'],
    'back' => 'Back to home',
    'heading' => 'Sign in',
    'lead' => 'Sign in with the Google account your guide was shared with.',
    'fields' => ['name' => 'Your name', 'email' => 'Email', 'password' => 'Password', 'password_confirmation' => 'Repeat the password', 'remember' => 'Remember me'],
    'submit' => 'Sign in',
    'submitting' => 'Signing in…',
    'owner' => 'Sign in with email and password',
    'forgot' => 'Forgot your password?',
    'no_account' => 'No account?',
    'create_account' => 'Create one',
    // The other account screens on Arcadia (`Site::authPage()`), since 2026-10-15.
    'screens' => [
        'register' => [
            'title' => 'Create an account',
            'lead' => 'With your email and a password, if you would rather not use Google.',
            'email_hint' => 'Use the email your guide was shared with. We will send you a link to confirm it.',
            'submit' => 'Create account',
            'have_account' => 'Already have an account?',
        ],
        'verify-email' => [
            'title' => 'Check your email',
            'body' => 'We sent a link to :email. Open it to confirm your email and see your guides.',
            'sent' => 'We sent you a new link.',
            'resend' => 'Send the link again',
            'other' => 'Use another account',
        ],
        'forgot-password' => [
            'title' => 'Recover your password',
            'lead' => 'Enter your email and we will send you a link to choose a new one.',
            'submit' => 'Send link',
        ],
        'reset-password' => [
            'title' => 'Choose a new password',
            'submit' => 'Save password',
        ],
    ],
    'google' => [
        'continue' => 'Continue with Google',
        'hint' => 'No forms, no passwords. Just pick your account.',
        'unavailable' => 'Signing in with Google is not available right now. Try again in a moment.',
        'failed' => 'Signing in with Google could not be completed. Please try again.',
        'not_shared' => 'The account :email has no guides on Arcadia. If your guide was shared with another email, sign in with that account.',
    ],
    // A passkey: only where the browser supports one and `PASSKEYS_ENABLED` is on.
    'passkey' => [
        'sign_in' => 'Sign in with a passkey',
        'signing_in' => 'Checking…',
        'or' => 'or',
    ],
    // Shown inside an app's own browser (WhatsApp, Instagram…), where Google may refuse to sign in.
    'in_app' => [
        'title' => 'Open it in your browser',
        'body' => 'You are inside another app, and Google may not let you sign in from here. Open this page in Chrome or Safari.',
        'open' => 'Open in Chrome',
        'copy' => 'Copy link',
        'copied' => 'Link copied: paste it into your browser.',
    ],
];
