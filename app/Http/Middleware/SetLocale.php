<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Forces the locale for the routes in one language's group — `locale:es`, `locale:en` — and
 * remembers it.
 *
 * **The URL wins, always.** A visitor who clicks a Spanish link asked for Spanish, whatever the
 * session said a moment ago — so this overwrites rather than consults, and remembering it is what
 * carries the choice to the pages no URL sets a language for: `/`, the sign-in, the settings.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next, string $locale): Response
    {
        app()->setLocale($locale);
        session(['locale' => $locale]);

        return $next($request);
    }
}
