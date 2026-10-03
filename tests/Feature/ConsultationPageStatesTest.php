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
use Illuminate\Support\Facades\File;
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

        $response = $this->actingAs($student, 'web')->get(route('student.consultation'))
            ->assertOk()
            ->assertSee('No consultations yet')
            ->assertSee('Find experts');

        // The Consultations tab is the one open, and it has nobody to book:
        // the page draws that explanation instead of a calendar.
        $this->assertSame('consultations', $response->viewData('tab'));
        $this->assertCount(0, $response->viewData('consultExperts'));
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

        $response = $this->actingAs($student, 'web')->get(route('student.consultation'))
            ->assertOk()
            ->assertSee("Consultation with ' + esc(b.expert_name)", false)
            ->assertDontSee('Consult Consultation');

        $this->assertCount(1, $response->viewData('consultExperts'));
        $this->assertCount(1, $response->viewData('upcomingBookings'));
    }

    /** The calendar's dates are even squares, and a small square shortens "3 open" to "3". */
    public function test_the_calendar_dates_are_squares_that_fit_a_phone(): void
    {
        $css = File::get(resource_path('student-ui/Styles/experts.css'));

        $this->assertStringContainsString('.xh-cal-grid{display:grid;grid-template-columns:repeat(7,minmax(0,1fr))', $css);
        $this->assertStringContainsString('.xh-day{aspect-ratio:1/1;', $css);
        $this->assertStringContainsString('@container (max-width: 78px){.xh-cap.is-long{display:none}.xh-cap.is-short{display:inline}}', $css);
        // Still coloured: a bookable date gold, a booked one blue.
        $this->assertStringContainsString('.xh-day.is-avail .xh-dot{background:var(--accent)}', $css);
        $this->assertStringContainsString('.xh-day.is-booked .xh-dot{background:var(--xh-blue)}', $css);
    }
}
