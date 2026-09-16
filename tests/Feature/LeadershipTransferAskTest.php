<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\Student;
use App\Models\StudentRoster;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The member being handed the team is actually asked.
 *
 * Transferring leadership is a request, not an act: the leader proposes it
 * and the other person has to accept. If they are never asked, the team is
 * stuck with a transfer that can never complete.
 */
class LeadershipTransferAskTest extends TestCase
{
    use RefreshDatabase;

    private Student $leader;
    private Student $member;
    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        $make = function (string $email, string $first) {
            $s = Student::create([
                'email' => $email, 'f_name' => $first, 'l_name' => 'Tester',
                'course' => 'BSIT', 'year_section' => '4th · A',
            ]);
            StudentRoster::create([
                'email' => $email, 'name' => $first.' Tester', 'section' => '4th · A',
                'program' => 'BSIT', 'school_year' => \App\Models\Term::currentSchoolYear(),
            ]);

            return $s;
        };

        $this->leader = $make('leader@my.cspc.edu.ph', 'Lead');
        $this->member = $make('member@my.cspc.edu.ph', 'Mem');

        $this->team = Team::create(['team_name' => 'Bulbasaurus', 'leader_id' => $this->leader->student_id]);

        foreach ([$this->leader, $this->member] as $s) {
            StudentSchoolYear::create(['student_id' => $s->student_id, 'team_id' => $this->team->team_id]);
        }
    }

    public function test_the_member_is_asked_and_sees_the_dialog(): void
    {
        $this->actingAs($this->leader, 'web')
            ->put(route('team.transfer-leadership'), ['new_leader_id' => $this->member->student_id])
            ->assertRedirect();

        $this->assertDatabaseHas('notifications', [
            'student_id' => $this->member->student_id,
            'type' => 'leadership_transfer_request',
            'is_read' => false,
        ]);

        // The page has to hand the view a pending transfer, or nothing opens.
        $pending = $this->actingAs($this->member, 'web')
            ->get(route('student.team'))->assertOk()->viewData('pendingLeadershipTransfer');

        $this->assertNotNull($pending, 'the member is never asked');
        $this->assertSame('leadership_transfer_request', $pending->type);
    }

    /** Accepting actually hands the team over. */
    public function test_accepting_makes_them_the_leader(): void
    {
        $this->actingAs($this->leader, 'web')
            ->put(route('team.transfer-leadership'), ['new_leader_id' => $this->member->student_id]);

        $ask = Notification::where('student_id', $this->member->student_id)
            ->where('type', 'leadership_transfer_request')->firstOrFail();

        $this->actingAs($this->member, 'web')
            ->patch(route('team.leadership.update', $ask->id), ['status' => 'accepted']);

        $this->assertSame($this->member->student_id, $this->team->fresh()->leader_id);
    }
}
