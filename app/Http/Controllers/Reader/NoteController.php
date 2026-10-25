<?php

namespace App\Http\Controllers\Reader;

use App\Enums\GuideStatus;
use App\Models\Note;
use App\Models\Reader;
use App\Models\Version;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/**
 * A reader's own notes on a guide, written from the guide page: on a line or on a stage. JSON in
 * and out — the page keeps them in place, as it does ticks.
 *
 * Only their own assignment, a guide they can open, and a line or stage of its released version —
 * the same rule as a tick. A note is only ever its writer's: anyone else's is a 404.
 */
class NoteController
{
    public function store(Request $request, Reader $reader): JsonResponse
    {
        $version = $this->readable($request, $reader);

        $data = $request->validate([
            'target_kind' => ['required', Rule::in([Note::ITEM, Note::STAGE])],
            'target_key' => ['required', 'string', Rule::exists(
                $request->input('target_kind') === Note::STAGE ? 'guide_stages' : 'guide_items', 'key',
            )->where('version_id', $version->id)],
            'body' => ['required', 'string', 'max:'.Note::MAX_LENGTH],
        ]);

        $note = Note::create([
            ...$data,
            'body' => trim($data['body']),
            'user_id' => $reader->user_id,
            'guide_id' => $reader->guide_id,
        ]);

        return response()->json($note->toReader(), 201);
    }

    public function update(Request $request, Reader $reader, int $note): JsonResponse
    {
        $this->readable($request, $reader);
        $note = $this->own($reader, $note);

        $data = $request->validate(['body' => ['required', 'string', 'max:'.Note::MAX_LENGTH]]);
        $note->update(['body' => trim($data['body'])]);

        return response()->json($note->toReader());
    }

    public function destroy(Request $request, Reader $reader, int $note): Response
    {
        $this->readable($request, $reader);
        $this->own($reader, $note)->delete();

        return response()->noContent();
    }

    /** Their own assignment, on a guide they can open; its released version. */
    private function readable(Request $request, Reader $reader): Version
    {
        abort_unless($reader->user_id === $request->user()->id, 404);
        abort_unless($reader->guide->locale === app()->getLocale(), 404);
        abort_unless($reader->guide->status === GuideStatus::PUBLISHED, 404);

        return $reader->guide->releasedVersion() ?? abort(404);
    }

    private function own(Reader $reader, int $note): Note
    {
        return Note::query()->where('user_id', $reader->user_id)->where('guide_id', $reader->guide_id)->findOrFail($note);
    }
}
