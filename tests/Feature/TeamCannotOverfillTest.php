<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\Student;
use App\Models\StudentRoster;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\Term;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A team cannot be invited past the size the CaPSA set.
 *
 * Nothing checked this. The only capacity test ran after somebody had
 * already joined, and only to mark the team Full - so a leader could pick
 * five classmates for a team of three and every one of them got in.
 */
class TeamCannotOverfillTest extends TestCase
{
    use RefreshDatabase;

    private function student(string $email): Student
    {
        $s = Student::create([
            'email' => $email, 'f_name' => 'A', 'l_name' => 'B',
            'course' => 'BSIT', 'year_section' => '3rd · A',
        ]);
        StudentRoster::create([
            'email' => $email, 'name' => 'A B', 'section' => '3rd · A',
            'program' => 'BSIT', 'school_year' => Term::currentSchoolYear(),
        ]);

        // Every real student has one of these from their first sign-in, and
        // joining a team is an update to it - without one, accepting an
        // invitation silently adds nobody.
        StudentSchoolYear::create(['student_id' => $s->student_id]);

        return $s;
    }

    private function invite(Student $leader, Team $team, Student $to)
    {
        return $this->actingAs($leader, 'web')->postJson('/api/invitations', [
            'receiver_id' => $to->student_id,
            'team_id' => $team->team_id,
            'type' => 'invite',
        ]);
    }

    public function test_invitations_stop_once_the_seats_are_spoken_for(): void
    {
        Setting::set('max_team_members', '3');

        $leader = $this->student('lead@my.cspc.edu.ph');
        $team = Team::create(['team_name' => 'T', 'leader_id' => $leader->student_id, 'team_status' => 'Active']);
        StudentSchoolYear::where('student_id', $leader->student_id)->update(['team_id' => $team->team_id]);

        // Three seats: the leader has one, so two invitations fit.
        $this->invite($leader, $team, $this->student('one@my.cspc.edu.ph'))->assertSuccessful();
        $this->invite($leader, $team, $this->student('two@my.cspc.edu.ph'))->assertSuccessful();

        // The third would go over, even though nobody has accepted yet.
        $this->invite($leader, $team, $this->student('three@my.cspc.edu.ph'))
            ->assertStatus(422)
            ->assertJson(fn ($j) => $j->where('message', fn ($m) => str_contains($m, 'waiting to be answered'))->etc());

        $this->assertSame(2, TeamInvitation::count());
    }

    public function test_a_full_team_refuses_outright(): void
    {
        Setting::set('max_team_members', '3');

        $leader = $this->student('lead2@my.cspc.edu.ph');
        $team = Team::create(['team_name' => 'T2', 'leader_id' => $leader->student_id, 'team_status' => 'Active']);

        foreach (['lead2', 'm1', 'm2'] as $i => $who) {
            $student = $i === 0 ? $leader : $this->student($who.'@my.cspc.edu.ph');
            StudentSchoolYear::where('student_id', $student->student_id)->update(['team_id' => $team->team_id]);
        }

        $this->invite($leader, $team, $this->student('extra@my.cspc.edu.ph'))
            ->assertStatus(422)
            ->assertJson(fn ($j) => $j->where('message', fn ($m) => str_contains($m, 'maximum of 3'))->etc());
    }

    /** Two invitations out, one seat: the second to answer is turned away. */
    public function test_the_second_accept_for_the_last_seat_is_refused(): void
    {
        Setting::set('max_team_members', '3');

        $leader = $this->student('lead3@my.cspc.edu.ph');
        $team = Team::create(['team_name' => 'T3', 'leader_id' => $leader->student_id, 'team_status' => 'Active']);
        $member = $this->student('sat@my.cspc.edu.ph');

        foreach ([$leader, $member] as $s) {
            StudentSchoolYear::where('student_id', $s->student_id)->update(['team_id' => $team->team_id]);
        }

        $first = $this->student('first@my.cspc.edu.ph');
        $second = $this->student('second@my.cspc.edu.ph');

        $invitations = collect([$first, $second])->map(fn ($to) => TeamInvitation::create([
            'sender_id' => $leader->student_id, 'receiver_id' => $to->student_id,
            'team_id' => $team->team_id, 'type' => 'invite', 'status' => 'pending',
        ]));

        $this->actingAs($first, 'web')
            ->patchJson('/api/invitations/'.$invitations[0]->id, ['status' => 'accepted'])->assertSuccessful();

        $this->actingAs($second, 'web')
            ->patchJson('/api/invitations/'.$invitations[1]->id, ['status' => 'accepted'])
            ->assertStatus(422);

        $this->assertNull(StudentSchoolYear::where('student_id', $second->student_id)->value('team_id'));
    }
}
