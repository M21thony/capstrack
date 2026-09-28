<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\ConsultationSchedule;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentRoster;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Models\Term;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The consultation page with no expert yet, and a booking's name. A team
 * with no expert was offered three empty expert cards, a calendar and a
 * Confirm button; a plain consultation was listed as "Consult Consultation".
 */
class ConsultationPageStatesTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: Student, 1: Team} */
    private function studentOnTeam(): array
    {
        $student = Student::create([
            'email' => 'leader@my.cspc.edu.ph', 'f_name' => 'Lead', 'l_name' => 'Er',
            'student_number' => '2026-0900', 'course' => 'BSIT', 'year_section' => '4th · A',
        ]);
        StudentRoster::create([
            'email' => $student->email, 'name' => 'Lead Er', 'section' => '4th · A',
            'program' => 'BSIT', 'school_year' => Term::currentSchoolYear(),
        ]);
        $team = Team::create(['team_name' => 'Team Consult', 'leader_id' => $student->student_id]);
        StudentSchoolYear::create(['student_id' => $student->student_id, 'team_id' => $team->team_id]);

        return [$student, $team];
    }

    public function test_a_team_with_no_expert_is_told_so_instead_of_offered_a_calendar(): void
    {
        [$student] = $this->studentOnTeam();

        $this->actingAs($student, 'web')->get(route('student.consultation'))
            ->assertOk()
            ->assertSee('No expert on your team yet')
            ->assertSee('href="'.route('student.experts').'" class="book-empty-link"', false)
            ->assertSee('<div class="book-body"  hidden >', false)
            ->assertSee('<div class="cal-notes"  hidden >', false)
            ->assertSee('.book-body[hidden],.cal-notes[hidden]{display:none!important}', false);
    }

    public function test_a_team_with_an_expert_gets_the_steps_and_a_plain_name_for_its_booking(): void
    {
        [$student, $team] = $this->studentOnTeam();
        $adviser = Role::firstOrCreate(['role_name' => 'adviser']);
        $expert = CapstoneExpert::factory()->create(['first_name' => 'Ben', 'last_name' => 'Adviser', 'approved_status' => true]);
        $expert->roles()->attach($adviser->role_id);
        DB::table('capstone_expert_application')->insert([
            'team_id' => $team->team_id, 'expert_id' => $expert->expert_id, 'status' => 'approved',
            'expert_role_id' => DB::table('expert_roles')->where('expert_id', $expert->expert_id)->value('expert_role_id'),
        ]);
        $slot = ConsultationSchedule::create([
            'expert_id' => $expert->expert_id, 'available_date' => 'Monday', 'start_time' => '09:00:00', 'end_time' => '10:00:00',
            'consultation_limit' => 1, 'slot_type' => 'consult', 'is_available' => true, 'recur' => 'weekly',
        ]);
        DB::table('consultation_booking')->insert([
            'student_id' => $student->student_id, 'team_id' => $team->team_id, 'schedule_id' => $slot->schedule_id, 'booking_status' => 'scheduled',
            'session_date' => now()->next('Monday')->toDateString(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($student, 'web')->get(route('student.consultation'))
            ->assertOk()
            ->assertDontSee('No expert on your team yet')
            ->assertSee('<div class="book-body" >', false)
            ->assertSee('<div class="appt-kind">Consultation</div>', false)
            ->assertDontSee('Consult Consultation')
            ->assertSee("slotTypeNames[booking.slot_type || 'consult'] || 'Consultation'", false);
    }

    /** The date picker's days stay round on a phone. */
    public function test_the_date_picker_days_stay_round_on_a_phone(): void
    {
        [$student] = $this->studentOnTeam();

        $this->actingAs($student, 'web')->get(route('student.consultation'))
            ->assertOk()
            ->assertSee('.sched-cal .calendar-day{border:0!important}', false)
            ->assertSee('.sched-cal .calendar-day{height:44px!important;padding:0!important;font-size:13px!important}', false)
            ->assertSee('.sched-cal .calendar-day{width:100%;max-width:40px;height:auto!important;aspect-ratio:1/1}', false)
            // Round, and still coloured: a bookable date gold, a booked one blue.
            ->assertSee('background:var(--accent-light);box-shadow:inset 0 0 0 1.5px var(--accent)}', false)
            ->assertSee('.sched-cal .calendar-day.my-booking{color:var(--blue);font-weight:700;background:var(--blue-bg);box-shadow:inset 0 0 0 2px var(--blue)}', false);
    }
}
