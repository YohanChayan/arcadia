<?php

namespace App\Actions;

use App\Enums\ActivityFamily;
use App\Services\ActivityImport;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;

/**
 * The owner's review of an imported activity, on a draft: its wording changed, and nothing else.
 * Only the family's text (`ActivityFamily::wording()`) is taken from what is sent, at the places
 * the activity already has — so a question cannot be added or dropped here, nor its correct option
 * moved, nor a flip card's key changed. What results must still be a valid activity of its family.
 */
class RewordActivity
{
    public function __construct(private readonly ActivityImport $import) {}

    /** @param  array<string, mixed>  $sent */
    public function handle(Model $node, array $sent): void
    {
        $activity = $node->activity;
        $family = ActivityFamily::from($activity['family']);

        $patterns = array_map(
            fn (string $path) => '/^'.str_replace('\*', '\d+', preg_quote($path, '/')).'$/',
            $family->wording(),
        );

        foreach (Arr::dot($activity) as $path => $value) {
            $wording = collect($patterns)->contains(fn (string $pattern) => preg_match($pattern, $path) === 1);
            // A field emptied arrives as null, and is then refused below as wording left blank.
            if ($wording && Arr::has($sent, $path) && (is_string($new = Arr::get($sent, $path)) || $new === null)) {
                Arr::set($activity, $path, trim((string) $new));
            }
        }

        $validator = Validator::make($activity, $this->import->rules($family, ''));
        $validator->after(function ($validator) use ($family, $activity) {
            foreach ($this->import->shapeProblems($family, $activity) as $field => $message) {
                $validator->errors()->add($field, $message);
            }
        });
        $validator->validate();

        $node->forceFill(['activity' => $activity])->save();
    }
}
