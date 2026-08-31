<?php

namespace Tests\Feature;

use App\Jobs\SyncConsultationCancellation;
use App\Mail\ConsultationCancelledMail;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ConsultationCancellationTest extends TestCase
{
    use RefreshDatabase;

    private function makeExpert(): int
    {
        return DB::table('capstone_experts')->insertGetId([
            'email' => 'adviser@gmail.com',
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

    public function test_cancelling_a_booking_requires_a_reason(): void
    {
        $student = Student::factory()->withCompleteProfile()->create(['email' => 'canceller@my.cspc.edu.ph']);
        ['bookingId' => $bookingId] = $this->makeBooking($student);

        $response = $this->actingAs($student, 'web')
            ->postJson(route('student.consultation.cancel', $bookingId), []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['reason']);

        $this->assertSame('scheduled', DB::table('consultation_booking')->where('booking_id', $bookingId)->first()->booking_status);
    }

    public function test_cancelling_stores_the_reason_frees_the_slot_and_emails_the_expert(): void
    {
        Mail::fake();

        $student = Student::factory()->withCompleteProfile()->create([
            'email' => 'canceller2@my.cspc.edu.ph',
            'f_name' => 'Juan',
            'l_name' => 'Dela Cruz',
        ]);
        ['bookingId' => $bookingId, 'scheduleId' => $scheduleId, 'expertId' => $expertId] = $this->makeBooking($student, 'Team Notify');

        $response = $this->actingAs($student, 'web')
            ->postJson(route('student.consultation.cancel', $bookingId), [
                'reason' => 'Team conflict with another deadline.',
            ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $booking = DB::table('consultation_booking')->where('booking_id', $bookingId)->first();
        $this->assertSame('cancelled', $booking->booking_status);
        $this->assertSame('Team conflict with another deadline.', $booking->cancellation_reason);

        $schedule = DB::table('consultation_schedule')->where('schedule_id', $scheduleId)->first();
        $this->assertTrue((bool) $schedule->is_available);

        $expertEmail = DB::table('capstone_experts')->where('expert_id', $expertId)->first()->email;

        Mail::assertSent(ConsultationCancelledMail::class, function ($mail) use ($expertEmail, $bookingId) {
            return $mail->hasTo($expertEmail)
                && $mail->reason === 'Team conflict with another deadline.'
                && $mail->studentName === 'Juan Dela Cruz'
                && $mail->booking->booking_id === $bookingId;
        });
    }

    public function test_a_student_cannot_cancel_another_students_booking(): void
    {
        $owner = Student::factory()->withCompleteProfile()->create(['email' => 'owner@my.cspc.edu.ph']);
        ['bookingId' => $bookingId] = $this->makeBooking($owner);

        $intruder = Student::factory()->withCompleteProfile()->create(['email' => 'intruder@my.cspc.edu.ph']);

        $response = $this->actingAs($intruder, 'web')
            ->postJson(route('student.consultation.cancel', $bookingId), [
                'reason' => 'Not my booking.',
            ]);

        $response->assertStatus(404);
        $this->assertSame('scheduled', DB::table('consultation_booking')->where('booking_id', $bookingId)->first()->booking_status);
    }

    public function test_an_already_cancelled_booking_cannot_be_cancelled_again(): void
    {
        $student = Student::factory()->withCompleteProfile()->create(['email' => 'canceller3@my.cspc.edu.ph']);
        ['bookingId' => $bookingId] = $this->makeBooking($student);

        DB::table('consultation_booking')->where('booking_id', $bookingId)->update(['booking_status' => 'cancelled']);

        $response = $this->actingAs($student, 'web')
            ->postJson(route('student.consultation.cancel', $bookingId), [
                'reason' => 'Already gone.',
            ]);

        $response->assertStatus(404);
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

        $response = $this->actingAs($teammate, 'web')->get(route('student.consultation'));

        $response->assertOk();
        $response->assertSee('Team Shared');
        $response->assertSee('data-booking-id="'.$bookingId.'"', false);
    }

    public function test_a_teammate_who_did_not_book_it_can_still_cancel_it(): void
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

        $response = $this->actingAs($teammate, 'web')
            ->postJson(route('student.consultation.cancel', $bookingId), [
                'reason' => 'Cancelling on behalf of the team.',
            ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $this->assertSame('cancelled', DB::table('consultation_booking')->where('booking_id', $bookingId)->first()->booking_status);
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

        $this->assertSame('scheduled', DB::table('consultation_booking')->where('booking_id', $bookingId)->value('booking_status'));
    }

    /** The day after the cutoff is fine. */
    public function test_a_session_further_out_can_still_be_cancelled(): void
    {
        $student = Student::factory()->withCompleteProfile()->create(['email' => 'later@my.cspc.edu.ph']);
        ['bookingId' => $bookingId] = $this->makeBooking($student, 'Team Later', 4);

        $this->actingAs($student, 'web')
            ->postJson(route('student.consultation.cancel', $bookingId), ['reason' => 'Something came up.'])
            ->assertOk();

        $this->assertSame('cancelled', DB::table('consultation_booking')->where('booking_id', $bookingId)->value('booking_status'));
    }

    /**
     * Cancelling used to sit for about three seconds: it waited on an SMTP
     * round trip and on Google Calendar before answering. Neither decides
     * anything, so both happen after the answer goes back.
     */
    public function test_cancelling_does_not_wait_on_mail_or_google(): void
    {
        Queue::fake();

        $student = Student::factory()->withCompleteProfile()->create(['email' => 'quick@my.cspc.edu.ph']);
        ['bookingId' => $bookingId] = $this->makeBooking($student, 'Team Quick');

        $this->actingAs($student, 'web')
            ->postJson(route('student.consultation.cancel', $bookingId), ['reason' => 'Clash.'])
            ->assertOk();

        Queue::assertPushed(SyncConsultationCancellation::class, function ($job) use ($bookingId) {
            return $job->bookingId === $bookingId && $job->reason === 'Clash.';
        });

        // Freeing the slot is the part the student can see, so it still
        // happens before the response.
        $this->assertSame('cancelled', DB::table('consultation_booking')->where('booking_id', $bookingId)->value('booking_status'));
    }
}
