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

    // Login and Register page translations
    'login' => [
        'badge' => 'Secure Access',
        'title' => [
            'line_1' => 'Welcome to',
            'highlight' => 'Yhn Dev Side',
        ],
        'description' => 'Enter your credentials to access your dashboard',
        'fields' => [
            'email' => 'Email Address',
            'password' => 'Password',
        ],
        'placeholders' => [
            'email' => 'your@email.com',
            'password' => '••••••••',
        ],
        'submit' => 'Sign In',
        'submit_processing' => 'Signing in...',
        'links' => [
            'forgot_password' => 'Forgot your password?',
            'register' => 'Register',
        ],
        'footer' => 'Secure and encrypted connection',
        'oauth' => [
            'title' => 'Continue with',
            'google' => 'Continue with Google',
            'linkedin' => 'Continue with LinkedIn',
            'github' => 'Continue with GitHub',
            'divider' => 'OR',
        ],
        'toast' => [
            'success' => 'Welcome back, :name!',
            'error' => 'Login failed. Please check your credentials and try again.',
        ],
    ],

    'register' => [
        'badge' => 'Secure Registration',
        'title' => [
            'line_1' => 'Create your account on',
            'highlight' => 'Yhn Dev Side',
        ],
        'description' => 'Complete your details to create your account',
        'fields' => [
            'name' => 'Name',
            'email' => 'Email Address',
            'password' => 'Password',
            'password_confirmation' => 'Confirm Password',
        ],
        'placeholders' => [
            'name' => 'Your name',
            'email' => 'your@email.com',
            'password' => '••••••••',
            'password_confirmation' => '••••••••',
        ],
        'submit' => 'Create Account',
        'submit_processing' => 'Creating account...',
        'links' => [
            'has_account' => 'I already have an account',
        ],
        'footer' => 'Secure and encrypted connection',
        'toast' => [
            'success' => 'Welcome to YHN Dev Side, :name! Please check your email to verify your account.',
            'error' => 'Registration failed. Please correct the errors and try again.',
        ],
    ],

    'forgot_password' => [
        'badge' => 'Account Recovery',
        'title' => [
            'line_1' => 'Forgot your',
            'highlight' => 'Password?',
        ],
        'description' => 'No worries, we will send you a recovery link to your email',
        'fields' => [
            'email' => 'Email Address',
        ],
        'placeholders' => [
            'email' => 'your@email.com',
        ],
        'submit' => 'Send Link',
        'submit_processing' => 'Sending...',
        'links' => [
            'back_to_login' => 'Back to login',
        ],
        'footer' => 'You will receive an email with instructions to reset your password in a few minutes.',
        'toast' => [
            'success' => 'Password reset link sent! Please check your email inbox.',
            'error' => 'Failed to send reset link. Please verify your email address and try again.',
        ],
    ],

    'reset_password' => [
        'badge' => 'Reset Password',
        'title' => [
            'line_1' => 'New',
            'highlight' => 'Password',
        ],
        'description' => 'Create a new secure password for your account',
        'fields' => [
            'email' => 'Email Address',
            'password' => 'New Password',
            'password_confirmation' => 'Confirm Password',
        ],
        'placeholders' => [
            'email' => 'your@email.com',
            'password' => 'Minimum 8 characters',
            'password_confirmation' => 'Repeat your password',
        ],
        'submit' => 'Reset Password',
        'submit_processing' => 'Updating...',
        'footer' => 'Your password will be securely encrypted',
        'validation' => [
            'email_required' => 'Email is required',
            'password_min_length' => 'Password must be at least 8 characters',
            'password_mismatch' => 'Passwords do not match',
        ],
        'toast' => [
            'success' => 'Password reset successfully! You can now login with your new password.',
            'error' => 'Password reset failed. Please check the form and try again.',
        ],
    ],

    'two_factor' => [
        'badge' => 'Two-step verification',
        'title' => [
            'line_1' => 'One more step',
            'highlight' => 'to confirm it is you',
        ],
        'description' => 'Enter the six-digit code from your authenticator app.',
        'description_recovery' => 'Enter one of the recovery codes you saved when you set up two-step verification. Each code works once.',
        'fields' => [
            'code' => 'Authentication code',
            'recovery_code' => 'Recovery code',
        ],
        'placeholders' => [
            'code' => '123456',
            'recovery_code' => 'xxxxxxxx-xxxxxxxx',
        ],
        'submit' => 'Verify',
        'submit_processing' => 'Verifying...',
        'links' => [
            'use_recovery' => 'Use a recovery code instead',
            'use_code' => 'Use my authenticator app instead',
        ],
        'footer' => 'Codes expire every 30 seconds. If yours keeps failing, check your device clock.',
    ],

    'confirm_password' => [
        'badge' => 'Secure area',
        'title' => [
            'line_1' => 'Confirm your password',
            'highlight' => 'before continuing',
        ],
        'description' => 'This action changes your account security settings, so we ask for your password one more time.',
        'fields' => [
            'password' => 'Password',
        ],
        'placeholders' => [
            'password' => 'Your current password',
        ],
        'submit' => 'Confirm',
        'submit_processing' => 'Confirming...',
        'footer' => 'We will not ask again for a few hours.',
    ],
    'onboarding' => [
        'step_label' => 'Step :number',
        'steps' => [
            1 => [
                'title' => 'Email verification',
                'done' => 'Done — your email address is confirmed',
                'current' => 'Pending — confirm your email address',
                'future' => 'Confirm your email address',
            ],
            2 => [
                'title' => 'Administrator approval',
                'done' => 'Done — your account was approved',
                'current' => 'Pending — an administrator is reviewing your request',
                'future' => 'An administrator reviews your request',
            ],
            3 => [
                'title' => 'Dashboard access',
                'done' => 'Done — you have full access',
                'current' => 'Ready — sign in to continue',
                'future' => 'Available once your account is approved',
            ],
        ],
    ],

    'verify_email' => [
        'title' => 'Verify your email address',
        'resend' => 'Resend verification email',
        'resend_processing' => 'Sending...',
        'help' => [
            'title' => 'What you need to do',
            'items' => [
                'Check your inbox',
                'Look for an email from YHN Dev Side',
                'Open the verification link inside it',
                'If it is not there, check your spam folder',
            ],
            'expiry' => 'The link expires in 24 hours. Use the button above if you need a new one.',
        ],
        'toast' => [
            'sent' => 'Verification email sent again',
        ],
    ],

    'waiting_approval' => [
        'title' => 'Waiting for approval',
        'headline' => 'Your account is being reviewed',
        'body' => 'Your email address is confirmed. An administrator now reviews the request before the account is opened.',
        'notice' => 'You will get an email when the account is approved. This usually takes a few hours.',
    ],
];
