<?php

namespace App\Jobs;

use App\Enums\QueueName;
use App\Mail\AccessRequestedMail;
use App\Models\AccessRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

/**
 * Tells the owner someone asked to enroll in a guide — to the address internal notifications land
 * at. Someone is waiting to pay; a count he must go and look at is not enough.
 */
class SendAccessRequestNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(protected AccessRequest $accessRequest)
    {
        $this->onQueue(QueueName::ARCADIA->value);
    }

    public function handle(): void
    {
        Mail::to(config('mail.business_address'))->send(new AccessRequestedMail($this->accessRequest));
    }
}
