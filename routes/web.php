<?php

use App\Http\Controllers\AccessRequestController;
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\CatalogueController;
use App\Http\Controllers\GuidePageController;
use App\Http\Controllers\InquiryController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\PrivacyController;
use App\Http\Controllers\RootController;
use App\Services\ArcadiaSite;
use Illuminate\Support\Facades\Route;

$site = app(ArcadiaSite::class);

// The editor first, so its `/{locale}/editor` paths are matched before the public ones. Each file
// reads the site as `$site`.
require __DIR__.'/editor.php';
require __DIR__.'/reader.php';

/*
|--------------------------------------------------------------------------
| Public routes
|--------------------------------------------------------------------------
|
| Each page is registered once per language, inside that language's route group, which sets the
| language from the URL and remembers it for `/`: `es.guide` at `/guia/{token}`,
| `en.guide` at `/guide/{token}`. The language is a segment of the name, never a suffix.
|
| Every other path is a 404. Fortify's account screens and the settings pages are registered
| elsewhere.
|
*/

// The language this browser last used here, else Spanish.
Route::get('/', RootController::class)->name('root');

// "Continue with Google".
Route::get('/auth/google', [GoogleController::class, 'redirect'])->name('auth.google');
Route::get('/auth/google/callback', [GoogleController::class, 'callback'])->name('auth.google.callback');

foreach ($site->locales() as $locale) {
    Route::middleware($site->localeMiddleware($locale))
        ->name("{$locale}.")
        ->group(function () use ($site, $locale) {
            Route::get($site->homePath($locale), LandingController::class)->name('home');

            // Anyone asks for a guide, from the landing page's popup — no account needed; a signed-in
            // one is recorded with it. A person sends one or two; more than five in ten minutes
            // from one address is not a person.
            Route::post($site->inquiryPath($locale), InquiryController::class)
                ->middleware('throttle:5,10')
                ->name('inquiry');

            // The guides open for enrollment, each card opening its presentation (ADR 0023).
            Route::get($site->cataloguePath($locale), CatalogueController::class)->name('catalogue');

            // A guide, by its link — only under its own language's slug. Its readers read it; anyone
            // else is shown what it is about and, while it is open, may ask to enroll (ADR 0022).
            // The device check is the controller's, for a reader only (`RecogniseDevice::check()`).
            Route::get('/'.$site->guidePath($locale).'/{token}', GuidePageController::class)
                ->where('token', '[a-z0-9]{24}')
                ->name('guide');

            // An account the guide is not shared with asks to enroll in it. No device: asking is
            // not reading.
            Route::post('/'.$site->guidePath($locale).'/{token}/'.$site->accessPath($locale), AccessRequestController::class)
                ->where('token', '[a-z0-9]{24}')
                ->middleware(['auth', 'verified', 'has-role', 'throttle:10,10'])
                ->name('guide.access');

            // What Arcadia keeps, and what YouTube does on play.
            Route::get($site->privacyPath($locale), PrivacyController::class)->name('privacy');
        });
}

// The kit's account settings: the profile, the password and passkeys.
require __DIR__.'/settings.php';
