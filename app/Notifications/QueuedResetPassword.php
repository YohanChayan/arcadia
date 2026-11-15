<?php

namespace App\Notifications;

use App\Notifications\Concerns\AccountMail;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Laravel's password-reset email, sent through the queue — for the same reason as
 * `QueuedVerifyEmail`: sent inline, a mail-server failure was a 500 on "forgot password".
 *
 * Its look, language and queue are Arcadia's (`AccountMail`).
 */
class QueuedResetPassword extends ResetPassword implements ShouldQueue
{
    use AccountMail;
    use Queueable;

    public function toMail($notifiable): MailMessage
    {
        return $this->accountMail('reset', $this->resetUrl($notifiable), [
            'minutes' => config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60),
        ]);
    }
}
