<?php

namespace App\Http\Middleware;

use App\Services\ArcadiaSite;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lets a request through only when the user holds one of the named roles — `role:admin`,
 * or `role:admin,manager` once a second role is given something.
 *
 * Added 2026-09-24, when the roles were answered: every admin section is **admin only**, and
 * manager and developer are recognised roles with nothing granted yet. They pass the
 * `has-role` floor and are refused here.
 *
 * A route middleware and not four policies, because every rule so far is the same "admin
 * only" — a policy per model would be four files whose every method returns `isAdmin()`. Reach
 * for a policy when a rule first needs the target record, not just the actor.
 *
 * Names are matched against `Role::alias`, never `alias_number`, so the route reads as the
 * rule it states. `'unknown'` can never be named, so an unrecognised role is refused here too.
 *
 * The role is the account's Arcadia membership's (`ArcadiaSite::roleOf()`).
 */
class EnsureUserHasRole
{
    public function __construct(private readonly ArcadiaSite $site) {}

    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        $alias = $user ? $this->site->roleOf($user)?->alias : null;

        if (! in_array($alias, $roles, true)) {
            abort(403);
        }

        return $next($request);
    }
}
