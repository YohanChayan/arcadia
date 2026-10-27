<?php

namespace App\Services;

use App\Enums\ActivityFamily;
use App\Models\Version;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * An AI's answer to one of the activity prompts, checked against a guide's draft before anything
 * is written: `{ "family": …, "activities": [{ "key": …, …the family's fields }] }`, where each
 * `key` is a node the draft already has, of the kind that family belongs on (`ActivityFamily`).
 *
 * Its own check, not the content import's: that one mints keys and replaces a draft, and this one
 * only attaches to what is there. It borrows nothing from it but `GuideImport::extract()`, which
 * finds the JSON however an AI wraps it. Every problem is reported at its place ("Actividad 2 ·
 * pregunta 1 · options"), as the content import reports them.
 */
class ActivityImport
{
    public function __construct(private readonly GuideImport $guideImport) {}

    /** @return array<string, mixed>|null */
    public function extract(string $raw): ?array
    {
        return $this->guideImport->extract($raw);
    }

    /**
     * Every problem with the answer against this draft, each with where it is.
     *
     * @param  array<string, mixed>  $data
     * @return list<array{place: string, message: string}>
     */
    public function problems(array $data, Version $draft): array
    {
        $family = ActivityFamily::tryFrom((string) ($data['family'] ?? ''));

        if (! $family) {
            return [['place' => 'family', 'message' => __('arcadia.editor.activity_import.errors.family')]];
        }

        $validator = Validator::make($data, ['activities' => ['required', 'array', 'list', 'min:1'], 'activities.*' => ['array'], 'activities.*.key' => ['required', 'string'], ...$this->rules($family)]);
        $validator->setImplicitAttributesFormatter(fn (string $attribute) => Arr::last(explode('.', $attribute)));

        $validator->after(function ($validator) use ($data, $family, $draft) {
            $nodes = $this->nodes($family, $draft);
            $seen = [];

            foreach ((array) ($data['activities'] ?? []) as $a => $activity) {
                if (! is_array($activity) || ! is_string($key = $activity['key'] ?? null)) {
                    continue;
                }

                $node = $nodes->firstWhere('key', $key);

                // The node must be in this draft, and of the kind the family belongs on.
                if (! $node || ! $family->belongsOn($node)) {
                    $validator->errors()->add("activities.{$a}.key", __('arcadia.editor.activity_import.errors.unknown_key', ['key' => $key]));
                } elseif (isset($seen[$key])) {
                    $validator->errors()->add("activities.{$a}.key", __('arcadia.editor.activity_import.errors.repeated_key', ['key' => $key]));
                }

                $seen[$key] = true;

                foreach ($this->shapeProblems($family, $activity) as $field => $message) {
                    $validator->errors()->add("activities.{$a}.{$field}", $message);
                }
            }
        });

        $problems = [];
        foreach ($validator->errors()->messages() as $key => $messages) {
            foreach ($messages as $message) {
                $problems[] = ['place' => $this->place($key), 'message' => $message];
            }
        }

        return $problems;
    }

    /**
     * What importing would do, to confirm before it is done: the family, the nodes it attaches
     * to, the entries it brings, and how many of the draft's activities of that family it replaces.
     *
     * @param  array<string, mixed>  $data
     * @return array{family: string, label: string, nodes: int, entries: int, replaces: int}
     */
    public function summary(array $data, Version $draft): array
    {
        $family = ActivityFamily::from($data['family']);
        $entries = collect($data['activities'])->sum(fn (array $activity) => max(1, ...array_map(
            fn (string $list) => count((array) ($activity[$list] ?? [])),
            ['questions', 'pairs', 'steps', 'cards'],
        )));

        return [
            'family' => $family->value,
            'label' => $family->label(),
            'nodes' => count($data['activities']),
            'entries' => $entries,
            'replaces' => $this->nodes($family, $draft)->filter(fn ($node) => ($node->activity['family'] ?? null) === $family->value)->count(),
        ];
    }

    /**
     * One activity's own fields, as an AI writes them — also what a reworded one must still be.
     *
     * @return array<string, mixed>
     */
    public function rules(ActivityFamily $family, string $prefix = 'activities.*.'): array
    {
        $text = fn (int $max) => ['required', 'string', 'max:'.$max];

        $rules = match ($family) {
            ActivityFamily::QUICK_CHECK => [
                'questions' => ['required', 'array', 'list', 'min:1', 'max:3'],
                'questions.*.prompt' => $text(300),
                'questions.*.options' => ['required', 'array', 'list', 'min:2', 'max:5'],
                'questions.*.options.*' => $text(200),
                'questions.*.correct' => ['required', 'integer', 'min:0'],
                'questions.*.explanation' => $text(500),
                'questions.*.evidence' => $text(500),
            ],
            ActivityFamily::STAGE_GAME => [
                'game' => ['required', Rule::in(['pairs', 'blanks', 'sequence'])],
                'instructions' => ['nullable', 'string', 'max:300'],
                'pairs' => ['exclude_if:'.$prefix.'game,sequence', 'required', 'array', 'list', 'min:3', 'max:8'],
                'pairs.*.left' => $text(200),
                'pairs.*.right' => $text(200),
                'steps' => ['exclude_unless:'.$prefix.'game,sequence', 'required', 'array', 'list', 'min:3', 'max:8'],
                'steps.*' => $text(200),
                'evidence' => ['nullable', 'string', 'max:500'],
            ],
            ActivityFamily::FLIP_CARDS => [
                'cards' => ['required', 'array', 'list', 'min:3', 'max:20'],
                'cards.*.front' => $text(300),
                'cards.*.back' => $text(300),
                'cards.*.evidence' => ['nullable', 'string', 'max:500'],
            ],
            ActivityFamily::PRACTICE_EXTRAS => [
                'hint' => $text(500),
                'example' => $text(1000),
                'rate' => ['sometimes', 'boolean'],
            ],
        };

        return collect($rules)->mapWithKeys(fn ($rule, $field) => [$prefix.$field => $rule])->all();
    }

    /**
     * What the rules cannot say: a correct option that exists, and a blank to fill in.
     *
     * @param  array<string, mixed>  $activity
     * @return array<string, string> by field path within the activity
     */
    public function shapeProblems(ActivityFamily $family, array $activity): array
    {
        $problems = [];

        if ($family === ActivityFamily::QUICK_CHECK) {
            foreach ((array) ($activity['questions'] ?? []) as $q => $question) {
                $correct = $question['correct'] ?? null;
                if (is_int($correct) && is_array($question['options'] ?? null) && $correct >= count($question['options'])) {
                    $problems["questions.{$q}.correct"] = __('arcadia.editor.activity_import.errors.correct');
                }
            }
        }

        if ($family === ActivityFamily::STAGE_GAME && ($activity['game'] ?? null) === 'blanks') {
            foreach ((array) ($activity['pairs'] ?? []) as $p => $pair) {
                if (is_string($pair['left'] ?? null) && ! str_contains($pair['left'], '___')) {
                    $problems["pairs.{$p}.left"] = __('arcadia.editor.activity_import.errors.blank');
                }
            }
        }

        return $problems;
    }

    /** @return Collection<int, Model> the draft's nodes of the family's kind */
    public function nodes(ActivityFamily $family, Version $draft): Collection
    {
        return $family->node()->model()::query()->where('version_id', $draft->id)->get();
    }

    private function place(string $key): string
    {
        $parts = [];
        $segments = explode('.', $key);
        $counted = ['activities', 'questions', 'options', 'pairs', 'steps', 'cards'];

        for ($i = 0; $i < count($segments); $i++) {
            $segment = $segments[$i];
            $next = $segments[$i + 1] ?? null;

            if (in_array($segment, $counted, true) && is_numeric($next)) {
                $parts[] = __("arcadia.editor.activity_import.places.{$segment}", ['number' => (int) $next + 1]);
                $i++;
            } else {
                $parts[] = $segment;
            }
        }

        return implode(' · ', $parts);
    }
}
