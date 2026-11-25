<?php

namespace Tests\Feature;

use Tests\TestCase;

class ContentSecurityPolicyTest extends TestCase
{
    public function test_content_security_policy_header_is_added_outside_local_environment(): void
    {
        $response = $this->get('/up');

        $response->assertOk();
        $this->assertSame(
            "default-src 'self'; base-uri 'self'; object-src 'none'; frame-ancestors 'none'; form-action 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://code.jquery.com; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com; font-src 'self' data: https://cdnjs.cloudflare.com https://fonts.gstatic.com; img-src 'self' data: blob: https:; connect-src 'self'; frame-src 'none'; upgrade-insecure-requests",
            $response->headers->get('Content-Security-Policy')
        );
    }

    public function test_content_security_policy_header_is_added_in_local_environment(): void
    {
        $this->app->detectEnvironment(fn () => 'local');

        $response = $this->get('/up');

        $response->assertOk();
        $this->assertNotEmpty($response->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString("frame-ancestors 'none'", $response->headers->get('Content-Security-Policy'));
    }
}