<?php

namespace Tests\Feature;

use App\Mail\NotificationMail;
use App\Models\CapstoneExpert;
use App\Models\Notification;
use App\Models\Role;
use App\Models\Student;
use App\Services\Notifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * An important notification reaches the person's Google mail as well as the
 * bell - in every portal, because every portal writes the bell through the
 * Notification model or the Notifier.
 */
class NotificationEmailTest extends TestCase
{
    use RefreshDatabase;

    private function student(array $attributes = []): Student
    {
        return Student::factory()->withCompleteProfile()->create($attributes);
    }

    private function expert(array $attributes = []): CapstoneExpert
    {
        $expert = CapstoneExpert::factory()->create($attributes + ['approved_status' => true, 'is_admin' => false]);
        $expert->roles()->attach(Role::firstOrCreate(['role_name' => 'adviser'])->role_id);

        return $expert;
    }

    private function bell(Student $student, string $type, string $title = 'Something happened'): void
    {
        Notification::create([
            'student_id' => $student->student_id,
            'type' => $type,
            'title' => $title,
            'message' => 'Your team <strong>Team &amp; Co</strong> did a thing.',
            'link' => '/student/dashboard',
            'is_read' => false,
        ]);
    }

    public function test_an_important_student_notification_is_emailed_as_plain_text(): void
    {
        Mail::fake();
        $student = $this->student();

        $this->bell($student, 'invitation_received', 'Team Invitation');

        Mail::assertQueued(NotificationMail::class, function (NotificationMail $mail) use ($student) {
            return $mail->hasTo($student->email)
                && $mail->headline === 'Team Invitation'
                && $mail->body === 'Your team Team & Co did a thing.'
                && $mail->url === url('/student/dashboard');
        });
    }

    public function test_the_rendered_email_shows_the_message_and_a_link(): void
    {
        $mail = new NotificationMail('Ana', 'Team Invitation', 'You were invited.', url('/student/dashboard'));

        $mail->assertSeeInHtml('Hi Ana');
        $mail->assertSeeInHtml('You were invited.');
        $mail->assertSeeInHtml(url('/student/dashboard'));
        $this->assertSame('CapsTrack: Team Invitation', $mail->envelope()->subject);
    }

    public function test_an_important_expert_notification_is_emailed(): void
    {
        Mail::fake();
        $expert = $this->expert();

        Notifier::toExpert($expert, 'document_submitted', 'Document submitted', 'A team sent a document.', '/instructor/docreview');

        Mail::assertQueued(NotificationMail::class, fn (NotificationMail $mail) => $mail->hasTo($expert->email));
        $this->assertSame(1, Notification::where('expert_id', $expert->expert_id)->count());
    }

    public function test_every_portal_gets_the_email_including_capsa(): void
    {
        Mail::fake();
        $capsa = CapstoneExpert::factory()->create(['approved_status' => true, 'is_admin' => false]);
        $capsa->roles()->attach(Role::firstOrCreate(['role_name' => CapstoneExpert::CAPSA_ROLE])->role_id);

        Notifier::toExpert($capsa, 'leave_request_received', 'Leave request', 'A student wants to leave.');

        Mail::assertQueued(NotificationMail::class, fn (NotificationMail $mail) => $mail->hasTo($capsa->email));
    }

    public function test_a_person_who_switched_the_email_off_is_not_emailed_but_still_gets_the_bell(): void
    {
        Mail::fake();
        $student = $this->student(['notification_prefs' => ['email.invitation_received' => false]]);

        $this->bell($student, 'invitation_received');

        Mail::assertNothingQueued();
        $this->assertSame(1, Notification::where('student_id', $student->student_id)->count());
    }

    public function test_the_top_switch_silences_the_email_too(): void
    {
        Mail::fake();
        $student = $this->student(['notification_prefs' => ['all' => false]]);

        $this->bell($student, 'team_verified');

        Mail::assertNothingQueued();
    }

    public function test_types_that_share_one_switch_are_governed_by_it(): void
    {
        Mail::fake();
        $student = $this->student(['notification_prefs' => ['email.leave_request_decided' => false]]);

        $this->bell($student, 'leave_request_declined');
        $this->bell($student, 'leave_request_approved');

        Mail::assertNothingQueued();

        $other = $this->student();
        $this->bell($other, 'leave_request_declined');

        Mail::assertQueued(NotificationMail::class, fn (NotificationMail $mail) => $mail->hasTo($other->email));
    }

    /** An expert's bell keeps everything now, even with a bell switch left off on an older page, and the email still goes. */
    public function test_an_old_bell_switch_does_not_hide_anything_from_an_expert_or_lose_the_email(): void
    {
        Mail::fake();
        $expert = $this->expert(['notification_prefs' => ['web.document_submitted' => false]]);

        Notifier::toExpert($expert, 'document_submitted', 'Document submitted', 'A team sent a document.');

        $this->assertSame(1, Notification::where('expert_id', $expert->expert_id)->count());
        Mail::assertQueued(NotificationMail::class, fn (NotificationMail $mail) => $mail->hasTo($expert->email));
    }

    public function test_events_with_their_own_email_do_not_get_a_second_one_from_the_bell(): void
    {
        Mail::fake();
        $expert = $this->expert();
        $student = $this->student();

        Notifier::toExpert($expert, 'consultation_booked', 'Consultation booked', 'A team booked you.');
        Notifier::toExpert($expert, 'review_reminder', 'Reviews waiting', 'Some are waiting.');
        $this->bell($student, 'defense_scheduled');

        Mail::assertNothingQueued();
    }

    public function test_chatty_or_deliberately_quiet_notifications_stay_in_the_bell(): void
    {
        Mail::fake();
        $student = $this->student();

        foreach (['milestone_due', 'checklist_item_added', 'team_created', 'member_joined', 'team_left'] as $type) {
            $this->bell($student, $type);
        }

        Mail::assertNothingQueued();
        $this->assertSame(5, Notification::where('student_id', $student->student_id)->count());
    }

    public function test_a_failing_mail_never_breaks_the_action_that_caused_it(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('smtp down'));
        $student = $this->student();

        $this->bell($student, 'invitation_received');

        $this->assertSame(1, Notification::where('student_id', $student->student_id)->count());
    }

    public function test_an_important_announcement_is_emailed_to_students_and_experts(): void
    {
        Mail::fake();
        $capsa = $this->expert();
        $capsa->roles()->attach(Role::firstOrCreate(['role_name' => CapstoneExpert::CAPSA_ROLE])->role_id);
        $capsa->update(['sections' => ['BSIT-3A']]);
        $expert = $this->expert();
        $student = $this->student();

        $this->actingAs($capsa, 'expert')->postJson(route('api.admin.announcements.store'), [
            'title' => 'Deadline moved',
            'body' => 'Everything is due Friday.',
            'priority' => 'urgent',
            'target_audience' => ['all'],
        ])->assertOk();

        Mail::assertQueued(NotificationMail::class, fn (NotificationMail $mail) => $mail->hasTo($expert->email) && $mail->headline === 'Deadline moved');
        Mail::assertQueued(NotificationMail::class, fn (NotificationMail $mail) => $mail->hasTo($student->email) && $mail->headline === 'Deadline moved');
    }

    public function test_a_normal_announcement_stays_in_the_bell(): void
    {
        Mail::fake();
        $capsa = $this->expert();
        $capsa->roles()->attach(Role::firstOrCreate(['role_name' => CapstoneExpert::CAPSA_ROLE])->role_id);
        $this->expert();
        $this->student();

        $this->actingAs($capsa, 'expert')->postJson(route('api.admin.announcements.store'), [
            'title' => 'Office hours',
            'body' => 'Open as usual.',
            'priority' => 'normal',
            'target_audience' => ['all'],
        ])->assertOk();

        Mail::assertNothingQueued();
    }

    public function test_the_expert_settings_card_lists_an_email_switch_for_each_new_email(): void
    {
        $expert = $this->expert();

        $this->actingAs($expert, 'expert')->get(route('instructor.profile'))
            ->assertOk()
            ->assertSee('data-types="email.expert_application email.leave_request_received email.expert_slots_short"', false)
            ->assertSee('data-types="email.document_submitted email.paper_to_check email.review_reminder"', false);
    }

    public function test_a_student_can_switch_an_email_off_and_stops_getting_it(): void
    {
        Mail::fake();
        $student = $this->student();

        $this->actingAs($student)->putJson('/notifications/preferences', [
            'all' => true,
            'on' => ['email.team_verified' => false],
        ])->assertOk();

        $this->bell($student->fresh(), 'team_verified');

        Mail::assertNothingQueued();
    }
}
