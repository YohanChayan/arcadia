<?php

namespace App\Http\Middleware;

use App\Services\ArcadiaSite;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The language of a request no route group has set one for — `/`, Fortify's sign-in, a 404:
 * the one this browser last used here, else Spanish (`ArcadiaSite::localeFor()`). Never the
 * account's or the browser's. The route groups then set the page's language from the URL.
 */
class ResolveLocale
{
    public function __construct(private readonly ArcadiaSite $site) {}

    public function handle(Request $request, Closure $next): Response
    {
        app()->setLocale($this->site->localeFor(session('locale')));

        return $next($request);
    }
}
