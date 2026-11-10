<?php

namespace App\Assistant\Channels;

use App\Assistant\Contracts\Channel;
use App\Assistant\Models\Turn;
use App\Assistant\TurnMetrics;
use Illuminate\Console\OutputStyle;

/**
 * The assistant in a terminal: the reply is written as it streams, and each turn ends with a
 * line of what it decided and cost. The second channel after the web, and the proof that the
 * orchestrator knows none — `arcadia:rehearse-assistant` drives whole conversations through it.
 */
class ConsoleChannel implements Channel
{
    public ?TurnMetrics $lastMetrics = null;

    public ?string $lastFailure = null;

    public function __construct(private OutputStyle $output) {}

    public function name(): string
    {
        return 'console';
    }

    public function turnStarted(Turn $turn): void
    {
        $this->lastMetrics = null;
        $this->lastFailure = null;
        $this->output->write('<fg=cyan>Asistente:</> ');
    }

    public function replyDelta(string $text): void
    {
        $this->output->write($text);
    }

    public function turnCompleted(TurnMetrics $metrics): void
    {
        $this->lastMetrics = $metrics;
        $usage = $metrics->usage;

        $this->output->newLine();
        $this->output->writeln(sprintf(
            '<fg=gray>  engagement %d · %d passages · %d in (%s cached) · %d out · %s%s</>',
            $metrics->engagement,
            $metrics->passages->count(),
            $usage->inputTokens,
            $usage->cacheReadInputTokens ?? 'n/a',
            $usage->outputTokens,
            $metrics->cost === null ? 'no price' : sprintf('$%.6f', $metrics->cost),
            $metrics->conversion === null ? '' : " · CONVERSION: {$metrics->conversion['guide_title']}",
        ));
    }

    public function turnFailed(string $message): void
    {
        $this->lastFailure = $message;
        $this->output->newLine();
        $this->output->writeln("<fg=red>  {$message}</>");
    }
}
