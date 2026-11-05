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

    /**
     * The sign-in page carries the privacy pop-up, and signing out lands
     * straight on it with the pop-up told to stay shut.
     */
    public function test_privacy_popup_is_skipped_only_after_sign_out(): void
    {
        $this->get('/login')->assertOk()->assertSee('id="privacy-modal"', false)->assertSee('data-skip=""', false);

        $this->post('/logout')->assertRedirect(route('login'))->assertSessionHas('signed_out', true);

        $this->withSession(['signed_out' => true])->get('/login')->assertSee('data-skip="1"', false);
    }
}
