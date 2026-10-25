<?php

namespace App\Services;

use App\Enums\CardKind;
use App\Enums\ItemKind;
use App\Enums\Level;
use App\Enums\SectionKind;
use App\Models\Guide;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * An AI's answer, read and checked before anything is written: the JSON the prompt's output
 * contract asks for — sections, stages → cards → items, a title, level, goals, a greeting and
 * the curator's notes — in the guide tables' own field names.
 *
 * Forgiving about how an AI wraps it — a ```json fence, a sentence before or after — and strict
 * about what is inside: every problem is reported at its place in the guide ("Etapa 2 ·
 * tarjeta 1 · ítem 3 · url"), so the owner can fix it in the AI's answer or ask the AI again.
 */
class GuideImport
{
    /**
     * The JSON object in what was pasted, or null when there is none to be found.
     *
     * @return array<string, mixed>|null
     */
    public function extract(string $raw): ?array
    {
        $text = trim($raw);
        $start = strpos($text, '{');
        $end = strrpos($text, '}');

        if ($start === false || $end === false || $end < $start) {
            return null;
        }

        $data = json_decode(substr($text, $start, $end - $start + 1), true);

        return is_array($data) && ! array_is_list($data) ? $data : null;
    }

    /**
     * Every problem with the answer, each with where it is.
     *
     * @param  array<string, mixed>  $data
     * @return list<array{place: string, message: string}>
     */
    public function problems(array $data): array
    {
        $validator = Validator::make($data, $this->rules());

        // A message names the field, not its path: the place says where it is.
        $validator->setImplicitAttributesFormatter(fn (string $attribute) => Arr::last(explode('.', $attribute)));

        $validator->after(function ($validator) use ($data) {
            $links = array_map(fn (ItemKind $kind) => $kind->value, array_filter(ItemKind::cases(), fn (ItemKind $kind) => $kind->isLink()));

            foreach ($data['stages'] ?? [] as $s => $stage) {
                foreach ((array) ($stage['cards'] ?? []) as $c => $card) {
                    foreach ((array) ($card['items'] ?? []) as $i => $item) {
                        if (in_array($item['kind'] ?? null, $links, true) && trim((string) ($item['url'] ?? '')) === '') {
                            $validator->errors()->add("stages.{$s}.cards.{$c}.items.{$i}.url", __('arcadia.editor.import.errors.link_needs_url'));
                        }
                    }
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
     * What importing would write, to confirm before it is written.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function summary(array $data): array
    {
        $cards = collect($data['stages'])->flatMap(fn ($stage) => $stage['cards']);
        $items = $cards->flatMap(fn ($card) => $card['items']);

        return [
            'title' => $data['title'] ?? null,
            'level' => isset($data['level']) ? __("arcadia.levels.{$data['level']}") : null,
            'stages' => count($data['stages']),
            'cards' => $cards->count(),
            'items' => $items->count(),
            'links' => $items->filter(fn ($item) => ItemKind::from($item['kind'])->isLink())->count(),
            // Links that came with a brief: those can be replaced by what they taught.
            'briefs' => $items->filter(fn ($item) => ItemKind::from($item['kind'])->isLink() && filled($item['brief']['summary'] ?? null))->count(),
            'challenges' => $cards->where('kind', CardKind::CHALLENGE->value)->count(),
            'sections' => count($data['sections'] ?? []),
            // The answer's goals as they will be stored: its own lines, nothing to match.
            'goals' => Guide::goalLines($data['goals'] ?? []),
            'greeting' => filled($data['greeting'] ?? null),
            'curator_notes' => filled($data['curator_notes'] ?? null),
        ];
    }

    /** @return array<string, mixed> */
    private function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:160'],
            'level' => ['nullable', Rule::enum(Level::class)],
            'goals' => ['nullable', 'array'],
            'goals.*' => ['string', 'max:160'],
            'greeting' => ['nullable', 'string', 'max:2000'],
            'curator_notes' => ['nullable', 'array'],

            'sections' => ['nullable', 'array'],
            'sections.*.kind' => ['required', Rule::enum(SectionKind::class)],
            'sections.*.title' => ['nullable', 'string', 'max:255'],
            'sections.*.body' => ['required', 'string', 'max:20000'],

            'stages' => ['required', 'array', 'min:1'],
            'stages.*.title' => ['required', 'string', 'max:255'],
            'stages.*.goal' => ['nullable', 'string', 'max:255'],
            'stages.*.cards' => ['required', 'array', 'min:1'],
            'stages.*.cards.*.kind' => ['required', Rule::enum(CardKind::class)],
            'stages.*.cards.*.byline' => ['nullable', 'string', 'max:255'],
            'stages.*.cards.*.description' => ['nullable', 'string', 'max:5000'],
            'stages.*.cards.*.quote' => ['nullable', 'string', 'max:5000'],
            'stages.*.cards.*.closing_note' => ['nullable', 'string', 'max:5000'],
            'stages.*.cards.*.items' => ['required', 'array', 'min:1'],
            'stages.*.cards.*.items.*.ref' => ['required', 'string', 'max:64', 'distinct'],
            'stages.*.cards.*.items.*.kind' => ['required', Rule::enum(ItemKind::class)],
            'stages.*.cards.*.items.*.title' => ['required', 'string', 'max:255'],
            'stages.*.cards.*.items.*.url' => ['nullable', 'string', 'max:2048', 'url:http,https'],
            'stages.*.cards.*.items.*.hint' => ['nullable', 'string', 'max:255'],
            'stages.*.cards.*.items.*.body' => ['nullable', 'string', 'max:10000'],
            // What a link teaches, for finding another if it goes. Optional: older answers have none.
            'stages.*.cards.*.items.*.brief' => ['nullable', 'array'],
            'stages.*.cards.*.items.*.brief.summary' => ['nullable', 'string', 'max:2000'],
            'stages.*.cards.*.items.*.brief.covers' => ['nullable', 'array', 'max:20'],
            'stages.*.cards.*.items.*.brief.covers.*' => ['string', 'max:160'],
            'stages.*.cards.*.items.*.brief.level' => ['nullable', 'string', 'max:60'],
            'stages.*.cards.*.items.*.brief.creator' => ['nullable', 'string', 'max:160'],
            'stages.*.cards.*.items.*.brief.duration' => ['nullable', 'string', 'max:60'],
            'stages.*.cards.*.items.*.brief.language' => ['nullable', 'string', 'max:20'],
            'stages.*.cards.*.items.*.brief.search' => ['nullable', 'string', 'max:255'],
            // The part of a video to play. Kept only with evidence and a readable start (`VideoClip`).
            'stages.*.cards.*.items.*.segment' => ['nullable', 'array'],
            'stages.*.cards.*.items.*.segment.evidence' => ['nullable', 'string', 'max:'.VideoClip::EVIDENCE_MAX],
        ];
    }

    /** `stages.1.cards.0.items.2.url` → "Etapa 2 · tarjeta 1 · ítem 3 · url". */
    private function place(string $key): string
    {
        $parts = [];
        $segments = explode('.', $key);

        for ($i = 0; $i < count($segments); $i++) {
            $segment = $segments[$i];
            $next = $segments[$i + 1] ?? null;

            if (in_array($segment, ['stages', 'cards', 'items', 'sections'], true) && is_numeric($next)) {
                $parts[] = __("arcadia.editor.import.places.{$segment}", ['number' => (int) $next + 1]);
                $i++;
            } else {
                $parts[] = $segment;
            }
        }

        return implode(' · ', $parts);
    }
}
