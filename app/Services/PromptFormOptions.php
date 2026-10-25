<?php

namespace App\Services;

use App\Enums\Level;
use App\Enums\PromptVariableType;
use App\Models\Category;
use App\Models\Guide;
use App\Models\PromptTemplate;
use App\Models\PromptVariable;
use App\Models\PromptVersion;
use App\Models\Setting;

/**
 * What a fill-in form offers, for a host's language: the choices of each variable type, a
 * template in every version with its variables, and what a guide's research prompt starts from.
 */
class PromptFormOptions
{
    /** @return array<string, list<array{value: string, label: string}>> */
    public function choices(string $locale): array
    {
        $terms = fn (string $model) => $model::query()->where('locale', $locale)->orderBy('name')->pluck('name')
            ->map(fn (string $name) => ['value' => $name, 'label' => $name])->all();

        return [
            PromptVariableType::LEVEL->value => array_map(fn (Level $level) => [
                'value' => $level->value,
                'label' => __("arcadia.levels.{$level->value}"),
            ], Level::cases()),
            PromptVariableType::LANGUAGE->value => array_map(fn (string $language) => [
                'value' => $language,
                'label' => __("arcadia.editor.prompts.languages.{$language}"),
            ], ['es', 'en']),
            PromptVariableType::CATEGORY->value => $terms(Category::class),
        ];
    }

    /**
     * The template of this name with every version, oldest first, as `VersionedPromptFiller` takes
     * one — or null where it is not seeded. The screen opens on the latest; the owner can pick another.
     *
     * @return array{id: int, name: string, versions: list<array<string, mixed>>}|null
     */
    public function template(string $name): ?array
    {
        $template = PromptTemplate::query()->where('name', $name)->with('versions.variables')->first();

        return $template ? [
            'id' => $template->id,
            'name' => $template->name,
            'versions' => $template->versions->map(fn (PromptVersion $version) => $this->version($version))->all(),
        ] : null;
    }

    /**
     * What a guide's research prompt starts filled with: the values it was last copied with, or —
     * never copied — what the guide and the request it answers already say, by the variable names
     * the research template uses.
     *
     * @return array<string, string>
     */
    public function researchValues(Guide $guide): array
    {
        if ($guide->prompt_values) {
            return $guide->prompt_values;
        }

        $inquiry = $guide->inquiry;

        return array_filter([
            'topic' => $inquiry?->topic ?? $guide->title,
            'purpose' => $inquiry?->purpose ? __('arcadia.landing.ask.purposes.'.$inquiry->purpose->value) : null,
            'learner_name' => $inquiry?->requesterName(),
            'level' => $guide->level->value,
            'goal' => $guide->goals[0] ?? null,
            'language' => $guide->locale,
        ], filled(...));
    }

    /**
     * A version as a fill-in form needs it, each variable with what its field starts filled with:
     * its own default, or — a price or a step length with none — what this language's settings say.
     *
     * @return array<string, mixed>
     */
    public function version(PromptVersion $version): array
    {
        $setting = null;

        return [
            'id' => $version->id,
            'number' => $version->number,
            'description' => $version->description,
            'frozen' => $version->isFrozen(),
            'variables' => $version->variables->map(fn (PromptVariable $variable) => [
                'name' => $variable->name,
                'label' => $variable->label,
                'type' => $variable->type->value,
                'required' => $variable->required,
                'default' => filled($variable->default_value) ? $variable->default_value : match ($variable->type) {
                    PromptVariableType::PRICE => $this->price($setting ??= Setting::forLocale(app()->getLocale())),
                    PromptVariableType::STEP_LENGTH => ($setting ??= Setting::forLocale(app()->getLocale()))->stepLengthLabel(),
                    default => $variable->default_value,
                },
            ])->all(),
        ];
    }

    /** `$3`, or `$3 BCV` with the host's note — as the landing page shows it; blank with no price. */
    private function price(Setting $setting): string
    {
        return $setting->hasPrice() ? trim($setting->priceLabel().' '.$setting->price_note) : '';
    }
}
