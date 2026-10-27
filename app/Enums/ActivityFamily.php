<?php

namespace App\Enums;

use App\Models\Item;
use App\Models\PromptTemplate;
use App\Models\Section;
use App\Models\Stage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

/**
 * The kinds of activity a guide's content can carry, each on its own kind of node, in that node's
 * `activity` JSON — written by the additive import, one prompt per family:
 *
 * - `QUICK_CHECK` on a video line: questions, each with `prompt`, `options`, the `correct` one's
 *   index, an `explanation` and the brief's words it rests on (`evidence`)
 * - `STAGE_GAME` on a stage: one `game` — `pairs` (match), `blanks` (fill in) or `sequence`
 *   (order the steps) — with `instructions` and its `pairs` or `steps`
 * - `FLIP_CARDS` on the "must master" section: `cards`, each with its own `key`, `front`, `back`
 * - `PRACTICE_EXTRAS` on a practice line: a `hint`, an `example` answer, and whether to `rate` it
 *
 * The page is an allowlist (`forPage()`): never `evidence`, which is for the owner, and never a
 * quick check's answer to a reader — the server checks it, since a right one earns the tick.
 */
enum ActivityFamily: string
{
    case QUICK_CHECK = 'quick_check';
    case STAGE_GAME = 'stage_game';
    case FLIP_CARDS = 'flip_cards';
    case PRACTICE_EXTRAS = 'practice_extras';

    /**
     * What a node's activity shows on the guide page: null for none, for a family this code does
     * not know, or for one on a node it does not belong on. `$withAnswers` for the owner and the
     * preview, whose page checks a quick check itself — there is no reader to tick for.
     *
     * @param  array<string, mixed>|null  $activity
     * @return array<string, mixed>|null
     */
    public static function forPage(?array $activity, Model $node, bool $withAnswers): ?array
    {
        $family = self::tryFrom((string) data_get($activity, 'family'));

        if (! $family?->belongsOn($node)) {
            return null;
        }

        return ['family' => $family->value, ...$family->visible($activity, $withAnswers)];
    }

    public function belongsOn(Model $node): bool
    {
        return match ($this) {
            self::QUICK_CHECK => $node instanceof Item && $node->kind === ItemKind::YOUTUBE_VIDEO,
            self::PRACTICE_EXTRAS => $node instanceof Item && $node->kind === ItemKind::PRACTICE,
            self::STAGE_GAME => $node instanceof Stage,
            self::FLIP_CARDS => $node instanceof Section && $node->kind === SectionKind::MUST_MASTER,
        };
    }

    /** The kind of node it is carried on. */
    public function node(): ContentNode
    {
        return match ($this) {
            self::QUICK_CHECK, self::PRACTICE_EXTRAS => ContentNode::ITEMS,
            self::STAGE_GAME => ContentNode::STAGES,
            self::FLIP_CARDS => ContentNode::SECTIONS,
        };
    }

    /**
     * Its text, the only part the owner rewords on review, as the path of each field within the
     * activity — `*` for each entry of a list.
     *
     * @return list<string>
     */
    public function wording(): array
    {
        return match ($this) {
            self::QUICK_CHECK => ['questions.*.prompt', 'questions.*.options.*', 'questions.*.explanation'],
            self::STAGE_GAME => ['instructions', 'pairs.*.left', 'pairs.*.right', 'steps.*'],
            self::FLIP_CARDS => ['cards.*.front', 'cards.*.back'],
            self::PRACTICE_EXTRAS => ['hint', 'example'],
        };
    }

    /** The prompt that writes it, by the template's name, or null while it has none. */
    public function template(): ?string
    {
        return match ($this) {
            self::QUICK_CHECK => PromptTemplate::QUICK_CHECK,
            self::FLIP_CARDS => PromptTemplate::FLIP_CARDS,
            default => null,
        };
    }

    /** The family a template writes, by its name: none for any other template. */
    public static function forTemplate(string $name): ?self
    {
        return collect(self::cases())->first(fn (self $family) => $family->template() === $name);
    }

    public function label(): string
    {
        return __("arcadia.editor.activities.families.{$this->value}");
    }

    /**
     * @param  array<string, mixed>  $activity
     * @return array<string, mixed>
     */
    private function visible(array $activity, bool $withAnswers): array
    {
        $each = fn (string $list, array $fields) => array_map(
            fn ($entry) => is_array($entry) ? Arr::only($entry, $fields) : $entry,
            array_values((array) ($activity[$list] ?? [])),
        );

        return match ($this) {
            self::QUICK_CHECK => ['questions' => $each('questions', $withAnswers ? ['prompt', 'options', 'correct', 'explanation'] : ['prompt', 'options'])],
            self::STAGE_GAME => [
                ...Arr::only($activity, ['game', 'instructions']),
                'pairs' => $each('pairs', ['left', 'right']),
                // The steps in their right order, as text: the page shuffles them.
                'steps' => array_values(array_filter((array) ($activity['steps'] ?? []), 'is_string')),
            ],
            self::FLIP_CARDS => ['cards' => $each('cards', ['key', 'front', 'back'])],
            self::PRACTICE_EXTRAS => Arr::only($activity, ['hint', 'example', 'rate']),
        };
    }
}
