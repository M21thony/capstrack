<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\CapstonePreProposal;
use App\Models\DuplicationCheck;
use App\Models\Notification;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Models\TeamExpertAssignment;
use App\Support\ExpertEvaluations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Panel comment 6: students are told who someone is to them (adviser,
 * consultant, grammarian, or their name), not "the expert". The word stays
 * only where it means all of them together, like Find Experts.
 */
class RoleNamesNotExpertTest extends TestCase
{
    use RefreshDatabase;

    private function expert(string $role, string $first = 'Ana', string $last = 'Santos'): CapstoneExpert
    {
        $expert = CapstoneExpert::factory()->create([
            'first_name' => $first, 'last_name' => $last,
            'approved_status' => true, 'max_teams' => 4, 'program_slots' => '4 - BSIT',
        ]);
        $expert->roles()->attach(Role::firstOrCreate(['role_name' => $role])->role_id);

        return $expert;
    }

    private function roleId(CapstoneExpert $expert, string $role): int
    {
        return (int) DB::table('expert_roles')->where('expert_id', $expert->expert_id)
            ->where('role_id', Role::where('role_name', $role)->value('role_id'))->value('expert_role_id');
    }

    /** A BSIT 3A leader with a recommended pre-proposal, and the section's CaPSA. */
    private function leader(): array
    {
        $leader = Student::factory()->withCompleteProfile()->create([
            'course' => 'Bachelor of Science in Information Technology', 'year_section' => '3rd · A',
        ]);
        $team = Team::create(['team_name' => 'Bulbasaur', 'team_status' => 'Active', 'is_confirmed' => true, 'leader_id' => $leader->student_id, 'member_count' => 5]);
        $sy = StudentSchoolYear::create(['student_id' => $leader->student_id, 'team_id' => $team->team_id]);

        $check = DuplicationCheck::create([
            'student_s_y_id' => $sy->s_y_id, 'document_path' => 'checks/a.pdf', 'concepts' => [], 'top_score' => 20,
            'verdict' => 'Passed', 'matches' => [], 'instructors_suggested' => [],
        ]);
        $proposal = CapstonePreProposal::create([
            'team_id' => $team->team_id, 'duplication_check_id' => $check->id, 'slot' => 1, 'title' => 'Clinic Queue',
            'sdg_goals' => '3', 'research_agenda' => [], 'problem_statement' => 'Long lines.', 'proposed_solution' => 'A system.',
            'scope' => 'One clinic.', 'limitations' => 'None.', 'sdm' => 'Agile', 'approved_status' => true, 'submitted_at' => now(),
        ]);
        $team->update(['selected_pre_proposal_id' => $proposal->id, 'project_title' => $proposal->title]);
        $this->makeCapsa(CapstoneExpert::factory()->create(['approved_status' => true, 'sections' => ['BSIT-3A']]));

        return [$leader, $team];
    }

    private function engage(Team $team, CapstoneExpert $expert, string $role): TeamExpertAssignment
    {
        return TeamExpertAssignment::create([
            'team_id' => $team->team_id, 'expert_id' => $expert->expert_id,
            'expert_role_id' => $this->roleId($expert, $role), 'status' => 'approved',
        ]);
    }

    public function test_the_evaluation_reminder_names_only_the_roles_owed_in_capstone_order(): void
    {
        $rows = fn (string ...$roles) => collect($roles)->map(fn ($r) => (object) ['role' => $r]);

        $this->assertSame('adviser, consultant and grammarian', ExpertEvaluations::whom($rows('grammarian', 'adviser', 'consultant')));
        $this->assertSame('adviser and grammarian', ExpertEvaluations::whom($rows('grammarian', 'adviser', 'adviser')));
        $this->assertSame('consultant', ExpertEvaluations::whom($rows('consultant')));
    }

    public function test_find_experts_says_adviser_consultant_and_grammarian(): void
    {
        [$leader] = $this->leader();

        $this->actingAs($leader, 'web')->get(route('student.experts'))
            ->assertOk()
            ->assertSee("Your team's adviser, consultant and grammarian", false)
            ->assertSee("To change your adviser, consultant or grammarian later, ask your section's CaPSA.", false)
            ->assertSee('Once your adviser or consultant accepts, their open dates show up here.')
            ->assertDontSee("Your team's experts", false)
            ->assertDontSee('To change an expert later')
            ->assertDontSee('waiting for the expert')
            ->assertDontSee('Message to the expert');

        $this->actingAs($leader, 'web')->get(route('student.dashboard'))
            ->assertOk()
            ->assertDontSee('Capstone Expert');
    }

    public function test_a_waiting_application_names_who_it_waits_on(): void
    {
        [$leader] = $this->leader();
        $rosa = $this->expert('adviser', 'Rosa', 'Villanueva');

        $this->actingAs($leader, 'web')->postJson('/api/assignments', [
            'expert_id' => $rosa->expert_id, 'expert_role_id' => $this->roleId($rosa, 'adviser'),
            'message' => 'We are Bulbasaur, building a clinic queue system.',
        ])->assertOk()->assertJson(['message' => 'Application sent. Waiting for '.$rosa->name.' to answer.']);

        // The pending pill on Find Experts is built from the request's own name and role.
        $this->actingAs($leader, 'web')->get(route('student.experts'))
            ->assertOk()
            ->assertSee("Pending · waiting for ' + esc(a.name)", false);
    }

    public function test_a_removed_grammarian_is_named_by_role_and_name(): void
    {
        [$leader, $team] = $this->leader();
        $ana = $this->expert('grammarian');
        $this->engage($team, $ana, 'grammarian');
        $admin = $this->makeCapsa(CapstoneExpert::create([
            'email' => 'admin@cspc.edu.ph', 'first_name' => 'Ad', 'last_name' => 'Min', 'is_admin' => true, 'approved_status' => true,
        ]));

        $this->actingAs($admin, 'expert')->deleteJson('/admin/experts/'.$ana->expert_id, ['reason' => 'Retired'])->assertOk();

        $title = 'Your grammarian, '.$ana->name.', is no longer available';
        $this->assertSame($title, Notification::where('student_id', $leader->student_id)->where('type', 'expert_removed')->value('title'));

        $this->actingAs($leader, 'web')->get(route('student.team'))
            ->assertOk()
            ->assertSee('No longer with your team')
            ->assertSee($title, false);
    }

    public function test_changing_a_consultant_says_consultant(): void
    {
        [$leader, $team] = $this->leader();
        $assignment = $this->engage($team, $this->expert('consultant'), 'consultant');
        $other = $this->expert('consultant', 'Ben', 'Cruz');
        $ask = fn (array $overrides) => $this->actingAs($leader, 'web')->deleteJson(route('api.student.assignments.destroy', $assignment), $overrides + [
            'reason' => 'Our consultant moved to another campus this semester.',
            'replacement_expert_id' => $other->expert_id,
            'replacement_message' => 'Would you consult for us?',
        ]);

        $ask(['replacement_expert_id' => ''])->assertStatus(422)->assertJson(['message' => 'Pick the consultant you want instead.']);
        $ask(['replacement_message' => ''])->assertStatus(422)->assertJson(['message' => 'Write a short message to the consultant you picked.']);

        // The dialog builds its words from the role on the button.
        $this->actingAs($leader, 'web')->get(route('student.experts'))
            ->assertOk()
            ->assertSee("'Request a ' + esc(role) + ' change'", false)
            ->assertSee("why your ' + esc(role) + ' has to change.", false);
    }
}
