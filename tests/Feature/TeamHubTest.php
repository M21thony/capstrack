<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\CapstonePreProposal;
use App\Models\DuplicationCheck;
use App\Models\ExpertRole;
use App\Models\MemberLeaveRequest;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Models\TeamExpertAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Teams hub - To do and Teams - for a CaPSA (Team Management), for an
 * expert (My Teams), and for a CaPSA who is an expert too (one page, not two).
 */
class TeamHubTest extends TestCase
{
    use RefreshDatabase;

    private int $students = 0;

    private function capsa(array $sections = ['BSIT-3A']): CapstoneExpert
    {
        return $this->makeCapsa(CapstoneExpert::factory()->create([
            'approved_status' => true, 'is_admin' => false, 'sections' => $sections,
        ]));
    }

    private function expert(string ...$roles): CapstoneExpert
    {
        $expert = CapstoneExpert::factory()->create(['approved_status' => true, 'is_admin' => false]);
        $this->giveRoles($expert, ...$roles);

        return $expert;
    }

    private function giveRoles(CapstoneExpert $expert, string ...$roles): void
    {
        foreach ($roles as $role) {
            $expert->roles()->attach(Role::firstOrCreate(['role_name' => $role])->role_id);
        }
    }

    /** A team whose leader puts it in a section: BSIT-3A by default. */
    private function team(string $name, string $course = 'Bachelor of Science in Information Technology', string $yearSection = '3rd · A'): array
    {
        $leader = Student::factory()->withCompleteProfile()->create([
            'email' => 'leader'.(++$this->students).'@my.cspc.edu.ph',
            'course' => $course,
            'year_section' => $yearSection,
        ]);

        $team = Team::create([
            'team_name' => $name, 'team_status' => 'Active', 'leader_id' => $leader->student_id,
            'is_confirmed' => false, 'member_count' => 4,
        ]);

        $sy = StudentSchoolYear::create(['student_id' => $leader->student_id, 'team_id' => $team->team_id]);

        return [$team, $leader, $sy];
    }

    private function assign(CapstoneExpert $expert, Team $team, string $role, string $status = 'approved'): TeamExpertAssignment
    {
        return TeamExpertAssignment::create([
            'team_id' => $team->team_id,
            'expert_id' => $expert->expert_id,
            'expert_role_id' => ExpertRole::where('expert_id', $expert->expert_id)
                ->where('role_id', Role::where('role_name', $role)->value('role_id'))
                ->value('expert_role_id'),
            'status' => $status,
        ]);
    }

    private function proposal(Team $team, StudentSchoolYear $sy, int $slot): CapstonePreProposal
    {
        $check = DuplicationCheck::create([
            'student_s_y_id' => $sy->s_y_id, 'document_path' => "checks/{$slot}.pdf", 'concepts' => ['title' => 'T'],
            'top_score' => 20, 'verdict' => 'Low Similarity', 'matches' => [], 'instructors_suggested' => [],
        ]);

        return CapstonePreProposal::create([
            'team_id' => $team->team_id, 'duplication_check_id' => $check->id, 'slot' => $slot,
            'title' => "Title {$slot} for a Barangay Health Record System", 'sdg_goals' => 'SDG 4',
            'research_agenda' => ['Applications and Systems Development'], 'problem_statement' => 'A problem.',
            'proposed_solution' => 'A solution.', 'scope' => 'A scope.', 'limitations' => 'A limitation.',
            'sdm' => 'Agile', 'approved_status' => false, 'submitted_at' => now(),
        ]);
    }

    public function test_team_management_opens_on_to_do_with_the_titles_waiting_on_her(): void
    {
        $capsa = $this->capsa();
        [$team, , $sy] = $this->team('Team Kalinga');
        $first = $this->proposal($team, $sy, 1);
        $this->proposal($team, $sy, 2);

        $response = $this->actingAs($capsa, 'expert')->get(route('capsa.team_management'));

        $this->assertMatchesRegularExpression('/data-hub-tab="todo"\s+aria-selected="true"/', $response->getContent());
        $response->assertOk()
            ->assertSeeInOrder(['class="group-h">Titles', 'Review 2 titles', 'Team Kalinga', 'BSIT-3A'], false)
            ->assertSee('href="'.route('capsa.team_management.title', [$team->team_id, $first->id]).'">Review titles', false)
            ->assertSee('1 to do')
            // The same team in Teams says what it is waiting for.
            ->assertSee('To do: Review 2 titles');
    }

    public function test_a_leave_request_is_decided_from_to_do(): void
    {
        $capsa = $this->capsa();
        [$team, $leader] = $this->team('Team Agos');
        $request = MemberLeaveRequest::create([
            'team_id' => $team->team_id, 'student_id' => $leader->student_id, 'requested_by' => $leader->student_id,
            'type' => MemberLeaveRequest::LEAVE, 'reason' => 'Transferring to the evening section this term.',
            'status' => MemberLeaveRequest::PENDING,
        ]);

        $this->actingAs($capsa, 'expert')->get(route('capsa.team_management'))
            ->assertOk()
            ->assertSeeInOrder(['class="group-h">Requests from teams', $leader->f_name.' '.$leader->l_name.' asks to leave'], false)
            ->assertSee('data-url="'.route('capsa.leave_requests.update', $request).'"', false)
            ->assertSee('data-value="'.MemberLeaveRequest::APPROVED.'"', false)
            ->assertSee('data-value="'.MemberLeaveRequest::DECLINED.'"', false);
    }

    public function test_an_empty_to_do_says_all_caught_up(): void
    {
        $html = $this->actingAs($this->capsa(), 'expert')->get(route('capsa.team_management', ['tab' => 'teams']))
            ->assertOk()
            ->assertSee('All caught up')
            ->getContent();

        $this->assertMatchesRegularExpression('/data-hub-tab="teams"\s+aria-selected="true"/', $html);
        $this->assertMatchesRegularExpression('/id="tm-todo" role="tabpanel"\s+hidden/', $html);
    }

    /** What has been settled is shown under Completed, not Decided. */
    public function test_settled_items_sit_under_completed(): void
    {
        $this->actingAs($this->capsa(), 'expert')->get(route('capsa.team_management'))
            ->assertOk()
            ->assertSee('data-todo-mode="decided" aria-pressed="false">Completed</button>', false)
            ->assertSee('Nothing completed yet')
            ->assertDontSee('>Decided</button>', false);
    }

    public function test_my_teams_lists_an_experts_invitations_and_teams(): void
    {
        $expert = $this->expert('adviser', 'consultant');
        [$advised] = $this->team('Team Bytecraft');
        [$inviting] = $this->team('Team Habi');
        [$elsewhere] = $this->team('Team Elsewhere');
        $this->assign($expert, $advised, 'adviser');
        $invite = $this->assign($expert, $inviting, 'consultant', 'pending');

        $this->actingAs($expert, 'expert')->get(route('instructor.teams'))
            ->assertOk()
            ->assertSee('<div class="tmx tm-hub" data-portal="expert">', false)
            // A shortcut to the Team Applications tab, with the application to read.
            ->assertSeeInOrder(['class="group-h group-apps"', 'Team applications', 'See all', 'Invited as consultant', 'Team Habi', 'Read application'], false)
            ->assertSee('data-read="application" data-read-id="'.$invite->assignment_id.'"', false)
            ->assertSee('data-url="'.route('instructor.assignments.update', $invite->assignment_id).'"', false)
            ->assertSee('data-note="reason" data-min="20"', false)
            // Teams: the one they advise, with their seat; not the others.
            ->assertSee('href="'.route('instructor.teams.show', $advised->team_id).'"', false)
            ->assertSee('<b class="hat">Adviser</b>', false)
            ->assertDontSee('href="'.route('instructor.teams.show', $elsewhere->team_id).'"', false)
            ->assertDontSee('href="'.route('instructor.teams.show', $inviting->team_id).'"', false)
            // Filtered by their seat, not by section.
            ->assertSee('data-role="grammarian"', false)
            ->assertDontSee('name="section"', false);
    }

    public function test_an_expert_opens_their_teams_page_without_capsa_controls(): void
    {
        $expert = $this->expert('adviser');
        [$team, , $sy] = $this->team('Team Lakbay');
        [$other] = $this->team('Team Other');
        $this->assign($expert, $team, 'adviser');
        $proposal = $this->proposal($team, $sy, 1);

        $this->actingAs($expert, 'expert')->get(route('instructor.teams.show', $team->team_id))
            ->assertOk()
            ->assertSee('Teams')
            ->assertDontSee('My Teams')
            ->assertSee($proposal->title)
            ->assertSee('once the CaPSA recommends')
            // Reviewing titles and verifying are the CaPSA's.
            ->assertDontSee(route('capsa.team_management.title', [$team->team_id, $proposal->id]), false)
            ->assertDontSee('Verify team')
            ->assertDontSee('Waiting on you');

        $this->actingAs($expert, 'expert')->get(route('instructor.teams.show', $other->team_id))->assertNotFound();
        $this->actingAs($expert, 'expert')->get(route('instructor.teams.log', [$team->team_id, 'activity']))
            ->assertOk()->assertSee('formed the team');
    }

    public function test_a_capsa_who_also_advises_gets_one_teams_page(): void
    {
        $capsa = $this->capsa();
        $this->giveRoles($capsa, 'adviser');
        [$mine] = $this->team('Team Sinag');
        [$advised, , $sy] = $this->team('Team Outside', 'Bachelor of Science in Computer Science', '4th · A');
        $this->assign($capsa, $advised, 'adviser');
        $proposal = $this->proposal($advised, $sy, 1);

        $this->actingAs($capsa, 'expert')->get(route('capsa.team_management', ['tab' => 'teams']))
            ->assertOk()
            ->assertSee('href="'.route('capsa.team_management.show', $mine->team_id).'"', false)
            ->assertSee('href="'.route('capsa.team_management.show', $advised->team_id).'"', false)
            ->assertSee('data-quick="mine"', false)
            ->assertSee('<b class="hat">Adviser</b>', false)
            // The labels say the seat, never "expert".
            ->assertDontSee('You are an expert')
            // Not her section, so its titles are not hers to review.
            ->assertDontSee('Review 1 title');

        $this->actingAs($capsa, 'expert')->get(route('capsa.team_management.show', $advised->team_id))->assertOk();
        $this->actingAs($capsa, 'expert')->get(route('capsa.team_management.title', [$advised->team_id, $proposal->id]))->assertNotFound();
        $this->actingAs($capsa, 'expert')->get(route('instructor.teams'))
            ->assertRedirect(route('capsa.team_management', ['tab' => 'teams']));
    }
}
