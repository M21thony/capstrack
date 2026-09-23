<?php

namespace Tests\Feature;

use Tests\TestCase;

class PrivacyPageTest extends TestCase
{
    /**
     * Google's consent screen links here, so it must open without signing in.
     */
    public function test_privacy_page_is_public(): void
    {
        $this->get('/privacy')
            ->assertOk()
            ->assertSee('Privacy Policy')
            ->assertSee('Google Calendar');
    }
}
