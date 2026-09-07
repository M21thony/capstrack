<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\StudentRoster;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\Term;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A team's members are settled once the department reaches Capstone 2.
 *
 * Capstone 1 is the second semester of 3rd year - forming the team, choosing
 * a title, getting it approved. Capstone 2 is the first semester of 4th year,
 * building and defending what was already proposed. Somebody joining then was
 * not there for any of the work being graded.
 *
 * Which one is running is the Admin's declaration on the active term, not
 * something a student's year answers: a 3rd year in the first semester has
 * not started Capstone 1, and the year label cannot tell those apart.
 */
class MembersOnlyInCapstoneOneTest extends TestCase
{
    use RefreshDatabase;

    private function student(string $email, string $yearSection = '3rd · A'): Student
    {
        $s = Student::create([
            'email' => $email, 'f_name' => 'A', 'l_name' => 'B',
            'course' => 'BSIT', 'year_section' => $yearSection,
        ]);
        StudentRoster::create([
            'email' => $email, 'name' => 'A B', 'section' => $yearSection,
            'program' => 'BSIT', 'school_year' => Term::currentSchoolYear(),
        ]);

        return $s;
    }

    private function teamOf(Student $leader): Team
    {
        $team = Team::create(['team_name' => 'T', 'leader_id' => $leader->student_id, 'team_status' => 'Active']);
        StudentSchoolYear::create(['student_id' => $leader->student_id, 'team_id' => $team->team_id]);

        return $team;
    }

    public function test_a_third_year_team_is_capstone_one_and_open(): void
    {
        $team = $this->teamOf($this->student('a@my.cspc.edu.ph', '3rd · A'));

        $this->assertSame(1, $team->capstoneStage());
        $this->assertTrue($team->mayAddMembers());
    }

    public function test_a_fourth_year_team_is_capstone_two_and_settled(): void
    {
        $team = $this->teamOf($this->student('b@my.cspc.edu.ph', '4th · A'));

        $this->assertSame(2, $team->fresh()->capstoneStage());
        $this->assertFalse($team->fresh()->mayAddMembers());
    }

    /** One member in 4th year settles the whole team. */
    public function test_any_fourth_year_member_settles_the_team(): void
    {
        $team = $this->teamOf($this->student('lead@my.cspc.edu.ph', '3rd · A'));

        $older = $this->student('older@my.cspc.edu.ph', '4th · A');
        StudentSchoolYear::create(['student_id' => $older->student_id, 'team_id' => $team->team_id]);

        $this->assertSame(2, $team->fresh()->capstoneStage());
    }

    /** No year recorded is read as 3rd year - refusing everybody is worse. */
    public function test_an_unknown_year_is_treated_as_capstone_one(): void
    {
        $student = $this->student('noyear@my.cspc.edu.ph');
        $student->update(['year_section' => '']);

        $this->assertSame(1, $student->fresh()->capstoneStage());
    }

    public function test_inviting_during_capstone_two_is_refused(): void
    {
        $leader = $this->student('lead@my.cspc.edu.ph', '4th · A');
        $team = $this->teamOf($leader);
        $outsider = $this->student('out@my.cspc.edu.ph', '4th · A');

        $this->actingAs($leader, 'web')
            ->postJson('/api/invitations', [
                'receiver_id' => $outsider->student_id,
                'team_id' => $team->team_id,
                'type' => 'invite',
            ])
            ->assertStatus(422)
            ->assertJson(fn ($j) => $j->where('message', fn ($m) => str_contains($m, 'Capstone 1'))->etc());

        $this->assertSame(0, TeamInvitation::count());
    }

    /**
     * An invitation sent in Capstone 1 cannot be accepted in Capstone 2.
     *
     * The list is uploaded again as 4th year between the two, so a check made
     * only at sending would let a stale invitation add a member after the
     * team has already moved up.
     */
    public function test_accepting_after_the_team_moves_up_is_refused(): void
    {
        $leader = $this->student('lead2@my.cspc.edu.ph', '3rd · A');
        $team = $this->teamOf($leader);
        $joiner = $this->student('join@my.cspc.edu.ph', '3rd · A');

        $invitation = TeamInvitation::create([
            'sender_id' => $leader->student_id,
            'receiver_id' => $joiner->student_id,
            'team_id' => $team->team_id,
            'type' => 'invite',
            'status' => 'pending',
        ]);

        // The section is uploaded again as 4th year: the team has moved up.
        $leader->update(['year_section' => '4th · A']);

        $this->actingAs($joiner, 'web')
            ->patchJson('/api/invitations/'.$invitation->id, ['status' => 'accepted'])
            ->assertStatus(422);

        $this->assertNull(StudentSchoolYear::where('student_id', $joiner->student_id)->value('team_id'));
    }
}
