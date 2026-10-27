<?php

namespace App\Http\Controllers\Editor;

use App\Models\Guide;
use App\Models\Note;
use App\Models\Reader;
use App\Services\ArcadiaSite;
use App\Services\GuidePresenter;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The guide as one of its readers sees it, for the owner to follow how far they have got: the
 * released version — the one they read — in their colours, greeted by their name, with their
 * ticks and their stages locked as theirs are. **Read only, by construction**: it is sent no
 * address to save a tick, answer a quick check, write a note or change a colour, so nothing on it
 * can change anything of theirs. Of their notes it shows only where there are some, never what they
 * say — readers are told their notes are for them alone.
 *
 * Only a reader of this guide with an account (a removed one is not one), and only once the guide
 * has a release; anything else is a 404. The general Preview (`PreviewController`) is unchanged.
 */
class ReaderViewController
{
    public function __invoke(Guide $guide, Reader $reader, GuidePresenter $presenter, ArcadiaSite $site): Response
    {
        abort_unless($guide->locale === app()->getLocale(), 404);
        abort_unless($reader->guide_id === $guide->id && $reader->user_id !== null, 404);

        $version = $guide->releasedVersion() ?? abort(404);

        return Inertia::render('Guide', [
            // As the reader is given it: no quick check's answer, which they are not sent either.
            ...$presenter->present($guide, $version),
            'preview' => false,
            'viewingAs' => $reader->displayName() ?? $reader->email,
            'appearance' => $reader->appearanceTokens(),
            'readerName' => $reader->displayName(),
            'progress' => [
                'ticked' => $reader->tickedKeys(),
                'unlockInOrder' => $guide->unlock_in_order,
                'readOnly' => true,
            ],
            // Where they have notes — "item:key" or "stage:key" — and nothing of what the notes say.
            'noteMarks' => Note::query()->where('user_id', $reader->user_id)->where('guide_id', $guide->id)
                ->get(['target_kind', 'target_key'])
                ->map(fn (Note $note) => "{$note->target_kind}:{$note->target_key}")
                ->unique()->values(),
            'privacyUrl' => $site->privacyPath(),
            'pageTitle' => $guide->title,
            'noIndex' => true,
        ]);
    }
}
