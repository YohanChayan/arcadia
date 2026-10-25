<?php

namespace App\Actions;

use App\Enums\GuideStatus;
use App\Enums\Level;
use App\Models\Category;
use App\Models\Guide;
use App\Models\Inquiry;
use Illuminate\Support\Str;

/**
 * A draft guide answering a request, titled by what they want to learn. Its research prompt is
 * prepared afterwards, on the guide's content screen (`GuidePromptController`).
 *
 * A request a prompt was copied for before 2026-10-22, when that step lived on the requests
 * screen, still hands it on: the level, goal and category it was filled with, when those name
 * terms of this language, and the version and values, so the guide can say what researched it.
 *
 * One guide per request: asked again, it returns the one already started.
 */
class StartGuideFromInquiry
{
    public function __construct(private readonly SaveGuide $save) {}

    public function handle(Inquiry $inquiry): Guide
    {
        if ($existing = $inquiry->guide) {
            return $existing;
        }

        $values = $inquiry->prompt_values ?? [];
        $term = fn (string $model, ?string $name) => $name
            ? $model::query()->where('locale', $inquiry->locale)->where('name', $name)->value('id')
            : null;

        $guide = $this->save->handle(
            guide: null,
            locale: $inquiry->locale,
            title: Str::limit(Str::ucfirst(trim($values['topic'] ?? '') ?: $inquiry->topic), 250, ''),
            level: Level::tryFrom($values['level'] ?? '') ?? Level::BASIC,
            categoryId: $term(Category::class, $values['category'] ?? null),
            status: GuideStatus::DRAFT,
            topicIds: [],
            // The prompt's goal, as written for this request: the guide's first line.
            goals: [$values['goal'] ?? null],
        );

        $guide->forceFill([
            'inquiry_id' => $inquiry->id,
            'prompt_version_id' => $inquiry->prompt_version_id,
            'prompt_values' => $inquiry->prompt_values,
        ])->save();

        return $guide;
    }
}
