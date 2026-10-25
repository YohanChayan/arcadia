<?php

namespace App\Mail;

use App\Models\Inquiry;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Someone asked for a guide. Always in Spanish, read by the owner, and carrying the whole
 * request with a link to answer it — so it can be answered from the inbox.
 */
class InquiryReceivedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Inquiry $inquiry) {}

    public function build(): static
    {
        $mail = $this->subject('Arcadia · piden una guía: '.str($this->inquiry->topic)->limit(60))
            ->view('mail.inquiry');

        // Answered by mail to the account that asked; an older request, to the email it typed.
        $email = $this->inquiry->user?->email
            ?? ($this->inquiry->contact_kind === Inquiry::EMAIL ? $this->inquiry->contact : null);

        return $email ? $mail->replyTo($email, $this->inquiry->requesterName()) : $mail;
    }
}
