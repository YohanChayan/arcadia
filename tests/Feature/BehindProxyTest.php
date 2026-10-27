<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * In production Caddy terminates TLS and forwards plain HTTP across a Docker network, so the app
 * learns the scheme from `X-Forwarded-Proto` — from a private address only. The root's redirect
 * to a home page is the plainest URL the app builds from the request.
 */
class BehindProxyTest extends TestCase
{
    public function test_a_request_forwarded_by_the_proxy_keeps_https(): void
    {
        $response = $this->withServerVariables(['REMOTE_ADDR' => '172.18.0.5'])
            ->withHeader('X-Forwarded-Proto', 'https')
            ->get('http://arcadia.yhndevside.com/');

        $this->assertStringStartsWith('https://arcadia.yhndevside.com/', $response->headers->get('Location'));
    }

    public function test_forwarded_headers_from_a_public_address_are_ignored(): void
    {
        $response = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])
            ->withHeader('X-Forwarded-Proto', 'https')
            ->get('http://arcadia.yhndevside.com/');

        $this->assertStringStartsWith('http://arcadia.yhndevside.com/', $response->headers->get('Location'));
    }
}
