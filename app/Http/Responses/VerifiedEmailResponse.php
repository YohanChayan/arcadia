<?php

namespace App\Http\Responses;

use App\Services\ArcadiaSite;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\VerifyEmailResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Where a just-verified email lands: where they were going — on Arcadia, the guide they tapped —
 * else Arcadia's home for them, with Fortify's `?verified=1`. Fortify's own sends everyone
 * to `fortify.home`, one path for every user.
 */
class VerifiedEmailResponse implements VerifyEmailResponse
{
    public function __construct(private readonly ArcadiaSite $site) {}

    /**
     * @param  Request  $request
     */
    public function toResponse($request): Response
    {
        if ($request->wantsJson()) {
            return new JsonResponse('', 204);
        }

        return redirect()->intended($this->site->homeAfterSignIn($request->user()).'?verified=1');
    }
}
