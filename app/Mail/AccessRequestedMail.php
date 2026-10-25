<?php

namespace App\Mail;

use App\Models\AccessRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Someone asked to enroll in a guide. Always in Spanish, read by the owner: who, which guide, their
 * message, and their account's email to write to about payment — a reply goes straight to them —
 * with a link to the guide's Readers screen, where he approves it once they have paid.
 */
class AccessRequestedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public AccessRequest $accessRequest) {}

    public function build(): static
    {
        $request = $this->accessRequest->loadMissing(['user', 'guide']);

        return $this->subject('Arcadia · piden inscribirse: '.str($request->guide->title)->limit(60))
            ->replyTo($request->user->email, $request->user->name)
            ->view('mail.access-request', [
                'readersUrl' => route("{$request->guide->locale}.editor.readers.index", $request->guide->id),
            ]);
    }
}
