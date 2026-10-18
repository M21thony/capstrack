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

    /** The calendar's dates are even squares that say plainly which ones can be booked. */
    public function test_the_calendar_dates_are_short_tiles_that_fit_the_screen(): void
    {
        $css = File::get(resource_path('student-ui/Styles/experts.css'));

        $this->assertStringContainsString('.xh-cal-grid{display:grid;grid-template-columns:repeat(7,minmax(0,1fr))', $css);
        // Short tiles rather than squares, so the month and the Book button fit one screen.
        $this->assertStringContainsString('.xh-day{height:44px;', $css);
        $this->assertStringContainsString('@container (max-width: 78px){.xh-cap.is-long{display:none}.xh-cap.is-short{display:inline}}', $css);
        // A bookable date is a gold tile, a booked one blue, and a date the
        // expert does not take is crossed out with an X.
        $this->assertStringContainsString('.xh-day.is-avail{background:var(--accent-light);border:1.5px solid var(--accent)}', $css);
        $this->assertStringContainsString('.xh-day.is-booked{background:var(--xh-blue-soft);border:1.5px solid var(--xh-blue)}', $css);
        $this->assertStringContainsString('.xh-day.is-off::after{content:"";position:absolute;inset:8% 22%;pointer-events:none}', $css);
        // While a session with the expert is pending, the open dates stay
        // visible, outlined.
        $this->assertStringContainsString('.xh-day.is-locked{background:var(--bg3);border:1.5px dashed var(--accent)', $css);
        // The nearest open date is picked for the team, so there is no row of
        // shortcut dates above the calendar any more.
        $this->assertStringNotContainsString('.xh-next-btn{', $css);
    }
}
