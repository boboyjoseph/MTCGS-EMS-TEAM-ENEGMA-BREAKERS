<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ExpiredPageRedirectTest extends TestCase
{
    public function test_browser_419_responses_redirect_to_login(): void
    {
        Route::get('/_test/session-expired', fn () => abort(419));

        $this->get('/_test/session-expired')
            ->assertRedirect(route('login'));
    }

    public function test_json_419_responses_remain_json(): void
    {
        Route::get('/_test/session-expired-json', fn () => abort(419));

        $this->getJson('/_test/session-expired-json')
            ->assertStatus(419)
            ->assertJson([
                'message' => 'Your session has expired. Please refresh the page and try again.',
            ]);
    }
}
