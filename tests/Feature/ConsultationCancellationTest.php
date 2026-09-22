<?php

namespace Tests\Feature;

use App\Jobs\SendWebPush;
use App\Jobs\SyncConsultationCancellation;
use App\Mail\ConsultationCancelledMail;
use App\Models\CapstoneExpert;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * A team asks to cancel a consultation and the expert decides. Until the
 * expert approves, the booking stays on and the slot stays held.
 */
class ConsultationCancellationTest extends TestCase
{
    use RefreshDatabase;

    private function makeExpert(string $email = 'adviser@gmail.com'): int
    {
        return DB::table('capstone_experts')->insertGetId([
            'email' => $email,
            'first_name' => 'Ada',
            'last_name' => 'Adviser',
            'is_admin' => false,
            'approved_status' => true,
            'created_at' => now(),
        ]);
    }

    private function makeBooking(Student $student, string $teamName = 'Team Cancel', int $daysAhead = 10): array
    {
        $team = DB::table('team')->insertGetId(['team_name' => $teamName, 'team_status' => 'Active']);
        DB::table('student_s_y')->insert([
            'student_id' => $student->student_id,
            'team_id' => $team,
            'program' => 'BSCS',
            'student_status' => 'Active',
        ]);

        $expertId = $this->makeExpert();

        $scheduleId = DB::table('consultation_schedule')->insertGetId([
            'expert_id' => $expertId,
            'available_date' => 'Wednesday',
            'start_time' => '13:00:00',
            'end_time' => '14:00:00',
            'consultation_limit' => 1,
            'is_available' => false,
            'recur' => 'once',
            'specific_date' => now()->addDays($daysAhead)->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $bookingId = DB::table('consultation_booking')->insertGetId([
            'student_id' => $student->student_id,
            'schedule_id' => $scheduleId,
            'session_date' => now()->addDays($daysAhead)->toDateString(),
            'booking_status' => 'scheduled',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return compact('bookingId', 'scheduleId', 'expertId', 'team');
    }

    private function booking(int $id): object
    {
        return DB::table('consultation_booking')->where('booking_id', $id)->first();
    }

    private function decide(int $expertId, int $bookingId, string $decision, ?string $reason = 'This is the last session before your defense.')
    {
        return $this->actingAs(CapstoneExpert::find($expertId), 'expert')
            ->patchJson("/instructor/schedule/bookings/{$bookingId}/cancellation", ['decision' => $decision, 'reason' => $reason]);
    }

    public function test_cancelling_a_booking_requires_a_reason(): void
    {
        $student = Student::factory()->withCompleteProfile()->create(['email' => 'canceller@my.cspc.edu.ph']);
        ['bookingId' => $bookingId] = $this->makeBooking($student);

        $response = $this->actingAs($student, 'web')
            ->postJson(route('student.consultation.cancel', $bookingId), []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['reason']);

        $this->assertSame('scheduled', $this->booking($bookingId)->booking_status);
    }

    public function test_asking_to_cancel_keeps_the_booking_until_the_expert_decides(): void
    {
        Queue::fake();

        $student = Student::factory()->withCompleteProfile()->create(['email' => 'asker@my.cspc.edu.ph']);
        ['bookingId' => $bookingId, 'scheduleId' => $scheduleId] = $this->makeBooking($student, 'Team Ask');

        $this->actingAs($student, 'web')
            ->postJson(route('student.consultation.cancel', $bookingId), ['reason' => 'Team conflict with another deadline.'])
            ->assertOk()
            ->assertJson(['success' => true]);

        $booking = $this->booking($bookingId);
        $this->assertSame('scheduled', $booking->booking_status);
        $this->assertNotNull($booking->cancellation_requested_at);
        $this->assertSame('Team conflict with another deadline.', $booking->cancellation_reason);
        $this->assertFalse((bool) DB::table('consultation_schedule')->where('schedule_id', $scheduleId)->value('is_available'));
        // The expert is told straight away, but nothing is undone yet: the
        // slot and the calendar are only touched once they approve.
        Queue::assertPushed(SendWebPush::class);
        Queue::assertNotPushed(SyncConsultationCancellation::class);

        // Asking twice is refused.
        $this->actingAs($student, 'web')
            ->postJson(route('student.consultation.cancel', $bookingId), ['reason' => 'Again.'])
            ->assertStatus(422);
    }

    public function test_the_expert_approving_cancels_it_frees_the_slot_and_emails(): void
    {
        Mail::fake();

        $student = Student::factory()->withCompleteProfile()->create([
            'email' => 'canceller2@my.cspc.edu.ph',
            'f_name' => 'Juan',
            'l_name' => 'Dela Cruz',
        ]);
        ['bookingId' => $bookingId, 'scheduleId' => $scheduleId, 'expertId' => $expertId] = $this->makeBooking($student, 'Team Notify');

        $this->actingAs($student, 'web')
            ->postJson(route('student.consultation.cancel', $bookingId), ['reason' => 'Team conflict with another deadline.'])
            ->assertOk();

        $this->decide($expertId, $bookingId, 'approve')->assertOk();

        $this->assertSame('cancelled', $this->booking($bookingId)->booking_status);
        $this->assertTrue((bool) DB::table('consultation_schedule')->where('schedule_id', $scheduleId)->value('is_available'));
        $this->assertDatabaseHas('notifications', ['student_id' => $student->student_id, 'title' => 'Cancellation approved']);

        $expertEmail = DB::table('capstone_experts')->where('expert_id', $expertId)->value('email');
        Mail::assertSent(ConsultationCancelledMail::class, fn ($mail) => $mail->hasTo($expertEmail)
            && $mail->reason === 'Team conflict with another deadline.'
            && $mail->studentName === 'Juan Dela Cruz'
            && $mail->booking->booking_id === $bookingId);
    }

    public function test_the_expert_declining_keeps_the_booking(): void
    {
        $student = Student::factory()->withCompleteProfile()->create(['email' => 'declined@my.cspc.edu.ph']);
        ['bookingId' => $bookingId, 'expertId' => $expertId] = $this->makeBooking($student, 'Team Kept');

        $this->actingAs($student, 'web')
            ->postJson(route('student.consultation.cancel', $bookingId), ['reason' => 'Something came up.'])
            ->assertOk();

        // Declining without saying why is refused, and the booking is untouched.
        $this->decide($expertId, $bookingId, 'decline', null)->assertStatus(422);
        $this->assertNotNull($this->booking($bookingId)->cancellation_requested_at);

        $this->decide($expertId, $bookingId, 'decline')->assertOk();

        $booking = $this->booking($bookingId);
        $this->assertSame('scheduled', $booking->booking_status);
        $this->assertNull($booking->cancellation_requested_at);
        $this->assertDatabaseHas('notifications', ['student_id' => $student->student_id, 'title' => 'Cancellation declined']);
    }

    public function test_only_the_booked_expert_decides(): void
    {
        $student = Student::factory()->withCompleteProfile()->create(['email' => 'other@my.cspc.edu.ph']);
        ['bookingId' => $bookingId] = $this->makeBooking($student, 'Team Other');

        $this->actingAs($student, 'web')
            ->postJson(route('student.consultation.cancel', $bookingId), ['reason' => 'Clash.'])
            ->assertOk();

        $this->decide($this->makeExpert('someone.else@gmail.com'), $bookingId, 'approve')->assertNotFound();
        $this->assertSame('scheduled', $this->booking($bookingId)->booking_status);
    }

    public function test_a_student_cannot_cancel_another_students_booking(): void
    {
        $owner = Student::factory()->withCompleteProfile()->create(['email' => 'owner@my.cspc.edu.ph']);
        ['bookingId' => $bookingId] = $this->makeBooking($owner);

        $intruder = Student::factory()->withCompleteProfile()->create(['email' => 'intruder@my.cspc.edu.ph']);

        $this->actingAs($intruder, 'web')
            ->postJson(route('student.consultation.cancel', $bookingId), ['reason' => 'Not my booking.'])
            ->assertStatus(404);

        $this->assertNull($this->booking($bookingId)->cancellation_requested_at);
    }

    public function test_an_already_cancelled_booking_cannot_be_cancelled_again(): void
    {
        $student = Student::factory()->withCompleteProfile()->create(['email' => 'canceller3@my.cspc.edu.ph']);
        ['bookingId' => $bookingId] = $this->makeBooking($student);

        DB::table('consultation_booking')->where('booking_id', $bookingId)->update(['booking_status' => 'cancelled']);

        $this->actingAs($student, 'web')
            ->postJson(route('student.consultation.cancel', $bookingId), ['reason' => 'Already gone.'])
            ->assertStatus(404);
    }

    public function test_a_teammate_who_did_not_book_it_can_still_see_it_on_their_own_page(): void
    {
        $booker = Student::factory()->withCompleteProfile()->create(['email' => 'booker@my.cspc.edu.ph']);
        ['bookingId' => $bookingId, 'team' => $team] = $this->makeBooking($booker, 'Team Shared');

        $teammate = Student::factory()->withCompleteProfile()->create(['email' => 'teammate@my.cspc.edu.ph']);
        DB::table('student_s_y')->insert([
            'student_id' => $teammate->student_id,
            'team_id' => $team,
            'program' => 'BSCS',
            'student_status' => 'Active',
        ]);

        $this->actingAs($teammate, 'web')->get(route('student.consultation'))
            ->assertOk()
            ->assertSee('data-booking-id="'.$bookingId.'"', false);
    }

    public function test_a_teammate_who_did_not_book_it_can_still_ask_to_cancel_it(): void
    {
        $booker = Student::factory()->withCompleteProfile()->create(['email' => 'booker2@my.cspc.edu.ph']);
        ['bookingId' => $bookingId, 'team' => $team] = $this->makeBooking($booker, 'Team Shared Cancel');

        $teammate = Student::factory()->withCompleteProfile()->create(['email' => 'teammate2@my.cspc.edu.ph']);
        DB::table('student_s_y')->insert([
            'student_id' => $teammate->student_id,
            'team_id' => $team,
            'program' => 'BSCS',
            'student_status' => 'Active',
        ]);

        $this->actingAs($teammate, 'web')
            ->postJson(route('student.consultation.cancel', $bookingId), ['reason' => 'Cancelling on behalf of the team.'])
            ->assertOk();

        $this->assertNotNull($this->booking($bookingId)->cancellation_requested_at);
    }

    /**
     * An expert who has kept a time free deserves more notice than a day,
     * and a team pulling out the night before leaves a slot nobody can take.
     */
    public function test_a_session_within_three_days_cannot_be_cancelled(): void
    {
        $student = Student::factory()->withCompleteProfile()->create(['email' => 'soon@my.cspc.edu.ph']);
        ['bookingId' => $bookingId] = $this->makeBooking($student, 'Team Soon', 2);

        $response = $this->actingAs($student, 'web')
            ->postJson(route('student.consultation.cancel', $bookingId), ['reason' => 'Something came up.']);

        $response->assertStatus(422);
        $this->assertStringContainsString('cancelled more than 3 days ahead', $response->json('message'));
        $this->assertNull($this->booking($bookingId)->cancellation_requested_at);
    }

    /**
     * Approving does not wait on mail or Google: both run after the answer.
     */
    public function test_approving_does_not_wait_on_mail_or_google(): void
    {
        Queue::fake();

        $student = Student::factory()->withCompleteProfile()->create(['email' => 'quick@my.cspc.edu.ph']);
        ['bookingId' => $bookingId, 'expertId' => $expertId] = $this->makeBooking($student, 'Team Quick');

        $this->actingAs($student, 'web')
            ->postJson(route('student.consultation.cancel', $bookingId), ['reason' => 'Clash.'])
            ->assertOk();
        $this->decide($expertId, $bookingId, 'approve')->assertOk();

        Queue::assertPushed(SyncConsultationCancellation::class, fn ($job) => $job->bookingId === $bookingId && $job->reason === 'Clash.');
        $this->assertSame('cancelled', $this->booking($bookingId)->booking_status);
    }
}
