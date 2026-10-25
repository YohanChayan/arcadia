<?php

namespace App\Http\Middleware;

use App\Services\ArcadiaSite;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function __construct(private readonly ArcadiaSite $site) {}

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),

            'user' => fn () => $request->user()
                ? array_merge($request->user()->only('name', 'email', 'email_verified_at', 'approved_at'), [
                    // The role on the account's membership.
                    'role' => $this->site->roleOf($request->user())?->only([
                        'name',
                        'alias_number',
                        'alias',
                    ]),
                ])
                : null,

            'message' => fn () => [
                'success' => session('success'),
                'error' => session('error'),
            ],

            // The request's language, never the account's: the URL decides, and the editor's
            // locale-named routes must match the page. A closure, so it is read after the route
            // group has set the language.
            'locale' => fn () => app()->getLocale(),

            // For the server render, which has no `@routes` script (`ssr.js`). Guests only: a
            // signed-in page is never server-rendered.
            'ziggy' => fn () => $request->user() ? null : [
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
            ],

            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
