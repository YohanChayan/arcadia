<?php

namespace App\Assistant\Contracts;

use App\Assistant\Models\Turn;
use App\Assistant\TurnMetrics;

/**
 * Where a conversation happens — the web over server-sent events today, Telegram and WhatsApp
 * later. The orchestrator owns the turn and knows no channel; it tells the channel what happened,
 * in this order: the turn started, the reply grew (any number of times), then the turn completed
 * or failed. A channel that cannot stream simply sends the reply when it completes.
 */
interface Channel
{
    /** The channel's name, recorded on every conversation: `web`, `console`. */
    public function name(): string;

    public function turnStarted(Turn $turn): void;

    public function replyDelta(string $text): void;

    public function turnCompleted(TurnMetrics $metrics): void;

    public function turnFailed(string $message): void;
}
