<?php

namespace App\Http\Middleware;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmailIsVerified
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure  $next
     */
    public function handle(Request $request, $next): Response
    {
        if (! auth()->check()) {
            $request->session()->reflash();

            return redirect()->route('login');
        }

        // disable verification for unverified users
        if (! (auth()->user() instanceof MustVerifyEmail)) {
            return $next($request);
        }

        // Unverified
        if (! auth()->user()->hasVerifiedEmail()) {
            $request->session()->reflash();

            return redirect()->route('verification.notice');
        }

        return $next($request);
    }
}
