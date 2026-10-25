<?php

namespace App\Services;

use App\Enums\PromptVariableType;
use App\Models\PromptSection;
use App\Models\PromptVariable;
use App\Models\PromptVersion;
use Illuminate\Support\Collection;

/**
 * A prompt version, filled: its sections in order, each `{{name}}` replaced by its value.
 *
 * The one renderer — behind the live preview, the copy, and what a request or a guide records —
 * so what the owner saw is what was copied. A choice renders as its words, not its stored value
 * (`basic` → "Básico", `es` → "español"). An optional blank left empty reads as a dash, so a
 * line like "Notes: {{notes}}" never ends in nothing; a required one left empty, and a
 * placeholder no variable declares, stay visible and are listed.
 *
 * Written `{{?name}}`, an optional blank left empty **takes its whole line with it** instead: for
 * a prompt where a dash would be drawn rather than read — an image's text, where the model put a
 * "—" beside an empty contact field. Nothing about the field reaches the model at all.
 *
 * Every fill-in form also offers "Instrucciones extra", whatever the template declares: a
 * one-off line for this research ("only sources in Spanish"), added at the end under its own
 * heading and kept with the other values (`EXTRA`), so a record still reproduces the exact text.
 */
class PromptRenderer
{
    private const PLACEHOLDER = '/\{\{\s*\??([a-z][a-z0-9_]*)\s*\}\}/';

    /** A placeholder that drops its line when empty: `{{?name}}`. */
    private const LINE_PLACEHOLDER = '/\{\{\s*\?([a-z][a-z0-9_]*)\s*\}\}/';

    private const EMPTY = '—';

    /** The value every form carries besides the declared ones. No variable may take its name. */
    public const EXTRA = 'extra_instructions';

    /**
     * @param  array<string, mixed>  $values  by variable name
     * @return array{prompt: string, missing: list<string>, undeclared: list<string>}
     */
    public function render(PromptVersion $version, array $values): array
    {
        $variables = $version->variables->keyBy('name');
        $missing = [];
        $undeclared = [];

        $prompt = $version->sections
            ->map(fn (PromptSection $section) => trim(($section->title ? "## {$section->title}\n\n" : '').$section->body))
            ->implode("\n\n");

        $extra = trim((string) ($values[self::EXTRA] ?? ''));
        if ($extra !== '') {
            $prompt .= "\n\n## ".__('arcadia.editor.prompts.fill.extra_heading')."\n\n".$extra;
        }

        $prompt = $this->dropEmptyLines($prompt, $variables, $values);

        $prompt = preg_replace_callback(self::PLACEHOLDER, function (array $match) use ($variables, $values, &$missing, &$undeclared) {
            $name = $match[1];
            $variable = $variables->get($name);

            if ($variable === null) {
                $undeclared[$name] = $name;

                return $match[0];
            }

            $value = trim((string) ($values[$name] ?? ''));

            if ($value === '') {
                if ($variable->required) {
                    $missing[$name] = $name;

                    return $match[0];
                }

                return self::EMPTY;
            }

            return $this->words($variable, $value);
        }, $prompt);

        // A required blank no section uses is still a blank the owner was asked for.
        foreach ($variables as $name => $variable) {
            if ($variable->required && trim((string) ($values[$name] ?? '')) === '') {
                $missing[$name] = $name;
            }
        }

        return ['prompt' => $prompt, 'missing' => array_values($missing), 'undeclared' => array_values($undeclared)];
    }

    /**
     * The placeholders a version's sections use.
     *
     * @return list<string>
     */
    public function placeholders(PromptVersion $version): array
    {
        preg_match_all(self::PLACEHOLDER, $version->sections->pluck('body')->implode("\n"), $matches);

        return array_values(array_unique($matches[1]));
    }

    /**
     * The prompt without each line whose `{{?name}}` is an optional blank left empty. A required
     * or undeclared one keeps its line, to be listed like any other.
     *
     * @param  Collection<string, PromptVariable>  $variables
     * @param  array<string, mixed>  $values
     */
    private function dropEmptyLines(string $prompt, $variables, array $values): string
    {
        $lines = array_filter(explode("\n", $prompt), function (string $line) use ($variables, $values) {
            preg_match_all(self::LINE_PLACEHOLDER, $line, $matches);

            foreach ($matches[1] as $name) {
                $variable = $variables->get($name);

                if ($variable !== null && ! $variable->required && trim((string) ($values[$name] ?? '')) === '') {
                    return false;
                }
            }

            return true;
        });

        return implode("\n", $lines);
    }

    private function words(PromptVariable $variable, string $value): string
    {
        return match ($variable->type) {
            PromptVariableType::LEVEL => __("arcadia.levels.{$value}"),
            PromptVariableType::LANGUAGE => __("arcadia.editor.prompts.languages.{$value}"),
            PromptVariableType::PHONE => $this->phone($value),
            default => $value,
        };
    }

    /**
     * A Venezuelan number grouped with dashes — `04128023555` → `0412-802-3555`, `+584128023555`
     * → `+58 412-802-3555` — whatever spaces or dashes it was typed with. Anything else stays as
     * typed: a number in another shape is the owner's to write.
     */
    private function phone(string $value): string
    {
        $digits = preg_replace('/\D/', '', $value);

        if (preg_match('/^0(\d{3})(\d{3})(\d{4})$/', $digits, $match) === 1) {
            return "0{$match[1]}-{$match[2]}-{$match[3]}";
        }

        if (str_starts_with(trim($value), '+') && preg_match('/^58(\d{3})(\d{3})(\d{4})$/', $digits, $match) === 1) {
            return "+58 {$match[1]}-{$match[2]}-{$match[3]}";
        }

        return $value;
    }
}
