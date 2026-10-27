<?php

namespace App\Http\Controllers\Reader;

use App\Enums\ActivityFamily;
use App\Enums\GuideStatus;
use App\Enums\SectionKind;
use App\Models\ActivityState;
use App\Models\Reader;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * A reader's answer to one flip card of a guide's "must master" deck: they knew it, or not yet.
 * Kept on their account by the card's own key (`ActivityState`), so the page brings back a card
 * left at "not yet" the next day, and counts the ones they know. Answering again replaces it.
 *
 * Only their own assignment, a guide they can open, and a card of its released version's deck;
 * anything else is a 404.
 */
class FlipCardController
{
    public function __invoke(Request $request, Reader $reader): Response
    {
        abort_unless($reader->user_id === $request->user()->id, 404);
        abort_unless($reader->guide->locale === app()->getLocale(), 404);
        abort_unless($reader->guide->status === GuideStatus::PUBLISHED, 404);

        $version = $reader->guide->releasedVersion() ?? abort(404);

        $data = $request->validate([
            'key' => ['required', 'string'],
            'known' => ['required', 'boolean'],
        ]);

        $deck = $version->sections()->where('kind', SectionKind::MUST_MASTER)->first() ?? abort(404);
        $cards = ActivityFamily::forPage($deck->activity, $deck, withAnswers: true)['cards'] ?? [];
        abort_unless(collect($cards)->contains('key', $data['key']), 404);

        ActivityState::updateOrCreate(
            ['user_id' => $reader->user_id, 'guide_id' => $reader->guide_id, 'family' => ActivityFamily::FLIP_CARDS->value, 'target_key' => $data['key']],
            ['state' => $request->boolean('known') ? ActivityState::KNOWN : ActivityState::NOT_YET],
        )->touch();

        return response()->noContent();
    }
}
