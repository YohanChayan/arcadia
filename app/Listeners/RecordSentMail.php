<?php

namespace App\Listeners;

use App\Models\SentMail;
use Illuminate\Mail\Events\MessageSent;
use Symfony\Component\Mime\Address;
use Throwable;

/**
 * Records every email the site sends, for Site settings → Mail: the framework fires `MessageSent`
 * for mailables, notifications and raw sends alike, so the five senders need no change. The body
 * is never stored.
 *
 * Runs inside the send, so the locale is the mail's own: mailables and notifications switch to it
 * while they are sent. A failure to record is reported and swallowed — the email has already
 * gone, and failing here would fail the job and send it again on retry.
 *
 * Rows older than `RETENTION_DAYS` are pruned as each new one is written: there is no scheduler.
 */
class RecordSentMail
{
    public const RETENTION_DAYS = 30;

    public function handle(MessageSent $event): void
    {
        try {
            $message = $event->message;

            SentMail::create([
                'sent_at' => now(),
                'recipient' => collect($message->getTo())->map(fn (Address $address) => $address->getAddress())->implode(', '),
                'subject' => $message->getSubject(),
                'mailable' => $event->data['__laravel_mailable'] ?? $event->data['__laravel_notification'] ?? null,
                'locale' => app()->getLocale(),
                'message_id' => $event->sent->getMessageId(),
            ]);

            SentMail::query()->where('sent_at', '<', now()->subDays(self::RETENTION_DAYS))->delete();
        } catch (Throwable $e) {
            report($e);
        }
    }
}
