<?php

namespace App\Http\Responses;

use App\Services\ArcadiaSite;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Passkeys\Contracts\PasskeyLoginResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Where a passkey sign-in lands: the same place as a password's (`SignedInResponse`) — the
 * intended page, else a reader's guides or the owner's editor. The browser asked for JSON, so
 * the destination travels in `redirect` for the passkey button to follow.
 */
class PasskeySignedInResponse implements PasskeyLoginResponse
{
    public function __construct(private readonly ArcadiaSite $site) {}

    /**
     * @param  Request  $request
     */
    public function toResponse($request): Response
    {
        $redirect = redirect()->intended($this->site->homeAfterSignIn($request->user()));

        return $request->wantsJson()
            ? new JsonResponse(['redirect' => $redirect->getTargetUrl()])
            : $redirect;
    }
}
