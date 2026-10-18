<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Booking on the Consultations page: the nearest open date is already
 * picked, a time opens the booking straight away, and the booking asks what
 * the team will bring. Every pop-up is the CapsTrack modal.
 */
class ConsultationBookingPageTest extends TestCase
{
    use RefreshDatabase;

    private function page(string $role = 'adviser'): string
    {
        $student = Student::create([
            'email' => 'mark@my.cspc.edu.ph', 'f_name' => 'Mark', 'l_name' => 'Reyes',
            'course' => 'BSIT', 'year_section' => '4th · A',
        ]);
        $team = Team::create(['team_name' => 'Team Lumina', 'leader_id' => $student->student_id]);
        StudentSchoolYear::create(['student_id' => $student->student_id, 'team_id' => $team->team_id]);

        $expert = CapstoneExpert::factory()->create(['first_name' => 'Ana', 'last_name' => 'Adviser', 'approved_status' => true]);
        $expert->roles()->attach(Role::firstOrCreate(['role_name' => $role])->role_id);
        DB::table('capstone_expert_application')->insert([
            'team_id' => $team->team_id, 'expert_id' => $expert->expert_id, 'status' => 'approved',
            'expert_role_id' => DB::table('expert_roles')->where('expert_id', $expert->expert_id)->value('expert_role_id'),
        ]);

        return $this->actingAs($student, 'web')->get(route('student.consultation'))->assertOk()->getContent();
    }

    public function test_the_page_knows_what_each_expert_can_be_consulted_about(): void
    {
        $this->assertStringContainsString('"topic_choices":["paper","system"],"topic_fixed":null', $this->page('adviser'));
    }

    public function test_a_grammarian_is_always_about_the_manuscript(): void
    {
        $this->assertStringContainsString('"topic_choices":["manuscript"],"topic_fixed":"manuscript"', $this->page('grammarian'));
    }

    public function test_a_time_opens_its_booking_and_the_booking_asks_what_the_team_brings(): void
    {
        $html = $this->page();

        // No second "Book this time" button to find under the times.
        $this->assertStringNotContainsString('data-xh="book-open"', $html);
        $this->assertStringContainsString('openBookConfirm();', $html);
        $this->assertStringContainsString("head('Book this time'", $html);
        $this->assertStringContainsString('data-xh="topic"', $html);
        $this->assertStringContainsString('topics: topics, note:', $html);
        $this->assertStringContainsString('maxlength="500" placeholder="What do you want to ask or show?"', $html);
    }

    public function test_the_nearest_open_date_is_picked_and_an_empty_month_says_where_the_next_is(): void
    {
        $html = $this->page();

        $this->assertStringNotContainsString('Next open dates', $html);
        $this->assertStringContainsString('if (!S.date) { S.date = firstOpen; }', $html);
        $this->assertStringContainsString("'. Next open: <b>'", $html);
    }

    public function test_every_other_week_shows_only_its_own_weeks(): void
    {
        $this->assertStringContainsString("if (s.recur === 'biweekly' && s.specific_date) {", $this->page());
    }

    public function test_every_pop_up_is_the_capstrack_modal(): void
    {
        $html = $this->page();

        $this->assertStringContainsString("d.className = 'cm xh-dlg ' + (shape === 'ask' ? 'cm-ask' : 'cm-panel'", $html);
        $this->assertStringContainsString('window.CapsModal.open(d,', $html);
        $this->assertStringNotContainsString('d.showModal()', $html);

        $css = File::get(resource_path('student-ui/Styles/experts.css'));
        $this->assertStringNotContainsString('dialog.xh-dlg{', $css);
        // A scrolling body stops short of the CapsTrack scrollbar.
        $this->assertStringContainsString('.xh-dlg-body{flex:1 1 auto;min-height:0;overflow-y:auto;overscroll-behavior:contain;scroll-padding:16px 0 24px;padding:20px 32px 20px 24px', $css);
    }
}
