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
 * Experts and Consultation are one page with two tabs. A team with nobody to
 * book lands on finding experts; once someone has accepted them, on booking.
 */
class ExpertsPageTabsTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: Student, 1: Team} */
    private function leader(): array
    {
        $student = Student::create([
            'email' => 'leader@my.cspc.edu.ph',
            'f_name' => 'Team',
            'l_name' => 'Leader',
        ]);

        $team = Team::create([
            'team_name' => 'Tabbed Team',
            'team_status' => 'Active',
            'leader_id' => $student->student_id,
            'is_confirmed' => true,
            'member_count' => 5,
        ]);

        StudentSchoolYear::create(['student_id' => $student->student_id, 'team_id' => $team->team_id]);

        return [$student, $team];
    }

    private function expert(string $first, string $role, int $maxTeams = 4): CapstoneExpert
    {
        $expert = CapstoneExpert::factory()->create([
            'first_name' => $first,
            'approved_status' => true,
            'max_teams' => $maxTeams,
            'program_slots' => $maxTeams.' - BSIT',
        ]);

        $expert->roles()->attach(Role::firstOrCreate(['role_name' => $role])->role_id);

        return $expert;
    }

    private function accept(Team $team, CapstoneExpert $expert): void
    {
        TeamExpertAssignment::create([
            'team_id' => $team->team_id,
            'expert_id' => $expert->expert_id,
            'expert_role_id' => ExpertRole::where('expert_id', $expert->expert_id)->value('expert_role_id'),
            'status' => 'approved',
        ]);
    }

    public function test_a_team_with_no_expert_opens_on_find_experts(): void
    {
        [$student] = $this->leader();
        $this->expert('Neil', 'adviser');

        $response = $this->actingAs($student, 'web')->get(route('student.experts'))->assertOk();

        $this->assertSame('find', $response->viewData('tab'));
        $response->assertSee('id="xh-panel-book" role="tabpanel" aria-labelledby="xh-tab-book"  hidden', false);
    }

    public function test_a_team_with_an_expert_opens_on_consultations(): void
    {
        [$student, $team] = $this->leader();
        $this->accept($team, $this->expert('Neil', 'adviser'));

        $response = $this->actingAs($student, 'web')->get(route('student.experts'))->assertOk();

        $this->assertSame('consultations', $response->viewData('tab'));
        $this->assertCount(1, $response->viewData('consultExperts'));
    }

    /** A search or a role in the address is someone browsing, whatever the team has. */
    public function test_a_search_opens_on_find_experts_even_with_an_expert(): void
    {
        [$student, $team] = $this->leader();
        $this->accept($team, $this->expert('Neil', 'adviser'));

        $this->assertSame('find', $this->actingAs($student, 'web')
            ->get(route('student.experts', ['q' => 'neil']))->viewData('tab'));
        $this->assertSame('find', $this->actingAs($student, 'web')
            ->get(route('student.experts', ['role' => 'grammarian']))->viewData('tab'));
    }

    /** The old Consultation link still works, and opens the booking tab. */
    public function test_the_consultation_address_opens_the_consultations_tab(): void
    {
        [$student] = $this->leader();

        $response = $this->actingAs($student, 'web')->get(route('student.consultation'))->assertOk();

        $this->assertSame('consultations', $response->viewData('tab'));
        $response->assertSee('<title>Experts - CapsTrack</title>', false);
    }

    /** One sidebar link for the one page, lit on either address. */
    public function test_the_sidebar_has_one_link_for_experts_and_consultations(): void
    {
        [$student] = $this->leader();

        foreach (['student.experts', 'student.consultation'] as $route) {
            $html = $this->actingAs($student, 'web')->get(route($route))->assertOk()->getContent();

            $this->assertSame(1, substr_count($html, 'Experts &amp; Consultations'), $route);
            $this->assertStringContainsString('data-tour-target="student.experts" class="nav-item active"', $html);
            $this->assertStringNotContainsString('data-tour-target="student.consultation"', $html);
        }
    }

    public function test_an_unknown_tab_falls_back_rather_than_breaking(): void
    {
        [$student] = $this->leader();

        $this->assertSame('find', $this->actingAs($student, 'web')
            ->get(route('student.experts', ['tab' => 'wizard']))->assertOk()->viewData('tab'));
    }

    public function test_has_open_slots_leaves_out_full_experts_and_those_who_set_no_capacity(): void
    {
        [$student, $team] = $this->leader();

        $open = $this->expert('Opal', 'adviser', 3);
        $full = $this->expert('Fuller', 'adviser', 1);
        $unset = $this->expert('Unsetta', 'adviser', 0);

        // Another team has taken Fuller's only slot.
        $other = Team::create(['team_name' => 'Other Team', 'team_status' => 'Active', 'is_confirmed' => true]);
        $this->accept($other, $full);

        $html = $this->actingAs($student, 'web')
            ->get(route('student.experts', ['role' => 'adviser', 'open' => 1]))->assertOk()->getContent();

        $this->assertStringContainsString('data-expert-id="'.$open->expert_id.'"', $html);
        $this->assertStringNotContainsString('data-expert-id="'.$full->expert_id.'"', $html);
        $this->assertStringNotContainsString('data-expert-id="'.$unset->expert_id.'"', $html);

        // Without the filter, all three are listed.
        $all = $this->actingAs($student, 'web')
            ->get(route('student.experts', ['role' => 'adviser']))->getContent();

        foreach ([$open, $full, $unset] as $expert) {
            $this->assertStringContainsString('data-expert-id="'.$expert->expert_id.'"', $all);
        }
    }
}
