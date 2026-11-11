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
use Tests\TestCase;

/**
 * A student told of their CaPSA/TSA's decision on a leave request can open it in
 * full from the bell: the decision, the CaPSA/TSA's reason, who decided, when,
 * and the request it answered.
 */
class LeaveDecisionPopupTest extends TestCase
{
    use RefreshDatabase;

    private Team $team;

    private Student $leader;

    private Student $member;

    protected function setUp(): void
    {
        parent::setUp();

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

    private function capsa(): CapstoneExpert
    {
        $capsa = CapstoneExpert::factory()->create(['approved_status' => true, 'is_admin' => false, 'sections' => ['BSIT-3A'], 'first_name' => 'Maria', 'last_name' => 'Santos']);
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

    public function test_a_declined_request_opens_with_the_capsas_reason_and_details(): void
    {
        $request = $this->askToLeave();

        $this->actingAs($this->capsa(), 'expert')
            ->patchJson(route('capsa.leave_requests.update', $request), ['status' => 'declined', 'note' => 'Finish the defense with your team first.'])
            ->assertOk();

        $notification = Notification::where('student_id', $this->member->student_id)->where('type', 'leave_request_declined')->firstOrFail();
        $this->assertSame($request->id, $notification->data['leave_request_id']);

        $html = $this->actingAs($this->member, 'web')->get(route('student.progress'))->assertOk()->getContent();

        $this->assertStringContainsString('id="ld-overlay"', $html);
        $this->assertStringContainsString('data-leave="', $html);
        preg_match('/data-leave="([^"]+)"/', $html, $m);
        $details = json_decode(html_entity_decode($m[1]), true);

        $this->assertSame('declined', $details['status']);
        $this->assertSame('Finish the defense with your team first.', $details['decisionNote']);
        $this->assertSame('Maria Santos', $details['decidedBy']);
        $this->assertSame('My family is moving to Cebu next month.', $details['reason']);
        $this->assertSame('Lockers', $details['team']);
        $this->assertSame('You', $details['requestedBy']);
        $this->assertNotEmpty($details['decidedOn']);
    }

    public function test_an_approved_request_opens_too(): void
    {
        $request = $this->askToLeave();

        $this->actingAs($this->capsa(), 'expert')
            ->patchJson(route('capsa.leave_requests.update', $request), ['status' => 'approved', 'note' => 'Transfer papers seen.'])
            ->assertOk();

        $html = $this->actingAs($this->member, 'web')->get(route('student.progress'))->assertOk()->getContent();
        preg_match('/data-leave="([^"]+)"/', $html, $m);
        $details = json_decode(html_entity_decode($m[1] ?? ''), true);

        $this->assertSame('approved', $details['status'] ?? null);
        $this->assertSame('Transfer papers seen.', $details['decisionNote']);
    }

    /** A notification from before they named their request is matched to it anyway. */
    public function test_an_older_notification_without_the_request_is_matched_to_it(): void
    {
        $request = $this->askToLeave();
        $request->update(['status' => MemberLeaveRequest::DECLINED, 'decision_note' => 'Not this term.', 'decided_at' => now()->subMinute()]);
        Notification::create(['student_id' => $this->member->student_id, 'type' => 'leave_request_declined', 'title' => 'Request declined', 'message' => 'Declined.', 'link' => route('student.team')]);

        $html = $this->actingAs($this->member, 'web')->get(route('student.progress'))->assertOk()->getContent();
        preg_match('/data-leave="([^"]+)"/', $html, $m);
        $details = json_decode(html_entity_decode($m[1] ?? ''), true);

        $this->assertSame('Not this term.', $details['decisionNote'] ?? null);
    }

    /** Other notifications still open their own page. */
    public function test_other_notifications_carry_no_popup(): void
    {
        Notification::create(['student_id' => $this->member->student_id, 'type' => 'announcement', 'title' => 'Plain one', 'message' => 'Hi.', 'link' => route('student.team')]);

        $this->actingAs($this->member, 'web')->get(route('student.progress'))
            ->assertOk()
            ->assertSee('Plain one')
            ->assertDontSee('data-leave="', false);
    }
}
