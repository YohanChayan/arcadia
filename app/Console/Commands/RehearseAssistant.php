<?php

namespace App\Console\Commands;

use App\Assistant\Channels\ConsoleChannel;
use App\Assistant\Models\Conversation;
use App\Assistant\Models\Turn;
use App\Assistant\Orchestrator;
use App\Assistant\Rehearsals;
use App\Assistant\TurnStatus;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Sleep;

use function Laravel\Prompts\text;

/**
 * Runs the assistant's fixed test conversations against the real model, in the terminal, and
 * checks each against what it must do: offer the right guide or none, record an acceptance when
 * there is one, and never put a link in a reply. With `--chat`, you are the visitor instead.
 *
 * What a link check cannot judge — tone, whether a decline is gracious, whether a video or a
 * creator was named in words — is read in the transcript it prints.
 */
#[Signature('arcadia:rehearse-assistant {rehearsal? : One rehearsal by name; all of them if omitted} {--locale=es} {--chat : Talk to the assistant yourself} {--pause=0 : Seconds to wait between turns, to stay under the provider\'s rate limit}')]
#[Description("Run the sales assistant's test conversations against the real model")]
class RehearseAssistant extends Command
{
    public function handle(Orchestrator $orchestrator, Rehearsals $rehearsals): int
    {
        $locale = (string) $this->option('locale');
        $channel = new ConsoleChannel($this->output);

        if ($this->option('chat')) {
            $conversation = $orchestrator->start($channel->name(), $locale);

            while (($message = text('Tú', hint: 'Vacío para terminar')) !== '') {
                $orchestrator->reply($conversation, $message, $channel);
            }

            return self::SUCCESS;
        }

        $all = $rehearsals->for($locale);
        $chosen = $this->argument('rehearsal');

        if (is_string($chosen) && ! isset($all[$chosen])) {
            $this->error("No rehearsal [{$chosen}]. There are: ".implode(', ', array_keys($all)).'.');

            return self::FAILURE;
        }

        $failures = [];

        foreach (is_string($chosen) ? [$chosen => $all[$chosen]] : $all as $name => $rehearsal) {
            $this->newLine();
            $this->info("── {$name}");
            $conversation = $orchestrator->start($channel->name(), $locale);

            foreach ($rehearsal['messages'] as $message) {
                $this->line("<fg=yellow>Visitante:</> {$message}");
                $orchestrator->reply($conversation, $message, $channel);
                Sleep::for((int) $this->option('pause'))->seconds();
            }

            foreach ($this->check($conversation, $rehearsal) as $failure) {
                $failures[] = "{$name}: {$failure}";
            }
        }

        $this->newLine();

        if ($failures === []) {
            $this->info('Every rehearsal held. Read the transcripts for tone and for anything named in words.');

            return self::SUCCESS;
        }

        foreach ($failures as $failure) {
            $this->error($failure);
        }

        return self::FAILURE;
    }

    /**
     * What a rehearsal got wrong; nothing when it held.
     *
     * @param  array{messages: list<string>, offers: string|null, converts: bool}  $rehearsal
     * @return list<string>
     */
    private function check(Conversation $conversation, array $rehearsal): array
    {
        $turns = $conversation->turns()->get();
        $failures = [];

        if ($turns->contains(fn (Turn $turn): bool => $turn->status !== TurnStatus::DONE)) {
            $failures[] = 'a turn failed';
        }

        $offeredTitles = $turns
            ->filter(fn (Turn $turn): bool => $turn->offered_guide_id !== null)
            ->map(fn (Turn $turn): string => collect($turn->passages)->firstWhere('guide_id', $turn->offered_guide_id)['guide_title'] ?? '')
            ->values();

        if ($rehearsal['offers'] === null && $offeredTitles->isNotEmpty()) {
            $failures[] = 'offered '.$offeredTitles->implode(', ').' where no guide fits';
        }

        if ($rehearsal['offers'] !== null && ! $offeredTitles->contains(fn (string $title): bool => str_contains($title, $rehearsal['offers']))) {
            $failures[] = "never offered a guide about «{$rehearsal['offers']}»";
        }

        $converted = $conversation->conversions()->exists();

        if ($converted !== $rehearsal['converts']) {
            $failures[] = $rehearsal['converts'] ? 'the acceptance was not recorded' : 'recorded a conversion nobody made';
        }

        if ($turns->contains(fn (Turn $turn): bool => preg_match('~https?://|www\.|youtu~i', (string) $turn->reply) === 1)) {
            $failures[] = 'a reply contains a link';
        }

        return $failures;
    }
}
