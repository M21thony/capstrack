<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\ExpertRole;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Models\TeamExpertAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The student-facing expert page has to show WHICH role is in play - the
 * one being applied for, and the one an approved expert already holds for
 * the team. Both used to be guessed from the expert's qualifications.
 */
class StudentExpertsPageRolesTest extends TestCase
{
    use RefreshDatabase;

    private function makeLeaderWithTeam(string $email = 'leader@my.cspc.edu.ph'): array
    {
        $student = Student::create([
            'email' => $email,
            'f_name' => 'Team',
            'l_name' => 'Leader',
        ]);

        $team = Team::create([
            'team_name' => 'Applying Team',
            'team_status' => 'Active',
            'leader_id' => $student->student_id,
            'is_confirmed' => true,
            'member_count' => 5,
        ]);

        StudentSchoolYear::create(['student_id' => $student->student_id, 'team_id' => $team->team_id]);

        return [$student, $team];
    }

    private function makeExpertWithRoles(array $roleNames): CapstoneExpert
    {
        $expert = CapstoneExpert::factory()->create(['approved_status' => true, 'max_teams' => 4]);

        foreach ($roleNames as $roleName) {
            $expert->roles()->attach(Role::firstOrCreate(['role_name' => $roleName])->role_id);
        }

        return $expert;
    }

    private function expertRoleId(CapstoneExpert $expert, string $roleName): int
    {
        return (int) ExpertRole::where('expert_id', $expert->expert_id)
            ->where('role_id', Role::where('role_name', $roleName)->value('role_id'))
            ->value('expert_role_id');
    }

    public function test_the_apply_button_offers_every_role_the_expert_holds(): void
    {
        [$student] = $this->makeLeaderWithTeam();
        $expert = $this->makeExpertWithRoles(['adviser', 'consultant']);

        $response = $this->actingAs($student, 'web')->get('/student/experts');

        $response->assertStatus(200);

        // Both roles reach the apply modal, each carrying the expert_roles
        // key the application will record.
        // Js::from() escapes the quotes as \u0022 inside the JSON.parse
        // string it emits, so match on that rather than a bare quote.
        $response->assertSee('expert_role_id\u0022:'.$this->expertRoleId($expert, 'adviser'), false);
        $response->assertSee('expert_role_id\u0022:'.$this->expertRoleId($expert, 'consultant'), false);
        $response->assertSee('An expert can take only one role per team');
    }

    // capstone_adviser is a portal permission, not something a team engages
    // someone for, so it must never appear as a choice.
    public function test_the_apply_button_does_not_offer_the_capsa_role(): void
    {
        [$student] = $this->makeLeaderWithTeam();
        $expert = $this->makeExpertWithRoles(['consultant', 'capstone_adviser']);

        $response = $this->actingAs($student, 'web')->get('/student/experts');

        $response->assertStatus(200);
        $response->assertDontSee('expert_role_id\u0022:'.$this->expertRoleId($expert, 'capstone_adviser'), false);
    }

    public function test_an_approved_expert_is_labelled_with_the_role_the_team_engaged_them_as(): void
    {
        [$student, $team] = $this->makeLeaderWithTeam();
        $expert = $this->makeExpertWithRoles(['adviser', 'consultant']);

        TeamExpertAssignment::create([
            'team_id' => $team->team_id,
            'expert_id' => $expert->expert_id,
            'expert_role_id' => $this->expertRoleId($expert, 'consultant'),
            'status' => 'approved',
        ]);

        $response = $this->actingAs($student, 'web')->get('/student/experts');

        $response->assertStatus(200);

        // The expert is qualified as an adviser too, so a label reading
        // "Adviser" here would mean the old guess is still in play.
        $this->assertStringContainsString(
            'Consultant',
            $this->teamExpertsSection($response->getContent()),
        );

        // An approved expert belongs in the slots above and nowhere else.
        // They used to appear three times over - in the slots, still in
        // Request Experts as though undecided, and still in the grid below
        // as though available to ask.
        $response->assertDontSee('Applied as consultant');
        $this->assertStringNotContainsString(
            'data-expert-id="'.$expert->expert_id.'"',
            $response->getContent(),
        );
    }

    /**
     * The Request Experts section is only rendered when there is something in
     * it, so a pending request has to be what puts it there - otherwise a
     * student who has applied to someone has nowhere on this page to see it.
     */
    public function test_a_pending_request_is_visible_on_the_page(): void
    {
        [$student, $team] = $this->makeLeaderWithTeam();
        $expert = $this->makeExpertWithRoles(['consultant']);

        TeamExpertAssignment::create([
            'team_id' => $team->team_id,
            'expert_id' => $expert->expert_id,
            'expert_role_id' => $this->expertRoleId($expert, 'consultant'),
            'status' => 'pending',
        ]);

        $response = $this->actingAs($student, 'web')->get('/student/experts');

        $response->assertStatus(200);

        // The request itself, named and labelled, not merely counted.
        $response->assertSee('class="req-section"', false);
        $response->assertSee($expert->name, false);
        $response->assertSee('Applied as consultant');
        $response->assertSee('Pending');

        // And the summary line above it agrees.
        $response->assertSee('request waiting on a reply');
    }

    /**
     * With nothing requested the section stays away - but the page still has
     * to say so somewhere, or there is no way to find out where requests
     * appear before you have made one.
     */
    public function test_the_request_section_is_absent_but_still_accounted_for(): void
    {
        [$student] = $this->makeLeaderWithTeam();

        $response = $this->actingAs($student, 'web')->get('/student/experts');

        $response->assertStatus(200);
        $response->assertDontSee('class="req-section"', false);
        $response->assertDontSee('waiting on a reply');

        // The signpost stays put at zero.
        $response->assertSee('No requests out yet.');
    }

    /** The "Your Team's Experts" panel only. */
    private function teamExpertsSection(string $html): string
    {
        $start = strpos($html, 'Your Team\'s Experts');
        $end = strpos($html, 'sticky-controls', $start ?: 0);

        return $start === false || $end === false ? '' : substr($html, $start, $end - $start);
    }
}
