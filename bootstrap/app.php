<?php

use App\Http\Middleware\DisableSsrForAuth;
use App\Http\Middleware\EnsureEmailIsVerified;
use App\Http\Middleware\EnsureUserHasRecognisedRole;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\EnsureUserIsApproved;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RecogniseDevice;
use App\Http\Middleware\RemoveTrailingSlash;
use App\Http\Middleware\ResolveLocale;
use App\Http\Middleware\SetLocale;
use App\Services\ArcadiaSite;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['sidebar_state']);

        // Before routing, so `/es/guias/` is one 301 away from its page. See the class.
        $middleware->prepend(RemoveTrailingSlash::class);

        $middleware->web(append: [
            // Before Inertia renders: a signed-in page is never server-rendered.
            DisableSsrForAuth::class,
            ResolveLocale::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        // Signed in already and at a sign-in page: on to where a sign-in lands — a reader's
        // guides, the owner's editor.
        $middleware->redirectUsersTo(fn (Request $request) => app(ArcadiaSite::class)->homeAfterSignIn($request->user()));

        $middleware->alias([
            'disable-ssr' => DisableSsrForAuth::class,
            'verified' => EnsureEmailIsVerified::class,
            'approved' => EnsureUserIsApproved::class,
            'has-role' => EnsureUserHasRecognisedRole::class,
            'role' => EnsureUserHasRole::class,
            'locale' => SetLocale::class,
            'device' => RecogniseDevice::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {

        /**
         * An expired session ends a form submission back on the form, with the reason, rather
         * than on Laravel's raw "Page Expired" screen.
         *
         * Typed on `HttpException` with a status guard, NOT on `TokenMismatchException`: the
         * handler rewrites the token exception into `HttpException(419)` before the render
         * callbacks are consulted, so a callback typed on the original never fires.
         */
        $exceptions->render(function (HttpException $e, Request $request) {
            if ($e->getStatusCode() !== 419) {
                return null;
            }

            return back()->withInput()->withErrors(['session' => __('errors.session_expired')]);
        });

        /**
         * Every error status a person can meet, rendered one way, in one place.
         *
         * `respond()` runs after the framework has turned the exception into a response, so it
         * sees the final status whatever was thrown. Pages and Inertia visits get `errors/Error`;
         * JSON gets `{"message": …}`; a crash while `app.debug` is on keeps its stack trace.
         * `rescue()`, because rendering a page is itself work that can fail.
         */
        // The statuses `errors/Error` has copy for in both locales (`lang/{en,es}/errors.php`).
        $statuses = [403, 404, 500, 503];

        $exceptions->respond(function (Response $response, Throwable $e, Request $request) use ($statuses) {
            $status = $response->getStatusCode();

            if (! in_array($status, $statuses, true)) {
                return $response;
            }

            if (config('app.debug') && ! $e instanceof HttpExceptionInterface) {
                return $response;
            }

            if (! $request->inertia() && ($request->expectsJson() || $request->is('api/*'))) {
                return response()->json(['message' => __("errors.titles.$status")], $status);
            }

            // Arcadia's document: an error before `HandleInertiaRequests` has run would otherwise
            // render in the default one.
            Inertia::setRootView('app');

            return rescue(
                // The address as a prop of its own: a path no route matched never ran the shared
                // props (`HandleInertiaRequests`).
                fn () => Inertia::render('errors/Error', [
                    'status' => $status,
                    'contactEmail' => app(ArcadiaSite::class)->contactEmail(),
                ])
                    ->toResponse($request)
                    ->setStatusCode($status),
                $response,
                report: false,
            );
        });

    })->create();
