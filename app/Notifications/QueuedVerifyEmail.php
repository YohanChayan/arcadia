<?php

namespace App\Notifications;

use App\Notifications\Concerns\ArcadiaAccountMail;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Laravel's verification email, sent through the queue.
 *
 * The stock notification is sent inside the registration request, so an SMTP failure — a
 * rejected login, a provider's sending limit — turned registration into a 500 after the
 * account had already been created. Queued, the visitor reaches the verify screen regardless,
 * and a failure lands in `failed_jobs`, where Site settings → Health shows it with a Retry.
 *
 * Its look, language and queue are Arcadia's (`ArcadiaAccountMail`).
 */
class QueuedVerifyEmail extends VerifyEmail implements ShouldQueue
{
    use ArcadiaAccountMail;
    use Queueable;

    public function toMail($notifiable): MailMessage
    {
        return $this->accountMail('verify', $this->verificationUrl($notifiable), [
            'minutes' => config('auth.verification.expire', 60),
        ]);
    }
}
