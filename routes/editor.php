<?php

use App\Enums\ContentNode;
use App\Enums\Vocabulary;
use App\Http\Controllers\Editor\ActivityController;
use App\Http\Controllers\Editor\ActivityImportController;
use App\Http\Controllers\Editor\AppearanceController;
use App\Http\Controllers\Editor\CompareController;
use App\Http\Controllers\Editor\ContentController;
use App\Http\Controllers\Editor\DeviceController;
use App\Http\Controllers\Editor\EditorHomeController;
use App\Http\Controllers\Editor\GuideController;
use App\Http\Controllers\Editor\GuidePromptController;
use App\Http\Controllers\Editor\ImageController;
use App\Http\Controllers\Editor\ImportController;
use App\Http\Controllers\Editor\InquiryController;
use App\Http\Controllers\Editor\InquiryGuideController;
use App\Http\Controllers\Editor\PhraseController;
use App\Http\Controllers\Editor\PresentationController;
use App\Http\Controllers\Editor\PreviewController;
use App\Http\Controllers\Editor\PromptFillController;
use App\Http\Controllers\Editor\PromptSectionController;
use App\Http\Controllers\Editor\PromptTemplateController;
use App\Http\Controllers\Editor\PromptVariableController;
use App\Http\Controllers\Editor\PromptVersionController;
use App\Http\Controllers\Editor\ReaderController;
use App\Http\Controllers\Editor\SettingsController;
use App\Http\Controllers\Editor\VersionController;
use App\Http\Controllers\Editor\VocabularyController;
use App\Services\ArcadiaSite;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| The editor
|--------------------------------------------------------------------------
|
| The owner's signed-in surface, once per language: `/es/editor` and `/en/editor`, named
| `<locale>.editor.*`. The language's route group sets the locale from the URL, and every
| editor query is scoped by it, so each editor sees and files only its own language's guides,
| requests and settings — whatever language the owner's account is in.
|
| The signed-in floor — verified, approved, a recognised role — then `role:admin`; and a user the
| site did not admit at sign-in could not reach it anyway.
|
| Guides are addressed by id here, not by their link token: the token is the public way in,
| and rotating it must not break the editor's own URLs.
|
*/

/** @var ArcadiaSite $site */
foreach ($site->locales() as $locale) {
    Route::middleware([$site->localeMiddleware($locale), 'disable-ssr', 'auth', 'approved', 'verified', 'has-role', 'role:admin'])
        ->prefix(ltrim($site->editorPath($locale), '/'))
        ->name("{$locale}.editor.")
        ->group(function () {
            Route::get('/', EditorHomeController::class)->name('home');

            Route::get('/guides', [GuideController::class, 'index'])->name('guides.index');
            Route::get('/guides/create', [GuideController::class, 'create'])->name('guides.create');
            Route::post('/guides', [GuideController::class, 'store'])->name('guides.store');
            Route::get('/guides/{guide:id}/edit', [GuideController::class, 'edit'])->name('guides.edit');
            Route::put('/guides/{guide:id}', [GuideController::class, 'update'])->name('guides.update');
            Route::delete('/guides/{guide:id}', [GuideController::class, 'destroy'])->name('guides.destroy');
            Route::post('/guides/{guide:id}/rotate-link', [GuideController::class, 'rotateLink'])->name('guides.rotate-link');

            // Who a guide is for, by email.
            Route::get('/guides/{guide:id}/readers', [ReaderController::class, 'index'])->name('readers.index');
            Route::post('/guides/{guide:id}/readers', [ReaderController::class, 'store'])->name('readers.store');
            Route::delete('/guides/{guide:id}/readers/{reader}', [ReaderController::class, 'destroy'])
                ->whereNumber('reader')->name('readers.destroy');
            // What the guide calls a reader.
            Route::put('/guides/{guide:id}/readers/{reader}/name', [ReaderController::class, 'rename'])
                ->whereNumber('reader')->name('readers.rename');
            // Tell a reader again, by email, that the guide is theirs.
            Route::post('/guides/{guide:id}/readers/{reader}/notify', [ReaderController::class, 'notify'])
                ->whereNumber('reader')->middleware('throttle:20,10')->name('readers.notify');
            // Enrollment: whether the guide takes requests, and the requests — approve, decline, and
            // the owner's own note on each (ADR 0022).
            Route::put('/guides/{guide:id}/enrollment', [ReaderController::class, 'enrollment'])->name('enrollment.update');
            Route::put('/guides/{guide:id}/access-requests/{accessRequest}/note', [ReaderController::class, 'note'])
                ->whereNumber('accessRequest')->name('access-requests.note');
            Route::post('/guides/{guide:id}/access-requests/{accessRequest}', [ReaderController::class, 'accept'])
                ->whereNumber('accessRequest')->name('access-requests.accept');
            Route::delete('/guides/{guide:id}/access-requests/{accessRequest}', [ReaderController::class, 'dismiss'])
                ->whereNumber('accessRequest')->name('access-requests.dismiss');
            // Colours: the guide's default for new readers, and one reader's own.
            Route::put('/guides/{guide:id}/appearance', [AppearanceController::class, 'guide'])->name('appearance.guide');
            Route::put('/guides/{guide:id}/readers/{reader}/appearance', [AppearanceController::class, 'reader'])
                ->whereNumber('reader')->name('appearance.reader');
            // A reader's devices — per account, whichever guide's screen they are managed from.
            Route::delete('/accounts/{user}/devices/{device}', [DeviceController::class, 'destroy'])
                ->whereNumber(['user', 'device'])->name('devices.destroy');
            Route::put('/accounts/{user}/device-limit', [DeviceController::class, 'limit'])
                ->whereNumber('user')->name('devices.limit');

            // Its images, by slot (`ImageSlot`): the upload and the crops cut from it. One is
            // removed by its id, as a slot may hold several.
            Route::get('/guides/{guide:id}/images', [ImageController::class, 'index'])->name('images.index');
            Route::post('/guides/{guide:id}/images/{slot}', [ImageController::class, 'store'])->name('images.store');
            Route::delete('/guides/{guide:id}/images/{image}', [ImageController::class, 'destroy'])
                ->whereNumber('image')->scopeBindings()->name('images.destroy');
            // What its link shows someone who may not read it: the pitch, and the page previewed.
            Route::put('/guides/{guide:id}/pitch', [PresentationController::class, 'update'])->name('presentation.update');
            Route::get('/guides/{guide:id}/presentation', [PresentationController::class, 'preview'])->name('presentation.preview');

            // The content tree of a guide's draft version: sections, stages, cards, items.
            $nodes = implode('|', array_column(ContentNode::cases(), 'value'));

            Route::get('/guides/{guide:id}/content', [ContentController::class, 'show'])->name('content.show');
            Route::post('/guides/{guide:id}/content/{node}', [ContentController::class, 'store'])
                ->where('node', $nodes)->name('content.store');
            Route::put('/guides/{guide:id}/content/{node}/{id}', [ContentController::class, 'update'])
                ->where(['node' => $nodes, 'id' => '[0-9]+'])->name('content.update');
            Route::delete('/guides/{guide:id}/content/{node}/{id}', [ContentController::class, 'destroy'])
                ->where(['node' => $nodes, 'id' => '[0-9]+'])->name('content.destroy');
            Route::post('/guides/{guide:id}/content/{node}/{id}/move/{direction}', [ContentController::class, 'move'])
                ->where(['node' => $nodes, 'id' => '[0-9]+', 'direction' => 'up|down'])->name('content.move');

            // Versions: release the draft, draft the next, its change log, and fix-everywhere — the
            // one edit that reaches released versions.
            Route::post('/guides/{guide:id}/versions/release', [VersionController::class, 'release'])->name('versions.release');
            Route::post('/guides/{guide:id}/versions', [VersionController::class, 'store'])->name('versions.store');
            Route::post('/guides/{guide:id}/versions/changes', [VersionController::class, 'storeChange'])->name('versions.changes.store');
            Route::delete('/guides/{guide:id}/versions/changes/{change}', [VersionController::class, 'destroyChange'])
                ->whereNumber('change')->name('versions.changes.destroy');
            Route::put('/guides/{guide:id}/items/{key}/fix', [VersionController::class, 'fix'])
                ->where('key', '[a-z0-9]+')->name('items.fix');

            // An AI's answer into the draft: checked and summarised first, then written.
            Route::post('/guides/{guide:id}/import/preview', [ImportController::class, 'preview'])->name('import.preview');
            Route::post('/guides/{guide:id}/import', [ImportController::class, 'store'])->name('import.store');

            // An AI's answer to an activity prompt, attached to the draft's nodes by their keys —
            // checked first, then attached — and the owner's review of each: reworded, or removed.
            Route::post('/guides/{guide:id}/activities/import/preview', [ActivityImportController::class, 'preview'])->name('activities.import.preview');
            Route::post('/guides/{guide:id}/activities/import', [ActivityImportController::class, 'store'])->name('activities.import.store');
            Route::put('/guides/{guide:id}/activities/{node}/{id}', [ActivityController::class, 'update'])
                ->where(['node' => 'items|stages|sections', 'id' => '[0-9]+'])->name('activities.update');
            Route::delete('/guides/{guide:id}/activities/{node}/{id}', [ActivityController::class, 'destroy'])
                ->where(['node' => 'items|stages|sections', 'id' => '[0-9]+'])->name('activities.destroy');

            // The research prompt copied from its content screen: recorded on the guide, and its
            // version frozen.
            Route::post('/guides/{guide:id}/prompt', GuidePromptController::class)->name('guides.prompt');

            // The reader's page, on any version, for the owner: `?version=2`.
            Route::get('/guides/{guide:id}/preview', PreviewController::class)->name('preview');

            // Two versions compared, node by node: `?from=1&to=3`.
            Route::get('/guides/{guide:id}/compare', CompareController::class)->name('compare');

            // AI research prompts: templates, their versions, and the latest version's sections.
            // Shared by both hosts' editors — the language is one of a prompt's variables.
            Route::get('/prompts', [PromptTemplateController::class, 'index'])->name('prompts.index');
            Route::post('/prompts', [PromptTemplateController::class, 'store'])->name('prompts.store');
            Route::get('/prompts/{template:id}', [PromptTemplateController::class, 'show'])->name('prompts.show');
            Route::put('/prompts/{template:id}', [PromptTemplateController::class, 'update'])->name('prompts.update');
            Route::delete('/prompts/{template:id}', [PromptTemplateController::class, 'destroy'])->name('prompts.destroy');
            Route::post('/prompts/{template:id}/versions', [PromptVersionController::class, 'store'])->name('prompts.versions.store');
            Route::put('/prompts/{template:id}/versions/{version}', [PromptVersionController::class, 'update'])
                ->whereNumber('version')->name('prompts.versions.update');
            Route::post('/prompts/{template:id}/sections', [PromptSectionController::class, 'store'])->name('prompts.sections.store');
            Route::put('/prompts/{template:id}/sections/{section}', [PromptSectionController::class, 'update'])
                ->whereNumber('section')->name('prompts.sections.update');
            Route::delete('/prompts/{template:id}/sections/{section}', [PromptSectionController::class, 'destroy'])
                ->whereNumber('section')->name('prompts.sections.destroy');
            Route::post('/prompts/{template:id}/sections/{section}/move/{direction}', [PromptSectionController::class, 'move'])
                ->where(['section' => '[0-9]+', 'direction' => 'up|down'])->name('prompts.sections.move');
            Route::post('/prompts/{template:id}/variables', [PromptVariableController::class, 'store'])->name('prompts.variables.store');
            Route::put('/prompts/{template:id}/variables/{variable}', [PromptVariableController::class, 'update'])
                ->whereNumber('variable')->name('prompts.variables.update');
            Route::delete('/prompts/{template:id}/variables/{variable}', [PromptVariableController::class, 'destroy'])
                ->whereNumber('variable')->name('prompts.variables.destroy');
            Route::post('/prompts/{template:id}/variables/{variable}/move/{direction}', [PromptVariableController::class, 'move'])
                ->where(['variable' => '[0-9]+', 'direction' => 'up|down'])->name('prompts.variables.move');
            // The live preview: a version filled with the values typed so far. Changes nothing.
            Route::post('/prompts/{template:id}/fill', PromptFillController::class)->name('prompts.fill');

            // What visitors asked for, from the landing page's popup.
            Route::get('/requests', [InquiryController::class, 'index'])->name('inquiries.index');
            // One the owner writes, to make a guide of their own through the same steps.
            Route::post('/requests', [InquiryController::class, 'store'])->name('inquiries.store');
            Route::delete('/requests/{inquiry}', [InquiryController::class, 'destroy'])
                ->whereNumber('inquiry')->name('inquiries.destroy');
            // The draft guide answering it, started from what the request says.
            Route::post('/requests/{inquiry}/guide', InquiryGuideController::class)
                ->whereNumber('inquiry')->name('inquiries.guide');

            // This host's settings: the price a guide is offered at.
            Route::get('/settings', [SettingsController::class, 'edit'])->name('settings.edit');
            Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');

            $vocabularies = implode('|', array_column(Vocabulary::cases(), 'value'));

            // Short motivation phrases, by the moment they are shown at.
            Route::get('/phrases', [PhraseController::class, 'index'])->name('phrases.index');
            Route::post('/phrases', [PhraseController::class, 'store'])->name('phrases.store');
            Route::put('/phrases/{phrase}', [PhraseController::class, 'update'])->whereNumber('phrase')->name('phrases.update');
            Route::delete('/phrases/{phrase}', [PhraseController::class, 'destroy'])->whereNumber('phrase')->name('phrases.destroy');

            Route::get('/vocabulary', [VocabularyController::class, 'index'])->name('vocabulary.index');
            Route::post('/vocabulary/{vocabulary}', [VocabularyController::class, 'store'])
                ->where('vocabulary', $vocabularies)->name('vocabulary.store');
            Route::put('/vocabulary/{vocabulary}/{term}', [VocabularyController::class, 'update'])
                ->where(['vocabulary' => $vocabularies, 'term' => '[0-9]+'])->name('vocabulary.update');
            Route::delete('/vocabulary/{vocabulary}/{term}', [VocabularyController::class, 'destroy'])
                ->where(['vocabulary' => $vocabularies, 'term' => '[0-9]+'])->name('vocabulary.destroy');
        });
}
