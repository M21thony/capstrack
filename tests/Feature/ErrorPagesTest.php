<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The error views are what a reader sees when everything else has failed, so
 * these tests hold them to the one rule that matters: they must render on their
 * own. No Vite manifest, no session, no database, no signed-in user.
 */
class ErrorPagesTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function errorViews(): array
    {
        return [
            '403' => ['403', 'That is not yours to open', 'ask the CaPSA/TSA to check your role'],
            '404' => ['404', 'That page is not here', 'Everything you can open is in the sidebar'],
            '419' => ['419', 'Your sign-in expired', 'Nothing was saved'],
            '500' => ['500', 'Something broke on our side', 'nothing else was altered'],
            '503' => ['503', 'down for maintenance', 'Nothing you have submitted is affected'],
        ];
    }

    #[DataProvider('errorViews')]
    public function test_each_error_view_renders_with_its_own_words(string $code, string $heading, string $body): void
    {
        $html = view("errors.{$code}")->render();

        $this->assertStringContainsString($heading, $html);
        $this->assertStringContainsString($body, $html);
        $this->assertStringContainsString('CapsTRACK', $html);
        $this->assertStringContainsString($code, $html);
    }

    #[DataProvider('errorViews')]
    public function test_no_error_view_depends_on_the_vite_bundle(string $code): void
    {
        $source = file_get_contents(resource_path("views/errors/{$code}.blade.php"));

        $this->assertStringNotContainsString('@vite', $source);
        $this->assertStringContainsString('@extends(\'errors.layout\')', $source);
    }

    public function test_the_shared_shell_loads_only_the_static_stylesheet(): void
    {
        $source = file_get_contents(resource_path('views/errors/layout.blade.php'));

        $this->assertStringNotContainsString('@vite', $source);
        $this->assertStringContainsString("asset('css/admin.css')", $source);
    }

    public function test_every_error_page_offers_a_way_out(): void
    {
        foreach (array_keys(self::errorViews()) as $code) {
            $html = view("errors.{$code}")->render();

            $this->assertMatchesRegularExpression(
                '/<a[^>]+href="[^"]+"[^>]*>/',
                $html,
                "errors/{$code} renders no link, so a reader reaching it is stuck."
            );
        }
    }

    public function test_an_unknown_url_renders_our_404_and_not_laravels(): void
    {
        $this->get('/a-page-that-does-not-exist')
            ->assertNotFound()
            ->assertSee('That page is not here');
    }
}
