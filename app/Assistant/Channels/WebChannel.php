<?php

namespace App\Assistant\Channels;

use App\Assistant\Contracts\Channel;
use App\Assistant\Models\Turn;
use App\Assistant\TurnMetrics;

/**
 * The web chat's channel: each thing the orchestrator reports is written at once as a named
 * server-sent event — `turn`, `delta`, `done`, `error` — the four the chat component reads
 * (`resources/js/composables/useAssistantChat.ts`). Written inside a streamed response, flushed
 * after every event so a reply reaches the browser as it is written.
 */
class WebChannel implements Channel
{
    public function name(): string
    {
        return 'web';
    }

    public function turnStarted(Turn $turn): void
    {
        $this->send('turn', ['conversation_id' => $turn->conversation_id, 'turn_id' => $turn->id]);
    }

    public function replyDelta(string $text): void
    {
        $this->send('delta', ['text' => $text]);
    }

    public function turnCompleted(TurnMetrics $metrics): void
    {
        $this->send('done', $metrics->toArray());
    }

    public function turnFailed(string $message): void
    {
        $this->send('error', ['message' => $message]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function send(string $event, array $data): void
    {
        echo "event: {$event}\n";
        echo 'data: '.json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n\n";

        if (ob_get_level() > 0) {
            ob_flush();
        }

        flush();
    }
}
