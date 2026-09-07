<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\StudentRole;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use Database\Seeders\StudentRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The panel that opens from the student's name in the sidebar.
 *
 * It is a small thing that kept accumulating what the sidebar beside it
 * already had: the picture, the sign-out, the name printed twice, and a
 * button to a page nobody uses. What it never had was the one thing only it
 * can show - where this student stands in their capstone.
 */
class StudentProfilePanelTest extends TestCase
{
    use RefreshDatabase;

    private function student(): Student
    {
        return Student::create([
            'email' => 'mark@my.cspc.edu.ph',
            'f_name' => 'Mark',
            'l_name' => 'Balilla',
            'course' => 'BSIT',
            'year_section' => '4th · A',
            'student_number' => '21-04512',
        ]);
    }

    private function panel(string $html): string
    {
        $at = strpos($html, 'id="profile-overlay"');

        return $at === false ? '' : substr($html, $at, strpos($html, 'id="pm-body"', $at) + 4000 - $at);
    }

    /**
     * Edit Profile pointed at the personal-info page, which is no longer part
     * of how a student gets set up - the middleware that used to force it is
     * a no-op, and Google supplies the name at sign-in. Nothing on the panel
     * is the student's to change anyway: course, section and student number
     * belong to the registrar.
     */
    public function test_the_panel_does_not_link_to_the_personal_info_page(): void
    {
        $html = $this->actingAs($this->student(), 'web')
            ->get(route('student.team'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString(route('student.personal-info.edit'), $html);
        $this->assertStringNotContainsString('Edit Profile', $html);
    }

    /** Both of these live in the sidebar the panel opens from. */
    public function test_the_panel_repeats_nothing_the_sidebar_already_has(): void
    {
        $panel = $this->panel(
            $this->actingAs($this->student(), 'web')->get(route('student.team'))->assertOk()->getContent()
        );

        $this->assertNotSame('', $panel, 'the panel is on the page');
        $this->assertStringNotContainsString('pm-avatar', $panel);
        $this->assertStringNotContainsString('Sign Out', $panel);
        // It repeated the name already printed in its own header.
        $this->assertStringNotContainsString('Full Name', $panel);
    }

    /**
     * Two other partials style .pm-close-btn as a grey circle, and one of
     * them is included after this file - so on document order alone it won,
     * and the panel's close button wore a circle it was never given. The
     * panel's own rule is scoped to beat both wherever they load.
     */
    public function test_the_close_button_is_not_restyled_by_another_partial(): void
    {
        $html = $this->actingAs($this->student(), 'web')
            ->get(route('student.team'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('.profile-modal .pm-close-btn', $html);
        $this->assertStringContainsString('border-radius: 0', $html);
    }

    /** What the sidebar cannot show, and the panel now does. */
    public function test_it_shows_the_team_and_the_role(): void
    {
        (new StudentRoleSeeder)->run();

        $student = $this->student();
        $team = Team::create(['team_name' => 'Bulbasaurus', 'leader_id' => $student->student_id]);
        StudentSchoolYear::create([
            'student_id' => $student->student_id,
            'team_id' => $team->team_id,
            's_role_id' => StudentRole::leaderRoleId(),
        ]);

        $panel = $this->panel(
            $this->actingAs($student, 'web')->get(route('student.team'))->assertOk()->getContent()
        );

        $this->assertStringContainsString('Bulbasaurus', $panel);
        $this->assertStringContainsString(StudentRole::LEADER_ROLE_NAME, $panel);
        $this->assertStringContainsString('Pending', $panel);
        $this->assertStringContainsString('21-04512', $panel);
    }

    /** A student with no team is shown their account and nothing invented. */
    public function test_a_student_with_no_team_gets_no_capstone_block(): void
    {
        $panel = $this->panel(
            $this->actingAs($this->student(), 'web')->get(route('student.team'))->assertOk()->getContent()
        );

        $this->assertStringNotContainsString('Your role', $panel);
        $this->assertStringContainsString('mark@my.cspc.edu.ph', $panel);
    }
}
