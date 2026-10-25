<?php

namespace App\Jobs;

use App\Enums\QueueName;
use App\Mail\InquiryReceivedMail;
use App\Models\Inquiry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

/**
 * Tells the owner someone asked for a guide — to the address internal notifications land at.
 */
class SendInquiryNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(protected Inquiry $inquiry)
    {
        $this->onQueue(QueueName::ARCADIA->value);
    }

    public function handle(): void
    {
        Mail::to(config('mail.business_address'))->send(new InquiryReceivedMail($this->inquiry));
    }
}
