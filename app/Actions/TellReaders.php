<?php

namespace App\Actions;

use App\Jobs\SendGuideSharedJob;
use App\Models\Guide;
use App\Models\Reader;
use Illuminate\Support\Facades\Bus;

/**
 * When a reader is told by email that a guide is theirs: at once if it can be opened, otherwise
 * the moment it can — never twice unless the owner resends, and never if the owner chose not to.
 *
 * Called on adding a reader and accepting a request (`reader()`), and wherever a guide may have
 * just become readable — its first release, or its status set to published (`waiting()`).
 */
class TellReaders
{
    /**
     * One reader, just added or let in. A guide that cannot be opened yet leaves them waiting.
     *
     * @return bool whether the email is on its way
     */
    public function reader(Reader $reader, bool $accessGranted = false): bool
    {
        if (! $reader->notify || $reader->notified_at !== null || ! $reader->guide->isReadable()) {
            return false;
        }

        $this->send(new SendGuideSharedJob($reader, $accessGranted));

        return true;
    }

    /** The owner's "send again". Only for a guide that can be opened. */
    public function again(Reader $reader): bool
    {
        if (! $reader->guide->isReadable()) {
            return false;
        }

        $this->send(new SendGuideSharedJob($reader, again: true));

        return true;
    }

    /** Everyone left waiting for this guide, once it can be opened. */
    public function waiting(Guide $guide): void
    {
        if (! $guide->isReadable()) {
            return;
        }

        $guide->readers()->where('notify', true)->whereNull('notified_at')->get()
            ->each(fn (Reader $reader) => $this->send(new SendGuideSharedJob($reader)));
    }

    /** Never an error page over an email: the reader is already assigned, and can be resent. */
    private function send(SendGuideSharedJob $job): void
    {
        rescue(fn () => Bus::dispatch($job));
    }
}
