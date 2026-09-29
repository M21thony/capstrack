<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\ConsultationBooking;
use App\Models\ConsultationSchedule;
use App\Models\Student;
use App\Models\StudentRoster;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Models\Term;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The student page never lists a day or time that has ended, but the booking
 * request itself has to refuse one too.
 */
class StudentCannotBookEndedSlotTest extends TestCase
{
    use RefreshDatabase;

    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->student = Student::create([
            'email' => 'leader@my.cspc.edu.ph', 'f_name' => 'Mark', 'l_name' => 'B',
            'course' => 'BSIT', 'year_section' => '4th · A',
        ]);
        StudentRoster::create([
            'email' => $this->student->email, 'name' => 'Mark B', 'section' => '4th · A',
            'program' => 'BSIT', 'school_year' => Term::currentSchoolYear(),
        ]);
        $team = Team::create(['team_name' => 'T', 'leader_id' => $this->student->student_id]);
        StudentSchoolYear::create([
            'student_id' => $this->student->student_id, 'team_id' => $team->team_id,
        ]);
    }

    private function onceSlot(string $date): ConsultationSchedule
    {
        $expert = CapstoneExpert::factory()->create();

        return ConsultationSchedule::create([
            'expert_id' => $expert->expert_id,
            'available_date' => now()->parse($date)->format('l'),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'consultation_limit' => 1,
            'slot_type' => 'consult',
            'recur' => 'once',
            'specific_date' => $date,
            'is_available' => true,
        ]);
    }

    private function book(ConsultationSchedule $slot)
    {
        return $this->actingAs($this->student, 'web')->postJson('/student/consultation/book', [
            'schedule_id' => $slot->schedule_id,
            'session_date' => $slot->specific_date,
        ]);
    }

    public function test_a_slot_on_a_day_that_has_ended_cannot_be_booked(): void
    {
        $this->book($this->onceSlot(now()->subDay()->toDateString()))->assertStatus(422);

        $this->assertSame(0, ConsultationBooking::count());
    }

    public function test_a_time_earlier_today_cannot_be_booked(): void
    {
        $this->travelTo(now()->setTime(15, 0));

        $this->book($this->onceSlot(now()->toDateString()))->assertStatus(422);

        $this->assertSame(0, ConsultationBooking::count());
    }

    public function test_a_time_later_today_can_be_booked(): void
    {
        $this->travelTo(now()->setTime(8, 0));

        $this->book($this->onceSlot(now()->toDateString()))->assertSuccessful();

        $this->assertSame(1, ConsultationBooking::count());
    }
}
