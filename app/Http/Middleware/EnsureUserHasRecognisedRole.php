<?php

namespace App\Http\Middleware;

use App\Models\Role;
use App\Services\ArcadiaSite;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The authorisation floor: refuses anyone whose role on Arcadia is absent or unrecognised.
 *
 * It does **not** ask what a role may do — that is `EnsureUserHasRole` (`role:admin`). It asks
 * only whether this is a user Arcadia has placed at all: an `admin` or a `reader` membership.
 * A route added inside the signed-in group is guarded the moment it is declared.
 */
class EnsureUserHasRecognisedRole
{
    /** @var list<int> */
    private const RECOGNISED = [
        Role::ALIAS_ADMIN,
        Role::ALIAS_READER,
    ];

    public function __construct(private readonly ArcadiaSite $site) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $alias = $user ? $this->site->roleOf($user)?->alias_number : null;

        if (! in_array($alias, self::RECOGNISED, true)) {
            abort(403);
        }

        return $next($request);
    }
}
