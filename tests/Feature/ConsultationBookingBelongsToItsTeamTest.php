<?php

namespace Tests\Feature;

use App\Mail\ConsultationReminderMail;
use App\Models\CapstoneExpert;
use App\Models\ConsultationBooking;
use App\Models\Notification;
use App\Models\Role;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * A consultation belongs to the team it was booked for.
 *
 * Bookings used to record only the student who clicked Book, and every page
 * worked the team out from where that student is now. A student who moved
 * to another team took their old team's consultations with them: the new
 * team - with no expert of its own - saw them and could ask to cancel them,
 * and the team they left stopped seeing them.
 */
class ConsultationBookingBelongsToItsTeamTest extends TestCase
{
    use RefreshDatabase;

    private function team(string $name): int
    {
        return DB::table('team')->insertGetId(['team_name' => $name, 'team_status' => 'Active']);
    }

    private function member(int $teamId): Student
    {
        $student = Student::factory()->withCompleteProfile()->create();
        DB::table('student_s_y')->insert(['student_id' => $student->student_id, 'team_id' => $teamId, 'program' => 'BSIT', 'student_status' => 'Active']);

        return $student;
    }

    private function moveTo(Student $student, int $teamId): void
    {
        DB::table('student_s_y')->where('student_id', $student->student_id)->update(['team_id' => $teamId]);
    }

    /** An approved adviser engaged by the team, with one weekly Monday slot. */
    private function adviserOf(int $teamId, string $name = 'Grom Hellscream'): array
    {
        [$first, $last] = explode(' ', $name, 2);
        $expert = CapstoneExpert::factory()->create(['approved_status' => true, 'first_name' => $first, 'last_name' => $last]);
        $roleId = Role::firstOrCreate(['role_name' => 'adviser'])->role_id;
        $expertRoleId = DB::table('expert_roles')->insertGetId(['expert_id' => $expert->expert_id, 'role_id' => $roleId]);
        DB::table('capstone_expert_application')->insert([
            'team_id' => $teamId, 'expert_id' => $expert->expert_id, 'expert_role_id' => $expertRoleId,
            'status' => 'approved', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $scheduleId = DB::table('consultation_schedule')->insertGetId([
            'expert_id' => $expert->expert_id, 'available_date' => 'Monday', 'start_time' => '09:00:00', 'end_time' => '10:00:00',
            'consultation_limit' => 1, 'is_available' => true, 'recur' => 'weekly', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return [$expert, $scheduleId];
    }

    private function book(Student $student, int $scheduleId, ?string $date = null): int
    {
        $this->actingAs($student, 'web')
            ->postJson(route('student.consultation.book'), ['schedule_id' => $scheduleId, 'session_date' => $date ?? now()->next('Monday')->toDateString()])
            ->assertOk();

        return (int) ConsultationBooking::latest('booking_id')->value('booking_id');
    }

    /** @return list<int> */
    private function upcomingFor(Student $student): array
    {
        return collect($this->actingAs($student, 'web')->getJson(route('student.consultation.refresh'))->assertOk()->json('upcomingBookings'))
            ->pluck('booking_id')->all();
    }

    public function test_a_booking_records_the_team_it_was_made_for(): void
    {
        $teamId = $this->team('Team Horde');
        $student = $this->member($teamId);
        [, $slot] = $this->adviserOf($teamId);

        $bookingId = $this->book($student, $slot);

        $this->assertSame($teamId, (int) ConsultationBooking::find($bookingId)->team_id);
    }

    public function test_a_student_who_joins_another_team_does_not_bring_their_old_consultations(): void
    {
        $old = $this->team('Team Horde');
        $new = $this->team('Team Without Experts');
        $mover = $this->member($old);
        $newMate = $this->member($new);
        [, $slot] = $this->adviserOf($old);
        $bookingId = $this->book($mover, $slot);

        $this->moveTo($mover, $new);

        $this->assertNotContains($bookingId, $this->upcomingFor($newMate), 'the new team has no expert and booked nothing');
        $this->assertNotContains($bookingId, $this->upcomingFor($mover));
    }

    public function test_the_team_that_was_left_keeps_its_consultation(): void
    {
        $old = $this->team('Team Horde');
        $mover = $this->member($old);
        $stayer = $this->member($old);
        [, $slot] = $this->adviserOf($old);
        $bookingId = $this->book($mover, $slot);

        $this->moveTo($mover, $this->team('Team Alliance'));

        $this->assertContains($bookingId, $this->upcomingFor($stayer));
    }

    public function test_only_the_team_it_was_booked_for_can_ask_to_cancel_it(): void
    {
        $old = $this->team('Team Horde');
        $new = $this->team('Team Without Experts');
        $mover = $this->member($old);
        $stayer = $this->member($old);
        $newMate = $this->member($new);
        [, $slot] = $this->adviserOf($old);
        $bookingId = $this->book($mover, $slot, now()->addWeeks(2)->next('Monday')->toDateString());

        $this->moveTo($mover, $new);
        $reason = ['reason' => 'We cannot make it on that day.'];

        $this->actingAs($newMate, 'web')->postJson(route('student.consultation.cancel', $bookingId), $reason)->assertNotFound();
        $this->actingAs($mover, 'web')->postJson(route('student.consultation.cancel', $bookingId), $reason)->assertNotFound();
        $this->actingAs($stayer, 'web')->postJson(route('student.consultation.cancel', $bookingId), $reason)->assertOk();
    }

    public function test_a_joining_student_does_not_block_their_new_team_from_booking_the_same_expert(): void
    {
        $old = $this->team('Team Horde');
        $new = $this->team('Team Alliance');
        $mover = $this->member($old);
        $newMate = $this->member($new);
        [$expert, $slot] = $this->adviserOf($old);
        $this->book($mover, $slot);

        // The same adviser also advises the new team, on another day.
        $roleRow = DB::table('capstone_expert_application')->where('expert_id', $expert->expert_id)->first();
        DB::table('capstone_expert_application')->insert([
            'team_id' => $new, 'expert_id' => $expert->expert_id, 'expert_role_id' => $roleRow->expert_role_id,
            'status' => 'approved', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $tuesday = DB::table('consultation_schedule')->insertGetId([
            'expert_id' => $expert->expert_id, 'available_date' => 'Tuesday', 'start_time' => '09:00:00', 'end_time' => '10:00:00',
            'consultation_limit' => 1, 'is_available' => true, 'recur' => 'weekly', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->moveTo($mover, $new);

        $this->actingAs($newMate, 'web')
            ->postJson(route('student.consultation.book'), ['schedule_id' => $tuesday, 'session_date' => now()->next('Tuesday')->toDateString()])
            ->assertOk();
    }

    public function test_the_expert_sees_the_team_it_was_booked_for(): void
    {
        $old = $this->team('Team Horde');
        $mover = $this->member($old);
        [$expert, $slot] = $this->adviserOf($old);
        $bookingId = $this->book($mover, $slot);

        $this->moveTo($mover, $this->team('Team Alliance'));

        $booked = collect($this->actingAs($expert, 'expert')->getJson('/instructor/schedule/slots')->assertOk()->json('slots'))
            ->where('booking_id', $bookingId);

        $this->assertCount(1, $booked);
        $this->assertSame('Team Horde', $booked->first()['team_name']);
    }

    public function test_the_reminder_goes_to_the_team_it_was_booked_for(): void
    {
        Mail::fake();
        $old = $this->team('Team Horde');
        $new = $this->team('Team Alliance');
        $mover = $this->member($old);
        $stayer = $this->member($old);
        $newMate = $this->member($new);
        [$expert, $slot] = $this->adviserOf($old);
        DB::table('consultation_schedule')->where('schedule_id', $slot)->update(['end_time' => '23:59:00']);
        DB::table('consultation_booking')->insert([
            'student_id' => $mover->student_id, 'team_id' => $old, 'schedule_id' => $slot,
            'session_date' => today()->toDateString(), 'booking_status' => 'scheduled', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->moveTo($mover, $new);
        $this->artisan('consultations:send-reminders')->assertSuccessful();

        Mail::assertQueued(ConsultationReminderMail::class, fn ($mail) => $mail->hasTo($expert->email)
            && $mail->hasCc($stayer->email)
            && ! $mail->hasCc($newMate->email));
    }

    // ----------------------------------------------------------------
    // Bookings made before the team was recorded
    // ----------------------------------------------------------------

    private function oldBooking(Student $student, int $scheduleId): int
    {
        return DB::table('consultation_booking')->insertGetId([
            'student_id' => $student->student_id, 'schedule_id' => $scheduleId,
            'session_date' => now()->next('Monday')->toDateString(), 'booking_status' => 'scheduled',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_an_old_booking_gets_the_team_named_in_the_experts_notice(): void
    {
        $old = $this->team('Team Horde');
        $mover = $this->member($old);
        [$expert, $slot] = $this->adviserOf($old);
        $bookingId = $this->oldBooking($mover, $slot);
        Notification::create([
            'expert_id' => $expert->expert_id, 'type' => 'consultation_booked', 'title' => 'Consultation booked',
            'data' => ['team_id' => $old, 'booking_id' => $bookingId], 'is_read' => false, 'created_at' => now(),
        ]);
        $this->moveTo($mover, $this->team('Team Alliance'));

        ConsultationBooking::fillMissingTeams();

        $this->assertSame($old, (int) ConsultationBooking::find($bookingId)->team_id);
    }

    public function test_an_old_booking_gets_the_students_team_when_that_team_has_the_expert(): void
    {
        $teamId = $this->team('Team Horde');
        $student = $this->member($teamId);
        [, $slot] = $this->adviserOf($teamId);
        $bookingId = $this->oldBooking($student, $slot);

        ConsultationBooking::fillMissingTeams();

        $this->assertSame($teamId, (int) ConsultationBooking::find($bookingId)->team_id);
    }

    public function test_an_old_booking_of_a_student_who_moved_is_not_given_their_new_team(): void
    {
        $old = $this->team('Team Horde');
        $new = $this->team('Team Without Experts');
        $mover = $this->member($old);
        $newMate = $this->member($new);
        [, $slot] = $this->adviserOf($old);
        $bookingId = $this->oldBooking($mover, $slot);
        $this->moveTo($mover, $new);

        ConsultationBooking::fillMissingTeams();

        $this->assertNull(ConsultationBooking::find($bookingId)->team_id, 'unknown is left empty, not guessed');
        $this->assertNotContains($bookingId, $this->upcomingFor($newMate));
        $this->assertContains($bookingId, $this->upcomingFor($mover), 'the student who made it still sees it');
    }
}
