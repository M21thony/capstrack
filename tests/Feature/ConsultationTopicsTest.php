<?php

namespace Tests\Feature;

use App\Mail\ConsultationBookedMail;
use App\Models\CapstoneExpert;
use App\Models\ConsultationBooking;
use App\Models\ConsultationSchedule;
use App\Models\Deliverable;
use App\Models\Student;
use App\Support\ConsultationTopics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\BuildsChecklists;
use Tests\TestCase;

/**
 * A team says what it is bringing when it books: the paper or the system to
 * its adviser, the diagrams or the system to its consultant, one or both,
 * and always the manuscript to its grammarian. A note is optional.
 */
class ConsultationTopicsTest extends TestCase
{
    use BuildsChecklists, RefreshDatabase;

    private Student $student;

    private int $teamId;

    protected function setUp(): void
    {
        parent::setUp();

        // A Saturday: the next Wednesday is the 21st.
        $this->travelTo('2026-10-17 08:00:00');
        Mail::fake();

        $this->student = Student::factory()->withCompleteProfile()->create(['email' => 'mark@my.cspc.edu.ph']);
        $this->teamId = DB::table('team')->insertGetId(['team_name' => 'Team Lumina', 'team_status' => 'Active']);
        DB::table('student_s_y')->insert([
            'student_id' => $this->student->student_id,
            'team_id' => $this->teamId,
            'program' => 'BSIT',
            'student_status' => 'Active',
        ]);
    }

    /** An expert this team engaged in these roles. */
    private function expertAs(string ...$roles): int
    {
        $expertId = DB::table('capstone_experts')->insertGetId([
            'email' => implode('-', $roles).'@gmail.com',
            'first_name' => 'Ana',
            'last_name' => ucfirst($roles[0]),
            'is_admin' => false,
            'approved_status' => true,
            'created_at' => now(),
        ]);

        foreach ($roles as $role) {
            $roleId = DB::table('roles')->where('role_name', $role)->value('role_id')
                ?? DB::table('roles')->insertGetId(['role_name' => $role, 'created_at' => now(), 'updated_at' => now()]);
            $link = DB::table('expert_roles')->insertGetId(['expert_id' => $expertId, 'role_id' => $roleId]);
            DB::table('capstone_expert_application')->insert([
                'team_id' => $this->teamId,
                'expert_id' => $expertId,
                'expert_role_id' => $link,
                'status' => 'approved',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $expertId;
    }

    private function slot(int $expertId, string $recur = 'weekly', ?string $firstDate = null): int
    {
        return DB::table('consultation_schedule')->insertGetId([
            'expert_id' => $expertId,
            'available_date' => 'Wednesday',
            'start_time' => '09:00:00',
            'end_time' => '09:30:00',
            'consultation_limit' => 1,
            'is_available' => true,
            'recur' => $recur,
            'specific_date' => $firstDate,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function book(int $scheduleId, array $extra = [], string $date = '2026-10-21')
    {
        return $this->actingAs($this->student, 'web')->postJson(
            route('student.consultation.book'),
            ['schedule_id' => $scheduleId, 'session_date' => $date] + $extra
        );
    }

    public function test_the_adviser_is_booked_about_the_paper_the_system_or_both(): void
    {
        $slot = $this->slot($this->expertAs('adviser'));

        $this->book($slot, ['topics' => ['system', 'paper'], 'note' => '  The survey questions.  '])
            ->assertOk()
            ->assertJsonPath('newBooking.topics', 'Paper, System')
            ->assertJsonPath('newBooking.note', 'The survey questions.');

        $booking = ConsultationBooking::sole();
        $this->assertSame(['paper', 'system'], $booking->topics);
        $this->assertSame('The survey questions.', $booking->note);
    }

    public function test_nothing_picked_for_the_adviser_is_refused(): void
    {
        $slot = $this->slot($this->expertAs('adviser'));

        $this->book($slot, ['topics' => []])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Pick what you will consult about: Paper or System.');

        $this->assertSame(0, ConsultationBooking::count());
    }

    public function test_a_page_open_from_before_the_choice_still_books(): void
    {
        $this->book($this->slot($this->expertAs('adviser')))->assertOk();

        $this->assertNull(ConsultationBooking::sole()->topics);
        $this->assertNull(ConsultationBooking::sole()->deliverables);
    }

    public function test_the_team_names_the_deliverables_it_brings_and_the_email_shows_them(): void
    {
        $slot = $this->slot($this->expertAs('adviser'));

        $this->book($slot, ['topics' => ['paper'], 'deliverables' => [' Chapter 1 Draft ', 'chapter 1 draft', '', 'Revised ERD']])
            ->assertOk()
            ->assertJsonPath('newBooking.deliverables', 'Chapter 1 Draft, Revised ERD');

        $this->assertSame(['Chapter 1 Draft', 'Revised ERD'], ConsultationBooking::sole()->deliverables);

        Mail::assertQueued(ConsultationBookedMail::class, function (ConsultationBookedMail $mail) {
            $html = $mail->render();

            return str_contains($html, 'Team:</strong> Team Lumina')
                && str_contains($html, 'Deliverables:</strong> Chapter 1 Draft, Revised ERD');
        });
    }

    public function test_a_page_with_the_deliverables_field_must_name_one(): void
    {
        $slot = $this->slot($this->expertAs('adviser'));

        foreach ([[], ['  ']] as $deliverables) {
            $this->book($slot, ['topics' => ['paper'], 'deliverables' => $deliverables])
                ->assertStatus(422)
                ->assertJsonPath('message', 'Pick or type at least one deliverable.');
        }

        $this->assertSame(0, ConsultationBooking::count());
    }

    public function test_the_booking_window_offers_the_checklist_documents(): void
    {
        $capsa = $this->capsaFor();
        [$team] = $this->teamInSection('Team Orbit');
        $checklist = $this->emptyChecklist($capsa);
        $this->addDeliverable($checklist, ['title' => 'Chapter 1 Draft', 'kind' => Deliverable::DOCUMENT]);
        $this->addDeliverable($checklist, ['title' => 'Adviser Acceptance Form']);
        $this->addDeliverable($checklist, ['title' => 'Revised ERD', 'kind' => Deliverable::CUSTOM]);
        $this->addDeliverable($checklist, ['title' => 'Old Outline', 'kind' => Deliverable::DOCUMENT, 'archived_at' => now()]);

        $board = app(\App\Support\ConsultationBoard::class);
        $this->assertSame(['Chapter 1 Draft', 'Revised ERD'], $board->deliverableOptions($team));
        $this->assertSame([], $board->deliverableOptions(null));
    }

    public function test_the_consultant_covers_the_diagrams_and_the_system_not_the_paper(): void
    {
        $slot = $this->slot($this->expertAs('consultant'));

        $this->book($slot, ['topics' => ['paper']])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Pick what you will consult about: Diagrams or System.');

        $this->book($slot, ['topics' => ['paper', 'diagrams']])->assertOk();

        $this->assertSame(['diagrams'], ConsultationBooking::sole()->topics);
    }

    public function test_the_grammarian_always_checks_the_manuscript(): void
    {
        $slot = $this->slot($this->expertAs('grammarian'));

        $this->book($slot, ['topics' => ['system']])->assertOk()->assertJsonPath('newBooking.topics', 'Manuscript');

        $this->assertSame(['manuscript'], ConsultationBooking::sole()->topics);
    }

    public function test_a_note_longer_than_500_characters_is_refused(): void
    {
        $slot = $this->slot($this->expertAs('adviser'));

        $this->book($slot, ['topics' => ['paper'], 'note' => str_repeat('a', 501)])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['note' => 'Keep the note to 500 characters.']);
    }

    public function test_an_expert_with_no_role_on_the_team_takes_the_booking_as_sent(): void
    {
        $expertId = DB::table('capstone_experts')->insertGetId([
            'email' => 'loose@gmail.com', 'first_name' => 'Lou', 'last_name' => 'Loose',
            'is_admin' => false, 'approved_status' => true, 'created_at' => now(),
        ]);

        $this->book($this->slot($expertId))->assertOk();

        $this->assertNull(ConsultationBooking::sole()->topics);
    }

    public function test_choices_follow_every_role_the_expert_holds(): void
    {
        $this->assertSame(['paper', 'diagrams', 'system'], ConsultationTopics::choicesFor(['consultant', 'adviser']));
        $this->assertNull(ConsultationTopics::fixedFor(['adviser']));
        $this->assertSame('manuscript', ConsultationTopics::fixedFor(['grammarian']));
        $this->assertSame('Diagrams, System', ConsultationTopics::label(['system', 'diagrams']));
    }

    public function test_the_page_knows_what_each_expert_covers_and_shows_it_on_the_booking(): void
    {
        $adviser = $this->expertAs('adviser');
        $this->expertAs('grammarian');
        $this->book($this->slot($adviser), ['topics' => ['paper'], 'note' => 'Chapter 3.'])->assertOk();

        $data = $this->actingAs($this->student, 'web')->getJson(route('student.consultation.refresh'))->assertOk()->json();

        $this->assertSame('Paper', $data['upcomingBookings'][0]['topics']);
        $this->assertSame('Chapter 3.', $data['upcomingBookings'][0]['note']);
        $this->assertSame(trim($this->student->f_name.' '.$this->student->l_name), $data['upcomingBookings'][0]['booked_by']);

        $experts = app(\App\Support\ConsultationBoard::class)->forStudent($this->student)['experts'];
        $this->assertSame(['paper', 'system'], $experts->firstWhere('expert_id', $adviser)->topic_choices);
        $this->assertSame('manuscript', $experts->first(fn ($e) => $e->expert_id !== $adviser)->topic_fixed);
    }

    public function test_the_expert_is_told_what_the_team_will_bring(): void
    {
        $adviser = $this->expertAs('adviser');
        $this->book($this->slot($adviser), ['topics' => ['paper', 'system'], 'note' => 'The sampling method.'])->assertOk();

        $this->assertDatabaseHas('notifications', ['expert_id' => $adviser, 'type' => 'consultation_booked']);
        $this->assertStringContainsString('about Paper, System', DB::table('notifications')->where('expert_id', $adviser)->value('message'));

        Mail::assertQueued(ConsultationBookedMail::class, function (ConsultationBookedMail $mail) {
            $html = $mail->render();

            return str_contains($html, 'About:</strong> Paper, System')
                && str_contains($html, 'Their note:</strong> The sampling method.');
        });
    }

    public function test_the_schedule_hands_the_expert_the_topics_the_note_and_the_bare_date(): void
    {
        $adviser = $this->expertAs('adviser');
        $this->book($this->slot($adviser), ['topics' => ['system'], 'note' => 'Login screens.'])->assertOk();

        $row = collect($this->actingAs(CapstoneExpert::find($adviser), 'expert')
            ->getJson('/instructor/schedule/slots')->assertOk()->json('slots'))
            ->firstWhere('booking_id', ConsultationBooking::sole()->booking_id);

        $this->assertSame('2026-10-21', $row['session_date']);
        $this->assertSame('System', $row['topics']);
        $this->assertSame('Login screens.', $row['note']);
    }

    public function test_every_other_week_skips_the_week_between(): void
    {
        $slot = ConsultationSchedule::find($this->slot($this->expertAs('adviser'), 'biweekly', '2026-10-21'));

        $this->assertTrue($slot->isValidOccurrenceDate('2026-10-21'));
        $this->assertFalse($slot->isValidOccurrenceDate('2026-10-28'));
        $this->assertTrue($slot->isValidOccurrenceDate('2026-11-04'));
        $this->assertSame('2026-11-04', $slot->nextOccurrenceOnOrAfter(now()->setDate(2026, 10, 22)));
        $this->assertSame('2026-10-21', $slot->nextOccurrenceOnOrAfter(now()->setDate(2026, 10, 1)));

        $this->book($slot->schedule_id, ['topics' => ['paper']], '2026-10-28')
            ->assertStatus(422)
            ->assertJsonPath('message', 'That date is not a valid occurrence of this slot.');
    }

    public function test_an_every_other_week_slot_keeps_the_date_it_starts_on(): void
    {
        $expert = CapstoneExpert::find($this->expertAs('adviser'));
        $add = fn (array $extra) => $this->actingAs($expert, 'expert')->postJson('/instructor/schedule/slots', [
            'available_date' => 'Wednesday', 'start_time' => '13:00', 'end_time' => '13:30',
            'consultation_limit' => 1, 'recur' => 'biweekly',
        ] + $extra);

        $id = $add(['specific_date' => '2026-10-28'])->assertOk()->json('schedule_id');
        $this->assertSame('2026-10-28', ConsultationSchedule::find($id)->specific_date);

        // Without one, it starts on the next of its weekday.
        $id = $add(['start_time' => '14:00', 'end_time' => '14:30'])->assertOk()->json('schedule_id');
        $this->assertSame('2026-10-21', ConsultationSchedule::find($id)->specific_date);

        $add(['specific_date' => '2026-10-29'])->assertStatus(422);
    }

    public function test_a_rejected_cancellation_tells_the_team_it_was_rejected(): void
    {
        $adviser = $this->expertAs('adviser');
        $this->travelTo('2026-10-10 08:00:00');
        $this->book($this->slot($adviser), ['topics' => ['paper']])->assertOk();
        $booking = ConsultationBooking::sole();
        $booking->update(['cancellation_requested_at' => now(), 'cancellation_reason' => 'Midterms.']);

        $this->actingAs(CapstoneExpert::find($adviser), 'expert')
            ->patchJson("/instructor/schedule/bookings/{$booking->booking_id}/cancellation", [
                'decision' => 'decline', 'reason' => 'This is the last session before your defense.',
            ])->assertOk();

        $this->assertDatabaseHas('notifications', ['student_id' => $this->student->student_id, 'title' => 'Cancellation rejected']);
    }
}
