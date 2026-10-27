<?php

namespace App\Http\Controllers\Reader;

use App\Enums\GuideStatus;
use App\Models\Item;
use App\Models\Reader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * A reader's answer to a video's quick check, checked here — the page is never sent the answers —
 * and, when every question is right, the line ticked (`Reader::tick()`): the one way a line with a
 * quick check is ticked. A wrong answer ticks nothing and says, for each question missed, why the
 * right option is right, so the reader can try again.
 *
 * Only their own assignment, a guide they can open, and a line of its released version that has
 * a quick check; anything else is a 404.
 */
class QuickCheckController
{
    public function __invoke(Request $request, Reader $reader): JsonResponse
    {
        abort_unless($reader->user_id === $request->user()->id, 404);
        abort_unless($reader->guide->locale === app()->getLocale(), 404);
        abort_unless($reader->guide->status === GuideStatus::PUBLISHED, 404);

        $version = $reader->guide->releasedVersion() ?? abort(404);

        $item = Item::query()->where('version_id', $version->id)->where('key', (string) $request->input('key'))->first();
        $questions = $item?->quickCheck()['questions'] ?? abort(404);

        $answers = $request->validate([
            'answers' => ['required', 'array', 'list', 'size:'.count($questions)],
            'answers.*' => ['required', 'integer', 'min:0'],
        ])['answers'];

        $results = array_map(fn (array $question, int $answer) => $answer === $question['correct']
            ? ['correct' => true]
            : ['correct' => false, 'explanation' => $question['explanation']], $questions, $answers);

        $passed = collect($results)->every('correct');

        if ($passed) {
            $reader->tick($item->key, true);
        }

        return response()->json(['passed' => $passed, 'results' => $results]);
    }
}
