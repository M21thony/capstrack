<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The CaPSA, Admin and Expert portals get the guided tour students have: their
 * own steps, open by themselves once, and replayable from Portal Tour.
 */
class StaffPortalTourTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<int, array<string, mixed>> */
    private function steps(string $html): array
    {
        preg_match('#<script type="application/json" id="gt-steps">(.*?)</script>#s', $html, $m);

        return json_decode($m[1] ?? '[]', true);
    }

    public function test_a_capsa_gets_the_capsa_tour_pointing_at_their_own_menu(): void
    {
        $capsa = $this->makeCapsa(CapstoneExpert::factory()->create(['approved_status' => true, 'is_admin' => false, 'sections' => ['BSIT-3A'], 'first_name' => 'Maria']));

        $html = $this->actingAs($capsa, 'expert')->get(route('capsa.dashboard'))
            ->assertOk()
            ->assertSee('id="portal-tour"', false)
            ->assertSee('data-tour-key="capsa"', false)
            ->assertSee('data-auto-open="1"', false)
            ->assertSee('data-save-url="'.route('instructor.tour.update').'"', false)
            ->assertSee('onclick="openPortalTour()"', false)
            ->assertSee(asset('css/portal-tour.css'), false)
            ->getContent();

        $steps = $this->steps($html);
        $this->assertSame('Welcome, Maria!', $steps[0]['label']);
        $teams = collect($steps)->firstWhere('key', 'teams');
        $this->assertSame(['.sidebar a.nav-item[href$="/capsa/team-management"]'], $teams['target']);
    }

    public function test_an_admin_gets_the_admin_tour(): void
    {
        $admin = CapstoneExpert::factory()->create(['approved_status' => true, 'is_admin' => true]);

        $html = $this->actingAs($admin, 'expert')->get(route('admin.students'))
            ->assertOk()
            ->assertSee('data-tour-key="admin"', false)
            ->getContent();

        $this->assertContains('experts', collect($this->steps($html))->pluck('key')->all());
    }

    public function test_an_expert_gets_the_expert_tour(): void
    {
        $expert = CapstoneExpert::factory()->create(['approved_status' => true, 'is_admin' => false]);

        $html = $this->actingAs($expert, 'expert')->get(route('instructor.grading'))
            ->assertOk()
            ->assertSee('data-tour-key="expert"', false)
            ->assertSee('onclick="openPortalTour()"', false)
            ->getContent();

        $this->assertSame(
            ['welcome', 'profile', 'dashboard', 'applications', 'teams', 'reviews', 'schedule', 'grading', 'bell', 'done'],
            collect($this->steps($html))->pluck('key')->all()
        );
    }

    public function test_finishing_it_stops_it_opening_by_itself_but_it_can_still_be_replayed(): void
    {
        $expert = CapstoneExpert::factory()->create(['approved_status' => true, 'is_admin' => false]);

        $this->actingAs($expert, 'expert')->putJson(route('instructor.tour.update'))->assertOk();
        $first = $expert->fresh()->tour_completed_at;
        $this->assertNotNull($first);

        // A replay later keeps the first time.
        $this->travel(2)->days();
        $this->actingAs($expert, 'expert')->putJson(route('instructor.tour.update'))->assertOk();
        $this->assertTrue($first->equalTo($expert->fresh()->tour_completed_at));

        $this->actingAs($expert, 'expert')->get(route('instructor.grading'))
            ->assertOk()
            ->assertSee('id="portal-tour"', false)
            ->assertSee('data-auto-open="0"', false);
    }

    public function test_only_a_signed_in_expert_can_record_it(): void
    {
        // Sent to sign in, the way every expert page treats a signed-out visit.
        $this->putJson(route('instructor.tour.update'))->assertRedirect();
    }
}
