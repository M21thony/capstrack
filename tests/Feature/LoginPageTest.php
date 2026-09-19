<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * The sign-in page renders.
 *
 * It is the one page every person meets before they have an account, and it
 * was rewritten onto Tailwind - which means it now depends on the Vite
 * manifest as well as its own Blade. A page that throws is a person who
 * cannot get in at all, so these assertions are deliberately about the
 * things a markup rewrite drops by accident rather than about styling.
 */
class LoginPageTest extends TestCase
{
    public function test_the_sign_in_page_renders_with_its_one_way_in(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertSee('Continue with CSPC Mail');
        $response->assertSee('How sign-in works');
    }

    /**
     * The submit handler, the reCAPTCHA field, and the Escape-closes-modals
     * partial all find their targets by these exact names.
     */
    public function test_the_page_keeps_the_hooks_its_scripts_reach_for(): void
    {
        $response = $this->get(route('login'));

        $response->assertSee('id="google-login-form"', false);
        $response->assertSee('id="google-signin-btn"', false);
        $response->assertSee('id="recaptcha-token"', false);
    }

    /** An expert whose account was taken away is told so, and why. */
    public function test_a_removed_expert_is_shown_the_reason(): void
    {
        $response = $this->get(route('login', [
            'removed_expert' => 1,
            'name' => 'Dr Reyes',
            'reason' => 'Left the department',
        ]));

        $response->assertOk();
        $response->assertSee('Account Removed');
        $response->assertSee('Dr Reyes');
        $response->assertSee('Left the department');
        // Escape closes it, and the partial finds it by this class alone.
        $response->assertSee('modal-overlay', false);
    }

    /** Nobody else sees that modal. */
    public function test_an_ordinary_visitor_sees_no_removal_notice(): void
    {
        $response = $this->get(route('login'));

        $response->assertDontSee('Account Removed');
    }
}
