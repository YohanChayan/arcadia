<?php

use App\Http\Controllers\Reader\AppearanceController;
use App\Http\Controllers\Reader\DashboardController;
use App\Http\Controllers\Reader\DeviceController;
use App\Http\Controllers\Reader\HelpController;
use App\Http\Controllers\Reader\NoteController;
use App\Http\Controllers\Reader\ProgressController;
use App\Services\ArcadiaSite;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| The reader's own pages
|--------------------------------------------------------------------------
|
| A signed-in reader's guides, once per language: `/es/mis-guias` and `/en/my-guides`, named
| `<locale>.reader.*`. Behind the floor and `role:reader` — the reader role of an Arcadia
| membership — and **not** `approved`: the membership is a reader's admission, and Arcadia never
| approves an account (`docs/architecture.md`).
|
| A reader's colours are saved here from the dashboard and from the guide page alike; their ticks,
| from the guide page; their notes, from the guide page too. Every one of them counts the browser as one of the reader's devices
| (`device`); the devices page itself does not, since it is where one at the limit chooses.
|
*/

/** @var ArcadiaSite $site */
foreach ($site->locales() as $locale) {
    Route::middleware([$site->localeMiddleware($locale), 'disable-ssr', 'auth', 'verified', 'has-role', 'role:reader', 'device'])
        ->prefix(ltrim($site->readerPath($locale), '/'))
        ->name("{$locale}.reader.")
        ->group(function () {
            Route::get('/', DashboardController::class)->name('home');

            Route::put('/{reader}/appearance', AppearanceController::class)
                ->whereNumber('reader')->name('appearance');

            Route::put('/{reader}/progress', ProgressController::class)
                ->whereNumber('reader')->name('progress');

            // Their own notes, on a line or a stage.
            Route::post('/{reader}/notes', [NoteController::class, 'store'])
                ->whereNumber('reader')->middleware('throttle:60,1')->name('notes.store');
            Route::put('/{reader}/notes/{note}', [NoteController::class, 'update'])
                ->whereNumber(['reader', 'note'])->middleware('throttle:60,1')->name('notes.update');
            Route::delete('/{reader}/notes/{note}', [NoteController::class, 'destroy'])
                ->whereNumber(['reader', 'note'])->name('notes.destroy');
        });

    // How Arcadia works, and who to write to: beside "My guides" in the reader's bar, at `/es/ayuda`.
    Route::middleware([$site->localeMiddleware($locale), 'disable-ssr', 'auth', 'verified', 'has-role', 'role:reader', 'device'])
        ->get($site->helpPath($locale), HelpController::class)
        ->name("{$locale}.reader.help");

    // One browser too many: the account's devices, and its once-a-month swap.
    Route::middleware([$site->localeMiddleware($locale), 'disable-ssr', 'auth', 'verified', 'has-role'])
        ->name("{$locale}.devices")
        ->group(function () use ($site, $locale) {
            Route::get($site->devicesPath($locale), [DeviceController::class, 'show']);
            Route::put($site->devicesPath($locale).'/{device}', [DeviceController::class, 'replace'])
                ->whereNumber('device')->middleware('throttle:10,10')->name('.replace');
        });
}
