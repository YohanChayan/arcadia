<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsApproved
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! auth()->check()) {
            // Preserve any existing flash messages
            $request->session()->reflash();

            return redirect()->route('login');
        }

        // Only the owner's routes ask for approval, and admission already refuses an owner
        // without it — so this is a floor, not a page to wait on.
        abort_unless(auth()->user()->approved_at !== null, 403);

        return $next($request);
    }
}
