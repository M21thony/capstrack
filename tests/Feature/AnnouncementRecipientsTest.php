<?php

namespace Tests\Feature;

use App\Mail\NotificationMail;
use App\Models\Announcement;
use App\Models\CapstoneExpert;
use App\Models\Notification;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Who a CaPSA/TSA's announcement reaches.
 *
 * The bell, the email and the student dashboard used to decide this each on
 * their own. A post to "Student" with no section picked went to every student
 * in the system, a team mentioned anywhere but the end of the post was
 * dropped, and a team mentioned in an expert-only post was still notified.
 * These hold them to one answer: whoever's dashboard lists it.
 */
class AnnouncementRecipientsTest extends TestCase
{
    use RefreshDatabase;

    private CapstoneExpert $capsa;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->withoutVite();

        $this->capsa = $this->makeCapsa(CapstoneExpert::factory()->create([
            'approved_status' => true,
            'sections' => ['BSIT-3A', 'BSIT-3B'],
        ]));
    }

    private function student(string $section, string $name): Student
    {
        return Student::factory()->create([
            'f_name' => $name,
            'l_name' => 'Test',
            'course' => 'BSIT',
            'year_section' => '3rd · '.$section,
        ]);
    }

    private function team(string $name, Student ...$members): Team
    {
        $team = Team::create([
            'team_name' => $name,
            'leader_id' => $members[0]->student_id,
            'team_status' => 'Active',
            'is_confirmed' => true,
        ]);

        foreach ($members as $member) {
            StudentSchoolYear::create(['student_id' => $member->student_id, 'team_id' => $team->team_id]);
        }

        return $team;
    }

    /** @param array<string, mixed> $overrides */
    private function announce(array $overrides = []): void
    {
        $this->actingAs($this->capsa, 'expert')
            ->postJson(route('api.admin.announcements.store'), array_merge([
                'title' => 'Chapter 3 deadline',
                'body' => 'Upload Chapter 3 by Friday.',
                'priority' => 'normal',
                'target_audience' => ['student'],
                'team_id' => null,
            ], $overrides))
            ->assertOk();
    }

    /** @return array<int, int> */
    private function notifiedStudentIds(): array
    {
        return Notification::whereNotNull('student_id')->pluck('student_id')->sort()->values()->all();
    }

    private function dashboardShows(Student $student, string $title): bool
    {
        $page = $this->actingAs($student)->get(route('student.dashboard'))->assertOk();

        return str_contains($page->getContent(), $title);
    }

    public function test_a_student_post_with_no_section_reaches_only_the_posters_sections(): void
    {
        $mine = $this->student('A', 'Mine');
        $alsoMine = $this->student('B', 'AlsoMine');
        $elsewhere = $this->student('C', 'Elsewhere');

        $this->announce();

        $this->assertSame([$mine->student_id, $alsoMine->student_id], $this->notifiedStudentIds());
        $this->assertTrue($this->dashboardShows($mine, 'Chapter 3 deadline'));
        $this->assertFalse($this->dashboardShows($elsewhere, 'Chapter 3 deadline'));
    }

    public function test_picked_sections_are_the_only_ones_notified(): void
    {
        $a = $this->student('A', 'Ana');
        $b = $this->student('B', 'Ben');

        $this->announce(['target_audience' => ['student', 'section:BSIT-3B']]);

        $this->assertSame([$b->student_id], $this->notifiedStudentIds());
        $this->assertFalse($this->dashboardShows($a, 'Chapter 3 deadline'));
        $this->assertTrue($this->dashboardShows($b, 'Chapter 3 deadline'));
    }

    public function test_all_sections_reaches_every_section_of_the_poster(): void
    {
        $a = $this->student('A', 'Ana');
        $b = $this->student('B', 'Ben');
        $this->student('C', 'Cara');

        $this->announce(['target_audience' => ['student', 'section:all', 'section:BSIT-3A', 'section:BSIT-3B']]);

        $this->assertSame([$a->student_id, $b->student_id], $this->notifiedStudentIds());
    }

    public function test_a_mentioned_team_is_notified_and_the_rest_of_the_section_is_not(): void
    {
        $member = $this->student('A', 'Member');
        $classmate = $this->student('A', 'Classmate');
        $team = $this->team('Bug Busters', $member);

        $this->announce(['body' => '@Bug Busters please bring the signed form.', 'team_id' => $team->team_id]);

        $this->assertSame([$member->student_id], $this->notifiedStudentIds());
        $this->assertSame('team_mention', Notification::sole()->type);
        $this->assertTrue($this->dashboardShows($member, 'Chapter 3 deadline'));
        $this->assertFalse($this->dashboardShows($classmate, 'Chapter 3 deadline'));
    }

    public function test_a_team_mentioned_in_an_expert_only_post_is_not_notified(): void
    {
        $member = $this->student('A', 'Member');
        $team = $this->team('Bug Busters', $member);

        $this->announce(['target_audience' => ['expert'], 'team_id' => $team->team_id]);

        $this->assertSame([], $this->notifiedStudentIds());
        Mail::assertNotQueued(NotificationMail::class, fn (NotificationMail $mail) => $mail->hasTo($member->email));
    }

    public function test_only_important_and_urgent_posts_are_emailed_to_students(): void
    {
        $student = $this->student('A', 'Ana');

        $this->announce(['title' => 'Plain notice']);
        Mail::assertNotQueued(NotificationMail::class);

        $this->announce(['title' => 'Urgent notice', 'priority' => 'urgent']);
        Mail::assertQueued(NotificationMail::class, fn (NotificationMail $mail) => $mail->hasTo($student->email) && $mail->headline === 'Urgent notice');
    }

    public function test_a_team_mention_is_emailed_whatever_the_priority(): void
    {
        $member = $this->student('A', 'Member');
        $team = $this->team('Bug Busters', $member);

        $this->announce(['team_id' => $team->team_id]);

        Mail::assertQueued(NotificationMail::class, fn (NotificationMail $mail) => $mail->hasTo($member->email));
    }

    public function test_a_student_who_turned_announcement_emails_off_gets_the_bell_only(): void
    {
        $student = $this->student('A', 'Ana');
        $student->forceFill(['notification_prefs' => ['email.announcement' => false]])->save();

        $this->announce(['priority' => 'important']);

        $this->assertSame([$student->student_id], $this->notifiedStudentIds());
        Mail::assertNotQueued(NotificationMail::class);
    }

    public function test_the_email_has_the_whole_post_and_a_link_that_turns_these_emails_off(): void
    {
        $student = $this->student('A', 'Ana');
        $body = str_repeat('Bring two printed copies of chapter 3. ', 8);

        $this->announce(['priority' => 'important', 'body' => $body]);

        $mail = null;
        Mail::assertQueued(NotificationMail::class, function (NotificationMail $m) use (&$mail) {
            return $mail = $m;
        });
        $this->assertSame(trim($body), trim($mail->body));
        $this->assertSame($this->capsa->name, $mail->poster);
        $this->assertSame('<'.$mail->offUrl.'>', $mail->headers()->text['List-Unsubscribe']);

        // Opening the link only asks; a scanner that follows it changes nothing.
        $this->get($mail->offUrl)->assertOk()->assertSee('Turn off announcement emails?');
        $this->assertTrue($student->fresh()->wants('announcement', 'email'));

        $this->post($mail->offUrl.'x')->assertForbidden();
        $this->post($mail->offUrl)->assertOk()->assertSee('Announcement emails are off');
        $this->assertFalse($student->fresh()->wants('announcement', 'email'));
    }

    public function test_experts_are_notified_and_emailed_but_not_the_poster(): void
    {
        $expert = CapstoneExpert::factory()->create(['approved_status' => true]);
        $pending = CapstoneExpert::factory()->create(['approved_status' => false]);

        $this->announce(['target_audience' => ['expert'], 'priority' => 'important']);

        $this->assertSame([$expert->expert_id], Notification::whereNotNull('expert_id')->pluck('expert_id')->all());
        Mail::assertQueued(NotificationMail::class, fn (NotificationMail $mail) => $mail->hasTo($expert->email));
        Mail::assertNotQueued(NotificationMail::class, fn (NotificationMail $mail) => $mail->hasTo($this->capsa->email) || $mail->hasTo($pending->email));
    }

    public function test_a_post_with_no_audience_is_refused_and_nothing_is_sent(): void
    {
        $this->student('A', 'Ana');

        $this->actingAs($this->capsa, 'expert')
            ->postJson(route('api.admin.announcements.store'), [
                'title' => 'No audience',
                'body' => 'Body',
                'priority' => 'urgent',
                'target_audience' => [],
            ])
            ->assertUnprocessable();

        $this->assertSame(0, Announcement::count());
        $this->assertSame([], $this->notifiedStudentIds());
        Mail::assertNothingQueued();
    }
}
