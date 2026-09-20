<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class TeamPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_page_loads_for_guests(): void
    {
        $response = $this->get(route('team'));

        $response->assertOk();
    }

    public function test_team_page_shows_all_five_members_with_roles(): void
    {
        $response = $this->get(route('team'));

        $response->assertSee('Meet the Team')
            ->assertSee('Built by')
            ->assertSee('Jolina Rabusa')
            ->assertSee('Project Lead')
            ->assertSee('John Kenneth Aurillas')
            ->assertSee('Backend Developer')
            ->assertSee('Mark Anthony Bailla')
            ->assertSee('Full-Stack Developer')
            ->assertSee('Ian Cyrus')
            ->assertSee('UI/UX Designer')
            ->assertSee('Alexa Nicole')
            ->assertSee('QA & Documentation');
    }

    public function test_team_page_links_back_to_sign_in(): void
    {
        $response = $this->get(route('team'));

        $response->assertSee(route('login'));
    }

    public function test_team_cards_render_contact_icons_even_without_links(): void
    {
        // Rendered with controlled data so filling in (or clearing) real
        // links on the controller never breaks this test.
        $html = view('auth.team', ['team' => [
            [
                'name' => 'Test One', 'initials' => 'T1', 'role' => 'Lead',
                'image' => '', 'facebook' => 'https://facebook.com/test.one',
                'email' => 'test.one@cspc.edu.ph',
            ],
            [
                'name' => 'Test Two', 'initials' => 'T2', 'role' => 'Dev',
                'image' => '', 'facebook' => '', 'email' => '',
            ],
        ]])->render();

        // Facebook icon is a real link when set...
        $this->assertStringContainsString('href="https://facebook.com/test.one"', $html);
        // ...the envelope copies the address rather than using mailto...
        $this->assertStringContainsString('data-email="test.one@cspc.edu.ph"', $html);
        $this->assertStringContainsString('class="icon-copy"', $html);
        // ...and empty values render as disabled slots.
        $this->assertStringContainsString('icon-off', $html);
        $this->assertStringContainsString('Facebook link not added yet', $html);
        $this->assertStringContainsString('Email not added yet', $html);
        $this->assertStringNotContainsString('mailto:', $html);
    }

    public function test_live_team_page_renders_copy_buttons_for_filled_emails(): void
    {
        $response = $this->get(route('team'));

        $response->assertOk()
            ->assertSee('icon-copy', false)
            ->assertSee('data-email', false);
    }

    /**
     * The copy buttons have to be wired, not just drawn.
     *
     * They shipped once as markup and CSS with no listener at all, so clicking
     * the envelope did nothing. Markup assertions cannot see that, so this
     * checks the handler and then hands the block to node --check: one syntax
     * error inside a <script> kills every handler declared in it.
     */
    public function test_team_page_copy_buttons_are_wired_and_the_script_parses(): void
    {
        $html = $this->get(route('team'))->assertOk()->getContent();

        $this->assertStringContainsString("querySelectorAll('.icon-copy')", $html);
        $this->assertStringContainsString('navigator.clipboard', $html);

        if (! Process::run(['node', '--version'])->successful()) {
            $this->markTestSkipped('node is not on PATH, so the script cannot be parsed here.');
        }

        preg_match_all('/<script(?![^>]*\bsrc=)[^>]*>(.*?)<\/script>/s', $html, $matches);

        $this->assertNotEmpty($matches[1], 'the team page carries inline script');

        foreach ($matches[1] as $i => $script) {
            if (trim($script) === '') {
                continue;
            }

            $file = tempnam(sys_get_temp_dir(), 'capstrack_js_').'.js';
            file_put_contents($file, $script);

            $result = Process::run(['node', '--check', $file]);
            @unlink($file);

            $this->assertTrue(
                $result->successful(),
                "Inline script #{$i} on the team page does not parse:\n".$result->errorOutput()
            );
        }
    }

    public function test_login_page_links_to_the_team_page(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk()
            ->assertSee(route('team'))
            ->assertSee('Meet the Team');
    }
}
