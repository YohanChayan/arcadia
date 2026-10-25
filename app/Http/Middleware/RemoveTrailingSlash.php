<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * One URL per page: a GET for `/about-us/` is 301'd to `/about-us`.
 *
 * Laravel's router ignores a trailing slash, so both forms used to answer 200 — and the
 * slashed one carried a canonical pointing at itself while its hreflang alternates pointed at
 * the slashless path. Two signals disagreeing about which URL is the page. Production nginx
 * does not normalise it either, and the `.htaccess` rule that would have is inert there.
 *
 * Global rather than in the `web` group, so it runs before routing and the redirect is the
 * first and only hop. GET and HEAD only: a form posting to a slashed URL keeps its body.
 *
 * The target is rebuilt from the request's own host with the leading slashes collapsed. A path
 * of `//evil.example/` must not become a `Location: //evil.example` — a protocol-relative URL is
 * a redirect off the site.
 */
class RemoveTrailingSlash
{
    public function handle(Request $request, Closure $next): Response
    {
        $path = $request->getPathInfo();

        if (! $request->isMethodSafe() || $path === '/' || ! str_ends_with($path, '/')) {
            return $next($request);
        }

        $query = $request->getQueryString();
        $target = $request->getSchemeAndHttpHost().$request->getBaseUrl().'/'.trim($path, '/')
            .($query === null ? '' : '?'.$query);

        return redirect()->to($target, 301);
    }
}
