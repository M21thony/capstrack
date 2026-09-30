<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\Team;
use Database\Seeders\ScheduleDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The demo data a BSIT-3C/3D CaPSA uses to show Generate schedule on the Pre-proposal Defense. */
class ScheduleDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_generate_schedule_suggests_the_eight_ready_demo_teams_and_lists_the_other_two(): void
    {
        $this->seed(ScheduleDemoSeeder::class);
        $capsa = $this->makeCapsa(CapstoneExpert::factory()->create(['approved_status' => true, 'sections' => ['BSIT-3C', 'BSIT-3D']]));

        $data = $this->actingAs($capsa, 'expert')
            ->postJson(route('api.admin.panel_assignment.schedule-drafts.store'), ['defense_type' => 'Pre-proposal Defense'])
            ->assertOk()
            ->json('data');

        $this->assertCount(8, $data['draft']);
        $notReady = collect($data['not_ready'])->pluck('reason', 'team_name');
        $this->assertSame('No panel assigned yet.', $notReady['Demo Bug Busters']);
        $this->assertStringContainsString('grammarian', $notReady['Demo Script Kiddos']);
    }

    public function test_running_it_twice_adds_nothing_the_second_time(): void
    {
        $this->seed(ScheduleDemoSeeder::class);
        $this->seed(ScheduleDemoSeeder::class);

        $this->assertSame(10, Team::where('team_name', 'like', 'Demo %')->count());
    }
}
