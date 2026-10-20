<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\MemberLeaveRequest;
use App\Models\Notification;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * A request to leave a team, or to remove a member, waits three days for the
 * CaPSA. Undecided by then, it is declined on its own, the team is told it
 * ran out of time rather than that the CaPSA said no, and both sides see a
 * tag saying so.
 */
class LeaveRequestAutoDeclineTest extends TestCase
{
    use RefreshDatabase;

    private Team $team;

    private Student $leader;

    private Student $member;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2027-02-01 09:00:00');

        $make = function (string $email) {
            $s = Student::create(['email' => $email, 'f_name' => ucfirst(strtok($email, '@')), 'l_name' => 'Tester', 'course' => 'BSIT', 'year_section' => '3rd · A']);
            StudentSchoolYear::create(['student_id' => $s->student_id]);

            return $s;
        };
        $this->leader = $make('leader@my.cspc.edu.ph');
        $this->member = $make('ana@my.cspc.edu.ph');
        $this->team = Team::create(['team_name' => 'Lockers', 'leader_id' => $this->leader->student_id, 'team_status' => 'Active', 'is_confirmed' => true]);
        StudentSchoolYear::whereIn('student_id', [$this->leader->student_id, $this->member->student_id])->update(['team_id' => $this->team->team_id]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function capsa(): CapstoneExpert
    {
        $capsa = CapstoneExpert::factory()->create(['approved_status' => true, 'is_admin' => false, 'sections' => ['BSIT-3A']]);
        $capsa->roles()->attach(Role::firstOrCreate(['role_name' => CapstoneExpert::CAPSA_ROLE])->role_id);

        return $capsa;
    }

    private function askToLeave(): MemberLeaveRequest
    {
        $this->actingAs($this->member, 'web')->postJson(route('team.capsa-leave-requests.store'), [
            'type' => MemberLeaveRequest::LEAVE, 'reason' => 'My family is moving to Cebu next month.',
        ])->assertCreated();

        return MemberLeaveRequest::firstOrFail();
    }

    public function test_a_request_left_three_days_is_declined_automatically_and_the_member_is_told_why(): void
    {
        $request = $this->askToLeave();

        $this->travel(2)->days();
        $this->artisan('leave-requests:expire')->assertSuccessful();
        $this->assertTrue($request->fresh()->isPending(), 'two days in, it still waits');

        $this->travel(1)->days();
        $this->artisan('leave-requests:expire')->assertSuccessful();

        $request->refresh();
        $this->assertSame(MemberLeaveRequest::DECLINED, $request->status);
        $this->assertTrue($request->auto_declined);
        $this->assertNull($request->decided_by);
        $this->assertSame(MemberLeaveRequest::AUTO_DECLINE_NOTE, $request->decision_note);
        $this->assertTrue($request->decided_at->equalTo($request->created_at->copy()->addDays(3)));
        $this->assertTrue(StudentSchoolYear::where('student_id', $this->member->student_id)->where('team_id', $this->team->team_id)->exists(), 'still on the team');

        $bell = Notification::where('student_id', $this->member->student_id)->where('type', 'leave_request_declined')->sole();
        $this->assertSame('Request declined automatically', $bell->title);
        $this->assertStringContainsString('did not act on it within 3 days', $bell->message);
        $this->assertSame($request->id, $bell->data['leave_request_id']);

        // A second run does nothing more.
        $this->artisan('leave-requests:expire')->assertSuccessful();
        $this->assertSame(1, Notification::where('student_id', $this->member->student_id)->where('type', 'leave_request_declined')->count());
    }

    public function test_a_removal_tells_the_leader_and_the_member_it_was_about(): void
    {
        // Asked by the leader of a team past Title Defense.
        MemberLeaveRequest::create([
            'team_id' => $this->team->team_id, 'student_id' => $this->member->student_id, 'requested_by' => $this->leader->student_id,
            'type' => MemberLeaveRequest::REMOVE, 'reason' => 'They have not attended a meeting in a month.', 'status' => MemberLeaveRequest::PENDING,
        ]);

        $this->travel(3)->days();
        $this->artisan('leave-requests:expire')->assertSuccessful();

        $this->assertSame('Request declined automatically', Notification::where('student_id', $this->leader->student_id)->where('type', 'leave_request_declined')->sole()->title);
        $this->assertStringContainsString('declined automatically', Notification::where('student_id', $this->member->student_id)->where('type', 'leave_request_declined')->sole()->message);
    }

    /** Without the scheduler: opening the Team page is enough. */
    public function test_the_team_page_declines_it_on_its_own_and_tags_it(): void
    {
        $this->askToLeave();

        $this->actingAs($this->member, 'web')->get(route('student.team'))
            ->assertOk()
            ->assertSee('Waiting on your CaPSA')
            ->assertSee('Auto-declines Feb 4 without a decision');

        $this->travelTo(now()->addDays(3)->addMinute());

        $this->actingAs($this->member, 'web')->get(route('student.team'))
            ->assertOk()
            ->assertDontSee('Waiting on your CaPSA')
            ->assertSee('Declined automatically')
            ->assertSee('Auto-declined · No action from CaPSA in 3 days')
            ->assertSee('Ask CaPSA to leave');

        $this->assertTrue(MemberLeaveRequest::sole()->auto_declined);
    }

    public function test_the_last_day_says_how_many_hours_are_left(): void
    {
        $this->askToLeave();
        $this->travel(2)->days();
        $this->travel(19)->hours();

        $this->actingAs($this->member, 'web')->get(route('student.team'))
            ->assertOk()
            ->assertSee('Auto-declines in 5 hours without a decision');
    }

    public function test_the_capsa_sees_the_deadline_then_the_tag_and_cannot_approve_it_late(): void
    {
        $request = $this->askToLeave();
        $capsa = $this->capsa();

        $this->actingAs($capsa, 'expert')->get(route('capsa.team_management', ['tab' => 'leave_requests']))
            ->assertOk()
            ->assertSee('Auto-declines on Feb 4')
            ->assertSee('declined automatically, and the team is told it ran out of time');

        $this->travel(3)->days();

        $this->actingAs($capsa, 'expert')
            ->patchJson(route('capsa.leave_requests.update', $request), ['status' => 'approved'])
            ->assertStatus(409)
            ->assertJsonFragment(['message' => 'This request was declined automatically: it waited 3 days without a decision. The team has been told.']);
        $this->assertTrue(StudentSchoolYear::where('student_id', $this->member->student_id)->where('team_id', $this->team->team_id)->exists());

        $this->actingAs($capsa, 'expert')->get(route('capsa.team_management', ['tab' => 'leave_requests']))
            ->assertOk()
            ->assertSee('Auto-declined')
            ->assertSee('No action from CaPSA in 3 days');
    }

    public function test_a_capsa_who_decides_in_time_is_unaffected(): void
    {
        $request = $this->askToLeave();
        $this->travel(2)->days();

        $this->actingAs($this->capsa(), 'expert')
            ->patchJson(route('capsa.leave_requests.update', $request), ['status' => 'declined', 'note' => 'Finish the defense with your team first.'])
            ->assertOk();

        $this->travel(5)->days();
        $this->artisan('leave-requests:expire')->assertSuccessful();

        $request->refresh();
        $this->assertFalse($request->auto_declined);
        $this->assertSame('Finish the defense with your team first.', $request->decision_note);
        $this->assertSame('Request declined', Notification::where('student_id', $this->member->student_id)->where('type', 'leave_request_declined')->sole()->title);
    }

    public function test_the_member_can_ask_again_after_it_runs_out(): void
    {
        $this->askToLeave();
        $this->travel(3)->days();
        $this->artisan('leave-requests:expire')->assertSuccessful();

        $this->actingAs($this->member, 'web')->postJson(route('team.capsa-leave-requests.store'), [
            'type' => MemberLeaveRequest::LEAVE, 'reason' => 'Asking again: my family is moving to Cebu.',
        ])->assertCreated();

        $this->assertSame(1, MemberLeaveRequest::pending()->count());
    }

    public function test_the_decision_popup_says_it_was_the_clock(): void
    {
        $this->askToLeave();
        $this->travel(3)->days();
        $this->artisan('leave-requests:expire')->assertSuccessful();

        $html = $this->actingAs($this->member, 'web')->get(route('student.progress'))->assertOk()->getContent();
        preg_match('/data-leave="([^"]+)"/', $html, $m);
        $details = json_decode(html_entity_decode($m[1]), true);

        $this->assertTrue($details['auto']);
        $this->assertSame(3, $details['autoDays']);
        $this->assertStringContainsString("set('ld-status', 'Auto-declined');", $html);
    }

    public function test_it_runs_every_hour(): void
    {
        $this->artisan('schedule:list')->expectsOutputToContain('leave-requests:expire');
    }
}
