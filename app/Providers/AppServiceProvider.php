<?php

namespace App\Providers;

use App\Actions\LinkReaderAssignments;
use App\Models\User;
use App\Services\ArcadiaSite;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ArcadiaSite::class, fn () => new ArcadiaSite(config('arcadia')));

        // Derived from APP_URL rather than the environment, so a TLS host and plain HTTP behind
        // a proxy or in a container can share the code.
        if (Str::startsWith(config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        // An email just proved to be its owner's: the guides assigned to it become theirs.
        Event::listen(Verified::class, function (Verified $event) {
            if ($event->user instanceof User) {
                app(LinkReaderAssignments::class)->handle($event->user);
            }
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
