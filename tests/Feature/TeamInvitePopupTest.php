<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Models\TeamInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The incoming-invitation popup, and inviting people into a team.
 *
 * Two of these guard a crash rather than a preference. "Later" sent a bare
 * POST to a route that only answers PUT, so pressing it replaced the page
 * with MethodNotAllowedHttpException - and closing the popup, which marks
 * everything in it for later, hit the same wall silently.
 */
class TeamInvitePopupTest extends TestCase
{
    use RefreshDatabase;

    private function leaderWithTeam(): array
    {
        $leader = Student::create([
            'email' => 'j.abella@my.cspc.edu.ph',
            'f_name' => 'Jerome', 'l_name' => 'Abella',
            'course' => 'BSIT', 'year_section' => '4th · A',
        ]);

        $team = Team::create([
            'team_name' => 'Codebreakers',
            'leader_id' => $leader->student_id,
            'team_status' => 'Active',
        ]);

        StudentSchoolYear::create(['student_id' => $leader->student_id, 'team_id' => $team->team_id]);

        return [$leader, $team];
    }

    /**
     * The route is a PUT. The popup used to send a form with no _method at
     * all, which the router answered with MethodNotAllowed.
     */
    public function test_later_is_sent_as_a_put(): void
    {
        [$leader] = $this->leaderWithTeam();

        $html = $this->actingAs($leader, 'web')->get(route('student.team'))->getContent();

        $this->assertStringContainsString("[['_method', 'PUT']]", $html);
        // And the close-everything path, which fetches rather than posting.
        $this->assertStringNotContainsString("remindUrl.replace('__ID__', id), {\n        method: 'POST'", $html);
    }

    /** The endpoint itself, so the verb the page sends is the verb it wants. */
    public function test_the_reminder_endpoint_takes_a_put(): void
    {
        [$leader, $team] = $this->leaderWithTeam();

        $sender = Student::create([
            'email' => 'k.delarosa@my.cspc.edu.ph',
            'f_name' => 'Kristine', 'l_name' => 'Dela Rosa',
        ]);

        $invite = TeamInvitation::create([
            'sender_id' => $sender->student_id,
            'receiver_id' => $leader->student_id,
            'team_id' => $team->team_id,
            'type' => 'invite',
            'status' => 'pending',
        ]);

        // It answers a browser with back(), and JSON with success - either
        // way it answers, which a POST did not.
        $this->actingAs($leader, 'web')
            ->put(route('invitations.remind-later', $invite->id))
            ->assertRedirect();

        $this->actingAs($leader, 'web')
            ->putJson(route('invitations.remind-later', $invite->id))
            ->assertOk()
            ->assertJson(['success' => true]);
    }

    public function test_the_dialogs_ask_yes_or_no(): void
    {
        [$leader] = $this->leaderWithTeam();

        $html = $this->actingAs($leader, 'web')->get(route('student.team'))->getContent();

        // Accepting names what is being accepted; declining is just Decline.
        $this->assertStringContainsString("'Accept Invitation'", $html);
        $this->assertStringContainsString("'Accept Request'", $html);
        $this->assertStringContainsString("confirmTitle.textContent = 'Decline';", $html);

        // Both answer with Yes, against a No. Scoped to this popup's own
        // variables: the leadership-transfer dialogs elsewhere on the page
        // still read "Approve & Review", which is a separate question.
        $this->assertStringContainsString("confirmOk.textContent = 'Yes';", $html);
        $this->assertStringNotContainsString("confirmOk.textContent = 'Approve & Review';", $html);
        $this->assertStringNotContainsString("confirmOk.textContent = 'Decline & Review';", $html);
        $this->assertStringContainsString('jrpConfirmClose()">No</button>', $html);
    }

    /**
     * Picking somebody and deciding to invite them are two acts. Touching a
     * name used to fire a confirmation on the spot, so inviting three people
     * was three dialogs.
     */
    public function test_the_invite_picker_sends_from_one_button(): void
    {
        [$leader] = $this->leaderWithTeam();

        $html = $this->actingAs($leader, 'web')->get(route('student.team'))->getContent();

        $this->assertStringContainsString('id="osl-send"', $html);
        $this->assertStringContainsString('sendOpenSlotInvites()', $html);
        $this->assertStringContainsString('Send Invite', $html);

        // No per-click confirmation, and no single-invite sender left behind.
        $this->assertStringNotContainsString('function sendOpenSlotInvite(', $html);
        $this->assertStringNotContainsString("title: 'Invite ' + name + '?'", $html);
    }

    /**
     * The pending list is rendered server-side, so a new invitation only
     * appears once the page is asked again - which is why one used to show
     * up only after a manual refresh.
     */
    public function test_sending_asks_the_page_again(): void
    {
        [$leader] = $this->leaderWithTeam();

        $html = $this->actingAs($leader, 'web')->get(route('student.team'))->getContent();

        $this->assertStringContainsString('window.location.reload()', $html);
    }
}
