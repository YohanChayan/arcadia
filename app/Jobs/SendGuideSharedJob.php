<?php

namespace App\Jobs;

use App\Enums\QueueName;
use App\Mail\GuideSharedMail;
use App\Models\Reader;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

/**
 * Tells a reader a guide is theirs (`GuideSharedMail`), on Arcadia's queue, and records when.
 *
 * Checked again when it runs, since a queue can be slow: a reader removed meanwhile, or a guide
 * that cannot be opened, gets nothing; nor does one already told, unless this is a resend.
 */
class SendGuideSharedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        protected Reader $reader,
        protected bool $accessGranted = false,
        protected bool $again = false,
    ) {
        $this->onQueue(QueueName::ARCADIA->value);
    }

    public function handle(): void
    {
        $reader = $this->reader->fresh();

        if (! $reader || $reader->trashed() || ! $reader->guide->isReadable()) {
            return;
        }

        if ($reader->notified_at !== null && ! $this->again) {
            return;
        }

        Mail::to($reader->email)->send(new GuideSharedMail($reader, $this->accessGranted));

        $reader->forceFill(['notified_at' => now()])->save();
    }
}
