<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The four people lists as one kind of page (public/css/table-kit.css).
 *
 * CaPSA/TSA Management, Student Accounts, Expert Management and Student Section
 * List each say how many rows every filter holds before it is picked, keep
 * their filters inside the card with the table, and keep destructive actions
 * in the row's menu rather than as a red button on every row.
 */
class TableListRedesignTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): CapstoneExpert
    {
        return CapstoneExpert::factory()->create([
            'approved_status' => true, 'is_admin' => true, 'sections' => [],
        ]);
    }

    private function capsa(array $sections = ['BSIT-4A']): CapstoneExpert
    {
        $capsa = CapstoneExpert::factory()->create([
            'approved_status' => true, 'is_admin' => false, 'sections' => $sections,
        ]);

        $capsa->roles()->attach(Role::firstOrCreate(['role_name' => CapstoneExpert::CAPSA_ROLE])->role_id);

        return $capsa;
    }

    private function student(string $email, string $yearSection = '4th · A', string $course = 'BSIT'): Student
    {
        return Student::create([
            'email' => $email, 'f_name' => 'Angelo', 'l_name' => str($email)->before('@')->title()->toString(),
            'course' => $course, 'year_section' => $yearSection,
        ]);
    }

    private function onTeam(Student $student, string $teamName = 'Team Alpha'): void
    {
        $team = Team::firstOrCreate(['team_name' => $teamName], ['project_title' => 'A Capstone Project', 'tags' => []]);
        StudentSchoolYear::create(['student_id' => $student->student_id, 'team_id' => $team->team_id]);
    }

    // ── shared ──────────────────────────────────────────────────────────

    public function test_every_list_loads_the_table_kit_and_marks_its_page(): void
    {
        $admin = $this->admin();
        $capsa = $this->capsa();

        foreach ([[$admin, 'admin.experts'], [$admin, 'admin.students'], [$capsa, 'capsa.experts'], [$capsa, 'capsa.sections']] as [$viewer, $route]) {
            $this->actingAs($viewer, 'expert')
                ->get(route($route))
                ->assertOk()
                ->assertSee('css/table-kit.css', false)
                ->assertSee('class="content list-page tk"', false)
                ->assertSee('class="tk-toolbar"', false);
        }
    }

    // ── Student Accounts ────────────────────────────────────────────────

    public function test_student_accounts_tabs_count_and_filter_by_team(): void
    {
        $this->onTeam($this->student('ontheteam@my.cspc.edu.ph'));
        $this->student('alone@my.cspc.edu.ph');
        $this->student('alsoalone@my.cspc.edu.ph');

        $this->actingAs($this->admin(), 'expert');

        $this->get(route('admin.students'))
            ->assertOk()
            ->assertSee('All <span class="n">3</span>', false)
            ->assertSee('In a team <span class="n">1</span>', false)
            ->assertSee('No team <span class="n">2</span>', false);

        $this->get(route('admin.students', ['team' => 'with']))
            ->assertOk()
            ->assertSee('ontheteam@my.cspc.edu.ph')
            ->assertDontSee('alone@my.cspc.edu.ph');

        $this->get(route('admin.students', ['team' => 'without']))
            ->assertOk()
            ->assertDontSee('ontheteam@my.cspc.edu.ph')
            ->assertSee('alone@my.cspc.edu.ph')
            ->assertSee('alsoalone@my.cspc.edu.ph');
    }

    public function test_student_accounts_tab_counts_follow_the_other_filters(): void
    {
        $this->onTeam($this->student('itteam@my.cspc.edu.ph', '4th · A', 'BSIT'));
        $this->student('isalone@my.cspc.edu.ph', '4th · A', 'BSIS');

        $this->actingAs($this->admin(), 'expert')
            ->get(route('admin.students', ['program' => 'BSIS']))
            ->assertOk()
            ->assertSee('All <span class="n">1</span>', false)
            ->assertSee('In a team <span class="n">0</span>', false);
    }

    public function test_student_accounts_lists_everyone_on_one_page(): void
    {
        foreach (range(1, 35) as $n) {
            $this->student('student'.$n.'@my.cspc.edu.ph');
        }

        $this->actingAs($this->admin(), 'expert')
            ->get(route('admin.students'))
            ->assertOk()
            ->assertSee('student35@my.cspc.edu.ph')
            ->assertSee('Showing <strong>35</strong> students', false)
            ->assertDontSee('Rows per page');
    }

    public function test_student_accounts_keeps_remove_in_the_row_menu(): void
    {
        $this->student('menu@my.cspc.edu.ph');

        $this->actingAs($this->admin(), 'expert')
            ->get(route('admin.students'))
            ->assertOk()
            ->assertDontSee('btn-remove-pill', false)
            ->assertSee('rowmenu-item danger std-remove', false)
            ->assertSee('Copy email');
    }

    // ── Student Section List ────────────────────────────────────────────

    public function test_section_pills_count_each_section_while_another_is_picked(): void
    {
        $this->student('a1@my.cspc.edu.ph', '4th · A');
        $this->student('a2@my.cspc.edu.ph', '4th · A');
        $this->student('b1@my.cspc.edu.ph', '4th · B');

        $html = $this->actingAs($this->capsa(['BSIT-4A', 'BSIT-4B']), 'expert')
            ->get(route('capsa.sections', ['section' => 'BSIT-4B']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('All sections <span class="n">3</span>', $html);
        $this->assertStringContainsString('BSIT-4A <span class="n">2</span>', $html);
        $this->assertMatchesRegularExpression('/section=BSIT-4B"\s+aria-current="page"\s*>BSIT-4B <span class="n">1<\/span>/', $html);
        // Only the picked section's students are listed.
        $this->assertStringContainsString('b1@my.cspc.edu.ph', $html);
        $this->assertStringNotContainsString('a1@my.cspc.edu.ph', $html);
    }

    public function test_section_list_status_tabs_count_and_rows_carry_what_they_filter_on(): void
    {
        $signedIn = $this->student('signed@my.cspc.edu.ph');
        $signedIn->forceFill(['last_seen_at' => now()])->save();
        $this->onTeam($signedIn);
        $this->student('waiting@my.cspc.edu.ph');

        $html = $this->actingAs($this->capsa(), 'expert')
            ->get(route('capsa.sections'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('All <span class="n">2</span>', $html);
        $this->assertStringContainsString('Without a team <span class="n">1</span>', $html);
        $this->assertStringContainsString('Not signed in <span class="n">1</span>', $html);
        $this->assertStringContainsString('Enrolled <span class="n">1</span>', $html);
        $this->assertStringContainsString('data-signed="1"', $html);
        $this->assertStringContainsString('data-signed="0"', $html);
    }

    public function test_section_list_bulk_bar_transfers_a_section(): void
    {
        $this->student('bulk@my.cspc.edu.ph');

        $html = $this->actingAs($this->capsa(), 'expert')
            ->get(route('capsa.sections'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Transfer section', $html);
        $this->assertStringNotContainsString('Set year &amp; section', $html);
    }

    // ── Expert Management ───────────────────────────────────────────────

    public function test_expert_management_tabs_count_each_role_and_pin_you(): void
    {
        $capsa = $this->capsa();
        $capsa->forceFill(['first_login_at' => now()])->save();

        $adviser = CapstoneExpert::factory()->create(['approved_status' => true]);
        $adviser->roles()->attach(Role::firstOrCreate(['role_name' => 'adviser'])->role_id);
        $grammarian = CapstoneExpert::factory()->create(['approved_status' => true]);
        $grammarian->roles()->attach(Role::firstOrCreate(['role_name' => 'grammarian'])->role_id);

        $html = $this->actingAs($capsa, 'expert')
            ->get(route('capsa.experts'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('All <span class="n">3</span>', $html);
        $this->assertStringContainsString('Adviser <span class="n">1</span>', $html);
        $this->assertStringContainsString('Grammarian <span class="n">1</span>', $html);
        $this->assertStringContainsString('Consultant <span class="n">0</span>', $html);
        $this->assertStringContainsString('tk-p-you">YOU<', $html);
        // The two who have never signed in are counted, and can be shown.
        $this->assertStringContainsString('2 experts haven&#039;t signed in yet.', $html);
        $this->assertStringContainsString('id="tkShowAwaiting"', $html);
    }

    // ── CaPSA/TSA Management ────────────────────────────────────────────────

    public function test_capsa_management_groups_uncovered_sections_and_drops_slots(): void
    {
        $this->capsa(['BSIT-3A']);
        $this->student('nobody@my.cspc.edu.ph', '3rd · B');

        $this->actingAs($this->admin(), 'expert')
            ->get(route('admin.experts'))
            ->assertOk()
            ->assertSee('class="tk-cov-row"', false)
            ->assertSee('title="BSIT-3B">3B<', false)
            ->assertSee('Assign sections')
            ->assertSee('>Sections handled</th>', false)
            ->assertDontSee('expert sets</span></th>', false);
    }
}
