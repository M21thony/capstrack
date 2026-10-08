<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\Role;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Vite;
use Tests\TestCase;

/**
 * The headers the OWASP ZAP scan found missing, and the API no longer
 * answering to any site that asks.
 */
class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_pages_carry_the_security_headers(): void
    {
        foreach (['/login', '/up'] as $url) {
            $this->get($url)
                ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
                ->assertHeader('X-Content-Type-Options', 'nosniff')
                ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
                ->assertHeaderMissing('X-Powered-By');
        }
    }

    public function test_the_content_security_policy_refuses_scripts_from_other_sites(): void
    {
        $policy = collect(explode(';', (string) $this->get('/login')->headers->get('Content-Security-Policy')))
            ->mapWithKeys(function (string $directive): array {
                $parts = preg_split('/\s+/', trim($directive));

                return [array_shift($parts) => $parts];
            });

        $this->assertSame(["'self'"], $policy['default-src']);
        $this->assertSame(["'self'"], $policy['frame-ancestors']);
        $this->assertSame(["'none'"], $policy['object-src']);
        $this->assertNotContains('*', $policy['script-src']);
        $this->assertNotContains('https:', $policy['script-src']);
        $this->assertNotContains("'unsafe-eval'", $policy['script-src']);
        // The login page's reCAPTCHA is the one outside script it needs.
        $this->assertContains('https://www.google.com', $policy['script-src']);

        // Inline <script> and <style> blocks run only with this response's
        // nonce; images load only from here and Google profile pictures.
        $this->assertNotContains("'unsafe-inline'", $policy['script-src']);
        $this->assertNotContains("'unsafe-inline'", $policy['style-src']);
        $this->assertNotContains('https:', $policy['img-src']);
        $this->assertContains('https://lh3.googleusercontent.com', $policy['img-src']);
    }

    public function test_every_inline_block_on_the_page_carries_the_policy_nonce(): void
    {
        $response = $this->get('/login');
        $this->assertEveryInlineBlockCarriesTheNonce($response);

        $this->assertNotSame($this->nonceOf($response), $this->nonceOf($this->get('/login')), 'a new nonce every response');
    }

    public function test_the_drive_picker_hands_google_the_nonce_for_the_stylesheet_it_adds(): void
    {
        $capsa = CapstoneExpert::factory()->create(['approved_status' => true, 'is_admin' => true]);
        $capsa->roles()->attach(Role::firstOrCreate(['role_name' => 'capstone_adviser'])->role_id);

        $response = $this->actingAs($capsa, 'expert')->get('/capsa/resources');

        $this->assertEveryInlineBlockCarriesTheNonce($response);
        $response->assertSee("gsi.nonce = document.querySelector('script[nonce]')?.nonce;", false);
    }

    public function test_the_pop_up_forms_carry_the_token_a_scan_looks_for(): void
    {
        $views = collect(File::allFiles(resource_path('views')))->map(fn ($file): string => $file->getContents())->implode("\n");

        // A scan sends a method="dialog" form as a link, token in the address.
        $this->assertStringNotContainsString('<form method="dialog"', $views);

        $hub = File::get(resource_path('views/capsa/team-management/_hub-script.blade.php'));
        $title = File::get(resource_path('views/capsa/team-management/title.blade.php'));
        $this->assertStringContainsString('<form method="post">@csrf', $hub);
        $this->assertStringContainsString('<form method="post" id="rec-form">@csrf', $title);
        $this->assertStringContainsString('<form method="post" id="rej-form">@csrf', $title);
    }

    private function assertEveryInlineBlockCarriesTheNonce($response): void
    {
        $nonce = $this->nonceOf($response);
        $this->assertNotEmpty($nonce);

        preg_match_all('/<(script|style)\b([^>]*)>/', $response->getContent(), $tags, PREG_SET_ORDER);
        $inline = array_filter($tags, fn (array $tag): bool => ! str_contains($tag[2], 'src=') && ! str_contains($tag[2], 'application/json'));
        $this->assertNotEmpty($inline);
        foreach ($inline as $tag) {
            $this->assertStringContainsString('nonce="'.$nonce.'"', $tag[0]);
        }
    }

    public function test_a_redirect_carries_no_page_and_no_script_readable_cookie_is_set(): void
    {
        $response = $this->get('/');

        $response->assertRedirect();
        $this->assertSame('', $response->getContent());
        $this->assertNull(collect($response->headers->getCookies())->first(fn ($cookie) => $cookie->getName() === 'XSRF-TOKEN'));
        $this->assertNull(collect($this->get('/login')->headers->getCookies())->first(fn ($cookie) => $cookie->getName() === 'XSRF-TOKEN'));
    }

    /**
     * A block written without the nonce is refused by the browser, with no
     * error on the page: the evaluation styles went missing that way and an
     * empty My Evaluations drew its star icon the width of the screen.
     * Emails and PDFs are not served under the policy, so they are left out.
     */
    public function test_no_view_writes_an_inline_block_without_the_nonce(): void
    {
        $missing = collect(File::allFiles(resource_path('views')))
            ->reject(fn ($file) => preg_match('#(^|[/\\\\])(emails|pdf)[/\\\\]|pdf\.blade\.php$#', $file->getRelativePathname()))
            ->filter(fn ($file) => preg_match('#^\s*<(style|script)>#m', $file->getContents()))
            ->map(fn ($file) => $file->getRelativePathname())
            ->values()
            ->all();

        $this->assertSame([], $missing);
    }

    private function nonceOf($response): ?string
    {
        preg_match("/'nonce-([^']+)'/", (string) $response->headers->get('Content-Security-Policy'), $nonce);

        return $nonce[1] ?? null;
    }

    public function test_the_dev_server_is_not_refused_while_it_runs(): void
    {
        $hot = storage_path('framework/testing/hot');
        @mkdir(dirname($hot), 0777, true);
        file_put_contents($hot, 'http://localhost:5173');
        Vite::useHotFile($hot);

        try {
            $this->get('/login')->assertHeader('Content-Security-Policy', "frame-ancestors 'self'");
        } finally {
            @unlink($hot);
        }
    }

    public function test_a_signed_in_page_and_the_api_carry_them_too(): void
    {
        $student = Student::factory()->withCompleteProfile()->withTourCompleted()->create();

        $this->actingAs($student, 'web')->get(route('student.dashboard'))->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $this->actingAs($student, 'web')->getJson('/api/student/financial')->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_the_api_answers_cross_site_requests_only_from_capstrack_itself(): void
    {
        config(['cors.allowed_origins' => ['https://capstrack.example.edu']]);

        // Named origin only - never '*' and never the asking site - so the
        // browser refuses the other site's request.
        $allowed = $this->withHeaders(['Origin' => 'https://evil.example', 'Access-Control-Request-Method' => 'GET'])
            ->options('/api/student/financial')
            ->headers->get('Access-Control-Allow-Origin');
        $this->assertNotContains($allowed, ['*', 'https://evil.example']);

        $this->withHeaders(['Origin' => 'https://capstrack.example.edu', 'Access-Control-Request-Method' => 'GET'])
            ->options('/api/student/financial')
            ->assertHeader('Access-Control-Allow-Origin', 'https://capstrack.example.edu');
    }
}
