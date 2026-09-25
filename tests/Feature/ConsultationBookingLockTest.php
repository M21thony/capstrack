<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\ConsultationBooking;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Two students booking the same one-person slot at the same moment both got
 * in (performance test PT-04). Bookings with one expert now go one at a
 * time: a second request waits for the first, then sees the slot taken.
 */
class ConsultationBookingLockTest extends TestCase
{
    use RefreshDatabase;

    private CapstoneExpert $expert;

    private int $slot;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        config(['capstrack.booking_lock_wait' => 1]);

        $this->expert = CapstoneExpert::factory()->create(['approved_status' => true]);
        $this->slot = DB::table('consultation_schedule')->insertGetId([
            'expert_id' => $this->expert->expert_id, 'available_date' => 'Monday', 'start_time' => '09:00:00', 'end_time' => '10:00:00',
            'consultation_limit' => 1, 'is_available' => true, 'recur' => 'weekly', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function student(string $team): Student
    {
        $student = Student::factory()->withCompleteProfile()->create();
        $teamId = DB::table('team')->insertGetId(['team_name' => $team, 'team_status' => 'Active']);
        DB::table('student_s_y')->insert(['student_id' => $student->student_id, 'team_id' => $teamId, 'program' => 'BSIT', 'student_status' => 'Active']);

        return $student;
    }

    private function book(Student $student)
    {
        return $this->actingAs($student, 'web')->postJson(route('student.consultation.book'), [
            'schedule_id' => $this->slot, 'session_date' => now()->next('Monday')->toDateString(),
        ]);
    }

    public function test_a_booking_waits_while_another_for_the_same_expert_is_being_made(): void
    {
        $inProgress = Cache::lock('consultation-booking:expert:'.$this->expert->expert_id, 20);
        $this->assertTrue($inProgress->get());

        $this->book($this->student('Team One'))
            ->assertStatus(503)
            ->assertJson(['success' => false, 'message' => 'Many students are booking this expert right now. Try again in a moment.']);
        $this->assertSame(0, ConsultationBooking::count());

        $inProgress->release();
        $this->book($this->student('Team Two'))->assertOk();
        $this->assertSame(1, ConsultationBooking::count());
    }

    public function test_the_second_of_two_bookings_for_a_one_person_slot_is_refused(): void
    {
        $this->book($this->student('Team One'))->assertOk();

        $this->book($this->student('Team Two'))
            ->assertStatus(422)
            ->assertJson(['message' => 'This slot is fully booked for that date.']);
        $this->assertSame(1, ConsultationBooking::count());
    }
}
