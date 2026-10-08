<?php

namespace Tests\Feature;

use App\Models\Notification;
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
        // 3rd year, so the team is in Capstone 1 and its roster is open.
        // These tests are about the invite button and verification; a 4th
        // year leader would close the roster and hide the button for a
        // reason none of them is testing.
        $leader = Student::create([
            'email' => 'j.abella@my.cspc.edu.ph',
            'f_name' => 'Jerome', 'l_name' => 'Abella',
            'course' => 'BSIT', 'year_section' => '3rd · A',
        ]);

        $team = Team::create([
            'team_name' => 'Codebreakers',
            'leader_id' => $leader->student_id,
            'team_status' => 'Active',
        ]);

        StudentSchoolYear::create(['student_id' => $leader->student_id, 'team_id' => $team->team_id]);

        return [$leader, $team];
    }

    /** A leadership transfer waiting on this student, so its dialogs render. */
    private function pendingTransfer(Student $leader, Team $team): void
    {
        Notification::create([
            'student_id' => $leader->student_id,
            'type' => 'leadership_transfer_request',
            'title' => 'Leadership transfer',
            'message' => 'You have been offered leadership of '.$team->team_name.'.',
            'is_read' => false,
            'data' => ['team_id' => $team->team_id, 'team_name' => $team->team_name],
        ]);
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

    /**
     * The X and the backdrop call jrpClose() from onclick, which only sees
     * window. It was private to the script, so the popup could not be closed.
     */
    public function test_the_popup_can_be_closed(): void
    {
        [$leader] = $this->leaderWithTeam();

        $html = $this->actingAs($leader, 'web')->get(route('student.team'))->getContent();

        $this->assertStringContainsString('jrpClose()" class="pm-close-btn"', $html);
        $this->assertStringContainsString('window.jrpClose = jrpClose;', $html);
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

        // Both answer with Yes, against a No.
        $this->assertStringContainsString("confirmOk.textContent = 'Yes';", $html);
        $this->assertStringContainsString('jrpConfirmClose()">No</button>', $html);
    }

    /**
     * The same question in the same words wherever it is asked. Accepting
     * leadership is not the same act as accepting an invitation, so it keeps
     * its own title - but it stopped saying "Approve and review?" too.
     */
    public function test_the_leadership_transfer_dialogs_match(): void
    {
        [$leader, $team] = $this->leaderWithTeam();
        $this->pendingTransfer($leader, $team);

        $html = $this->actingAs($leader, 'web')->get(route('student.team'))->getContent();

        $this->assertStringContainsString("title:'Accept Transfer'", $html);
        $this->assertStringNotContainsString('Approve and review?', $html);
        $this->assertStringNotContainsString('Approve &amp; Review', $html);
        $this->assertStringNotContainsString('Decline and review?', $html);
        $this->assertStringNotContainsString('Decline &amp; Review', $html);

        // Cancel is a parameter rather than a rename: the same dialog asks a
        // dozen other things where Cancel is the right word.
        $this->assertStringContainsString("cancelText = 'Cancel'", $html);
        $this->assertStringContainsString("cancelText:'No'", $html);
    }

    /**
     * Later on a leadership transfer had the identical bug the invitation
     * popup did: a POST to a route that only answers PUT.
     */
    public function test_leadership_later_is_sent_as_a_put(): void
    {
        [$leader, $team] = $this->leaderWithTeam();
        $this->pendingTransfer($leader, $team);

        $html = $this->actingAs($leader, 'web')->get(route('student.team'))->getContent();

        $this->assertStringContainsString('leadershipPopRemindLater', $html);
        // The verb sent to the reminder route, read off the call itself.
        $this->assertSame(1, preg_match('/leadership.{0,200}?reminder.{0,200}?method: .(\w+)./s', $html, $m));
        $this->assertSame('PUT', $m[1]);
    }

    /**
     * "popup" named the thing on screen, not the thing being fetched. It is
     * the collection now, filtered to what is waiting for you.
     */
    public function test_what_is_waiting_is_read_from_the_collection(): void
    {
        [$leader, $team] = $this->leaderWithTeam();

        $sender = Student::create([
            'email' => 'p.escano@my.cspc.edu.ph',
            'f_name' => 'Paolo', 'l_name' => 'Escano',
        ]);

        TeamInvitation::create([
            'sender_id' => $sender->student_id,
            'receiver_id' => $leader->student_id,
            'team_id' => $team->team_id,
            'type' => 'invite',
            'status' => 'pending',
        ]);

        $this->actingAs($leader, 'web')
            ->getJson(route('invitations.index'))
            ->assertOk()
            ->assertJsonPath('items.0.name', 'Paolo Escano');
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
     * A CaPSA verifying a team does not close its roster.
     *
     * The invite button was hidden once is_confirmed was set, so a leader
     * whose member dropped out after verification had no way to replace them.
     * InvitationController::store() never enforced it, so what was there was a
     * hidden button over an open door.
     */
    public function test_a_verified_team_can_still_invite(): void
    {
        [$leader, $team] = $this->leaderWithTeam();
        $team->update(['is_confirmed' => true]);

        $html = $this->actingAs($leader, 'web')->get(route('student.team'))->getContent();

        $this->assertStringContainsString('openOpenSlotInviteModal()', $html);
        $this->assertStringContainsString('Click to invite a classmate', $html);
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

    /** Capstone 2 does close it, which is a different rule from verification. */
    public function test_a_capstone_two_team_has_no_invite_button(): void
    {
        [$leader, $team] = $this->leaderWithTeam();

        // The section is uploaded again as 4th year: the team has moved up.
        $leader->update(['year_section' => '4th · A']);

        $html = $this->actingAs($leader, 'web')->get(route('student.team'))->getContent();

        $this->assertStringNotContainsString('Click to invite a classmate', $html);
        $this->assertStringContainsString('Members Addition no longer available.', $html);
    }
}
