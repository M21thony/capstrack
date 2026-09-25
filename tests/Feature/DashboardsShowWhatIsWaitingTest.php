<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\CapstonePreProposal;
use App\Models\DuplicationCheck;
use App\Models\Role;
use App\Models\StudentSchoolYear;
use App\Support\SectionCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsChecklists;
use Tests\TestCase;

/**
 * Each dashboard opens on what is waiting for that person: a CaPSA sees the
 * work in their own sections, an Admin sees what the system still needs.
 */
class DashboardsShowWhatIsWaitingTest extends TestCase
{
    use BuildsChecklists;
    use RefreshDatabase;

    public function test_a_capsa_sees_the_work_waiting_in_their_own_sections(): void
    {
        $capsa = $this->capsaFor(['BSIT-3A']);
        [$mine] = $this->teamInSection('Alpha');
        [$theirs] = $this->teamInSection('Outsider', yearSection: '4th · A');
        $mine->update(['team_status' => 'complete', 'is_confirmed' => false]);
        $theirs->update(['team_status' => 'complete', 'is_confirmed' => false]);

        foreach ([$mine, $theirs] as $team) {
            $check = DuplicationCheck::create([
                'student_s_y_id' => StudentSchoolYear::where('team_id', $team->team_id)->value('s_y_id'), 'document_path' => 'checks/1.pdf', 'concepts' => [],
                'top_score' => 10, 'verdict' => 'Passed', 'matches' => [], 'instructors_suggested' => [],
            ]);
            CapstonePreProposal::create([
                'team_id' => $team->team_id, 'duplication_check_id' => $check->id, 'slot' => 1, 'title' => 'A title',
                'sdg_goals' => 'SDG 4', 'research_agenda' => ['x'], 'problem_statement' => 'p', 'proposed_solution' => 's',
                'scope' => 'sc', 'limitations' => 'l', 'sdm' => 'Agile', 'submitted_at' => now(),
            ]);
        }

        $this->actingAs($capsa, 'expert')
            ->get(route('capsa.dashboard'))
            ->assertOk()
            ->assertSee('Teams to Verify')
            ->assertDontSee('Capstones Submitted per Year')
            ->assertViewHas('capsa', fn ($c) => $c['toVerify'] === 1 && $c['titles'] === 1 && $c['teamCount'] === 1)
            ->assertViewHas('admin', null);
    }

    public function test_an_admin_sees_sections_nobody_covers(): void
    {
        SectionCatalog::store(['BSIT-3A', 'BSIT-3B']);
        $this->capsaFor(['BSIT-3A']);
        $admin = CapstoneExpert::factory()->create(['approved_status' => true, 'is_admin' => true]);
        $admin->roles()->detach(Role::where('role_name', CapstoneExpert::CAPSA_ROLE)->value('role_id'));

        $this->actingAs($admin, 'expert')
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Sections Without a CaPSA')
            ->assertSee('BSIT-3B')
            ->assertViewHas('admin', fn ($a) => $a['uncovered'] === ['BSIT-3B'] && $a['capsas'] === 1)
            ->assertViewHas('capsa', null);
    }
}
