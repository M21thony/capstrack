<?php

namespace Tests\Feature;

use App\Mail\NotificationMail;
use App\Models\CapstoneExpert;
use App\Models\CapstonePreProposal;
use App\Models\DuplicationCheck;
use App\Models\Notification;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Services\Notifier;
use App\Support\NotificationTypes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * A notice that waits on someone to do something reaches their email, not
 * only the bell - and a notice that is merely news stays off it.
 */
class PeopleWhoMustActAreEmailedTest extends TestCase
{
    use RefreshDatabase;

    private function expert(): CapstoneExpert
    {
        $expert = CapstoneExpert::factory()->create(['approved_status' => true, 'is_admin' => false]);
        $expert->roles()->attach(Role::firstOrCreate(['role_name' => 'adviser'])->role_id);

        return $expert;
    }

    /** @return array<string, array{0: string}> */
    public static function noticesAnExpertMustActOn(): array
    {
        return [
            'a team turns in its titles' => ['titles_submitted'],
            'a team asks to change its title' => ['title_change_requested'],
            'a new title waits to be confirmed' => ['title_to_confirm'],
            'the adviser is asked for the go' => ['defense_go_request'],
            'put on or replaced on a panel' => ['defense_panel'],
        ];
    }

    #[DataProvider('noticesAnExpertMustActOn')]
    public function test_an_expert_is_emailed_what_waits_on_them(string $type): void
    {
        Mail::fake();
        $expert = $this->expert();

        Notifier::toExpert($expert, $type, 'Something to do', 'A team is waiting on you.', '/instructor/teams');

        Mail::assertQueuedCount(1);
        Mail::assertQueued(NotificationMail::class, fn (NotificationMail $mail) => $mail->hasTo($expert->email));
    }

    /** @return array<string, array{0: string}> */
    public static function noticesAStudentMustActOn(): array
    {
        return [
            'a paper is sent back' => ['paper_sent_back'],
            'a receipt is sent back' => ['receipt_sent_back'],
        ];
    }

    #[DataProvider('noticesAStudentMustActOn')]
    public function test_a_student_is_emailed_what_was_sent_back(string $type): void
    {
        Mail::fake();
        $student = Student::factory()->withCompleteProfile()->create();

        Notifier::toStudent($student, $type, 'Sent back', 'Send a corrected copy.', '/student/progress');

        Mail::assertQueuedCount(1);
        Mail::assertQueued(NotificationMail::class, fn (NotificationMail $mail) => $mail->hasTo($student->email));
    }

    /** Both kinds of "sent back" are one switch, and switching it off is respected. */
    public function test_the_sent_back_email_can_be_switched_off(): void
    {
        Mail::fake();
        $student = Student::factory()->withCompleteProfile()->create(['notification_prefs' => ['email.paper_sent_back' => false]]);

        Notifier::toStudent($student, 'paper_sent_back', 'Sent back', 'Send a corrected copy.');
        Notifier::toStudent($student, 'receipt_sent_back', 'Sent back', 'Upload a corrected photo.');

        Mail::assertNothingQueued();
        $this->assertSame(2, Notification::where('student_id', $student->student_id)->count());
    }

    /** News that asks nothing of anyone stays in the bell. */
    public function test_notices_that_are_only_news_are_still_not_emailed(): void
    {
        foreach (['milestone_due', 'checklist_item_added', 'member_joined', 'team_created'] as $type) {
            $this->assertFalse(NotificationTypes::mailedByBell('student', $type), $type.' would be emailed.');
        }

        foreach (['team_rating_submitted', 'team_ended', 'defense_window'] as $type) {
            $this->assertFalse(NotificationTypes::mailedByBell('expert', $type), $type.' would be emailed.');
        }
    }

    // -- A team turning its titles in --------------------------------------

    /** @return array{0: CapstoneExpert, 1: Student, 2: Team} */
    private function teamWithDrafts(int $drafts): array
    {
        $capsa = $this->makeCapsa(CapstoneExpert::factory()->create([
            'approved_status' => true, 'is_admin' => true, 'sections' => ['BSCS-4A'],
        ]));
        $leader = Student::factory()->withCompleteProfile()->create();
        $team = Team::create(['team_name' => 'Bahaghari', 'team_status' => 'Active', 'is_confirmed' => false, 'leader_id' => $leader->student_id]);
        $sy = StudentSchoolYear::create(['student_id' => $leader->student_id, 'team_id' => $team->team_id]);

        foreach (range(1, $drafts) as $slot) {
            $check = DuplicationCheck::create([
                'student_s_y_id' => $sy->s_y_id, 'document_path' => "checks/{$slot}.pdf", 'concepts' => [],
                'top_score' => 10, 'verdict' => 'Passed', 'matches' => [], 'instructors_suggested' => [],
            ]);
            CapstonePreProposal::create([
                'team_id' => $team->team_id, 'duplication_check_id' => $check->id, 'slot' => $slot,
                'title' => "Draft Title Number {$slot} For The Team To Turn In",
                'sdg_goals' => 'SDG 4', 'research_agenda' => ['Applications and Systems Development'],
                'problem_statement' => 'p', 'proposed_solution' => 's', 'scope' => 'sc', 'limitations' => 'l', 'sdm' => 'Agile',
                'duplication_status' => 'free', 'approved_status' => false,
            ]);
        }

        return [$capsa, $leader, $team];
    }

    /** Three titles turned in together are one thing to do, so one email. */
    public function test_the_capsa_gets_one_email_when_a_team_turns_in_its_titles(): void
    {
        Mail::fake();
        [$capsa, $leader, $team] = $this->teamWithDrafts(3);

        $this->actingAs($leader, 'web')
            ->patch(route('student.pre-proposal.submit'), ['status' => 'submitted'])
            ->assertSessionHas('status');

        Mail::assertQueuedCount(1);
        Mail::assertQueued(NotificationMail::class, fn (NotificationMail $mail) => $mail->hasTo($capsa->email)
            && $mail->headline === 'Titles to check: Bahaghari'
            && str_contains($mail->body, 'turned in 3 titles')
            && $mail->url === route('capsa.team_management.pre_proposals', $team->team_id));
    }

    /** Taking the titles back and turning them in again is not a second email. */
    public function test_turning_in_again_does_not_email_the_capsa_twice_while_the_first_is_unread(): void
    {
        Mail::fake();
        [$capsa, $leader] = $this->teamWithDrafts(2);

        $this->actingAs($leader, 'web');
        $this->patch(route('student.pre-proposal.submit'), ['status' => 'submitted'])->assertSessionHas('status');
        $this->patch(route('student.pre-proposal.submit'), ['status' => 'draft'])->assertSessionHas('status');
        $this->patch(route('student.pre-proposal.submit'), ['status' => 'submitted'])->assertSessionHas('status');

        Mail::assertQueuedCount(1);
        $this->assertSame(1, Notification::where('expert_id', $capsa->expert_id)->where('type', 'titles_submitted')->count());

        // Once she has read it, a later turn-in is news again.
        Notification::where('expert_id', $capsa->expert_id)->update(['is_read' => true]);
        $this->patch(route('student.pre-proposal.submit'), ['status' => 'draft'])->assertSessionHas('status');
        $this->patch(route('student.pre-proposal.submit'), ['status' => 'submitted'])->assertSessionHas('status');

        Mail::assertQueuedCount(2);
    }

    /** A team in a section nobody covers has no one to tell, and still turns in. */
    public function test_turning_in_works_when_no_capsa_covers_the_section(): void
    {
        Mail::fake();
        [$capsa, $leader, $team] = $this->teamWithDrafts(1);
        $capsa->update(['sections' => ['BSIT-3A']]);

        $this->actingAs($leader, 'web')
            ->patch(route('student.pre-proposal.submit'), ['status' => 'submitted'])
            ->assertSessionHas('status');

        Mail::assertNothingQueued();
        $this->assertTrue($team->fresh()->hasSubmittedPreProposals());
    }
}
