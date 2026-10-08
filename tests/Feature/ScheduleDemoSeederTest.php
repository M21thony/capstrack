<?php

namespace Tests\Feature;

use App\Models\Team;
use Database\Seeders\ScheduleDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsChecklists;
use Tests\TestCase;

/** The demo data a BSIT-3A/3B CaPSA uses to show Generate schedule on the Pre-proposal Defense. */
class ScheduleDemoSeederTest extends TestCase
{
    use BuildsChecklists;
    use RefreshDatabase;

    /** The CaPSA has the department checklist, whose forms come before the Pre-proposal Defense. */
    /** A missing grammarian is only a warning, so the team without one is still given a date. */
    public function test_generate_schedule_suggests_every_demo_team_with_a_panel(): void
    {
        $capsa = $this->capsaFor(['BSIT-3A', 'BSIT-3B']);
        $this->departmentChecklist($capsa);
        $this->seed(ScheduleDemoSeeder::class);

        $data = $this->actingAs($capsa, 'expert')
            ->postJson(route('api.admin.panel_assignment.schedule-drafts.store'), ['defense_type' => 'Pre-proposal Defense'])
            ->assertOk()
            ->json('data');

        $this->assertCount(9, $data['draft']);
        $this->assertContains('Demo Script Kiddos', array_column($data['draft'], 'team_name'));
        $this->assertSame(['Demo Bug Busters' => 'No panel assigned yet.'], collect($data['not_ready'])->pluck('reason', 'team_name')->all());
    }

    public function test_running_it_twice_adds_nothing_the_second_time(): void
    {
        $this->seed(ScheduleDemoSeeder::class);
        $this->seed(ScheduleDemoSeeder::class);

        $this->assertSame(10, Team::where('team_name', 'like', 'Demo %')->count());
    }
}
