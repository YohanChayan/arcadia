<?php

namespace App\Http\Responses;

use App\Services\ArcadiaSite;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\LoginResponse;
use Laravel\Fortify\Contracts\RegisterResponse;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Where a sign-in lands — and a registration, by the same rule (a verified email:
 * `VerifiedEmailResponse`).
 *
 * Fortify's own sends everyone to `fortify.home`, one path for every user — Arcadia's land in two
 * places. The intended URL still wins.
 */
class SignedInResponse implements LoginResponse, RegisterResponse, TwoFactorLoginResponse
{
    public function __construct(private readonly ArcadiaSite $site) {}

    /**
     * @param  Request  $request
     */
    public function toResponse($request): Response
    {
        if ($request->wantsJson()) {
            return new JsonResponse(['two_factor' => false]);
        }

        return redirect()->intended($this->site->homeAfterSignIn($request->user()));
    }
}
