<?php

namespace Tests\Feature;

use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
                ->assertHeader('Content-Security-Policy', "frame-ancestors 'self'")
                ->assertHeader('X-Content-Type-Options', 'nosniff')
                ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
                ->assertHeaderMissing('X-Powered-By');
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
