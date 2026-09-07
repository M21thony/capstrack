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
 * One live booking per expert, not one per team.
 *
 * A team due to see their adviser on Thursday should still be able to book
 * their consultant for Friday - different people, about different things.
 * The rule is that they cannot book the same person twice while the first
 * session is still ahead of them.
 */
class OneBookingPerExpertTest extends TestCase
{
    use RefreshDatabase;

    private Student $student;
    private Team $team;

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
        $this->team = Team::create(['team_name' => 'T', 'leader_id' => $this->student->student_id]);
        StudentSchoolYear::create([
            'student_id' => $this->student->student_id, 'team_id' => $this->team->team_id,
        ]);
    }

    private function slotFor(CapstoneExpert $expert, string $start = '09:00'): ConsultationSchedule
    {
        return ConsultationSchedule::create([
            'expert_id' => $expert->expert_id,
            'available_date' => 'Monday',
            'start_time' => $start,
            'end_time' => '10:00',
            'consultation_limit' => 2,
            'slot_type' => 'consult',
            'recur' => 'weekly',
            'is_available' => true,
        ]);
    }

    private function book(ConsultationSchedule $slot, string $date)
    {
        return $this->actingAs($this->student, 'web')->postJson('/student/consultation/book', [
            'schedule_id' => $slot->schedule_id,
            'session_date' => $date,
        ]);
    }

    public function test_a_second_booking_with_the_same_expert_is_refused(): void
    {
        $adviser = CapstoneExpert::factory()->create(['first_name' => 'Grom', 'last_name' => 'Hellscream']);

        $this->book($this->slotFor($adviser), '2026-09-14')->assertSuccessful();

        $this->book($this->slotFor($adviser, '11:00'), '2026-09-21')
            ->assertStatus(422)
            ->assertJson(fn ($j) => $j->where('message', fn ($m) => str_contains($m, 'Hellscream'))->etc());

        $this->assertSame(1, ConsultationBooking::count());
    }

    /** The one the old rule broke: a different expert is still bookable. */
    public function test_another_expert_can_still_be_booked(): void
    {
        $adviser = CapstoneExpert::factory()->create(['first_name' => 'Grom', 'last_name' => 'Hellscream']);
        $consultant = CapstoneExpert::factory()->create(['first_name' => 'Mark', 'last_name' => 'Balilla']);

        $this->book($this->slotFor($adviser), '2026-09-14')->assertSuccessful();
        $this->book($this->slotFor($consultant), '2026-09-14')->assertSuccessful();

        $this->assertSame(2, ConsultationBooking::count());
    }

    /** Once the first session is cancelled, that expert is free again. */
    public function test_cancelling_frees_that_expert(): void
    {
        $adviser = CapstoneExpert::factory()->create(['first_name' => 'Grom', 'last_name' => 'Hellscream']);

        $this->book($this->slotFor($adviser), '2026-09-14')->assertSuccessful();
        ConsultationBooking::query()->update(['booking_status' => 'cancelled']);

        $this->book($this->slotFor($adviser, '11:00'), '2026-09-21')->assertSuccessful();

        $this->assertSame(1, ConsultationBooking::where('booking_status', 'scheduled')->count());
    }
}
