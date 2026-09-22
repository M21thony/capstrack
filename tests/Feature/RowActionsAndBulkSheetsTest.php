<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\Role;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Acting on one row, and acting on several.
 *
 * Two rules hold this together and both are easy to break by accident. A row's
 * menu is about that row whether or not anything is ticked, so editing one
 * student stays reachable while four are selected. And nothing on these pages
 * asks a question through the browser any more - window.prompt could not show
 * what a change would come to, could not be cancelled halfway through a pair
 * of them, and asked a CaPSA to pick a program by typing a number.
 */
class RowActionsAndBulkSheetsTest extends TestCase
{
    use RefreshDatabase;

    private function capsa(array $sections = ['BSIT-4A', 'BSIT-4B']): CapstoneExpert
    {
        $capsa = CapstoneExpert::factory()->create([
            'approved_status' => true, 'is_admin' => false, 'sections' => $sections,
        ]);

        $capsa->roles()->attach(Role::firstOrCreate(['role_name' => 'capstone_adviser'])->role_id);

        return $capsa;
    }

    private function admin(): CapstoneExpert
    {
        $admin = CapstoneExpert::factory()->create([
            'approved_status' => true, 'is_admin' => true, 'sections' => [],
        ]);

        $admin->roles()->attach(Role::firstOrCreate(['role_name' => 'capstone_adviser'])->role_id);

        return $admin;
    }

    private function student(): Student
    {
        return Student::create([
            'email' => 'jd.abella@my.cspc.edu.ph',
            'student_number' => '21-04128',
            'f_name' => 'Jerome',
            'l_name' => 'Abella',
            'course' => 'BSIT',
            'year_section' => '4th · A',
        ]);
    }

    public function test_a_student_row_carries_its_own_menu(): void
    {
        $this->student();

        $html = $this->actingAs($this->capsa(), 'expert')
            ->get(route('capsa.sections'))->assertOk()->getContent();

        $this->assertStringContainsString('rowmenu-btn', $html);
        $this->assertStringContainsString('Edit student details', $html);
        $this->assertStringContainsString('Remove from section', $html);
    }

    public function test_nothing_on_the_section_list_asks_through_the_browser(): void
    {
        $this->student();

        $html = $this->actingAs($this->capsa(), 'expert')
            ->get(route('capsa.sections'))->assertOk()->getContent();

        $this->assertStringNotContainsString('window.prompt(', $html);
        $this->assertStringNotContainsString('window.confirm(', $html);
        $this->assertStringNotContainsString('askOne', $html);

        // What replaced them.
        $this->assertStringContainsString('id="bulk-program-scrim"', $html);
        $this->assertStringContainsString('id="bulk-section-scrim"', $html);
        $this->assertStringContainsString('id="ad-scrim"', $html);
    }

    public function test_the_row_carries_the_year_and_letter_the_sheet_reads(): void
    {
        $this->student();

        $html = $this->actingAs($this->capsa(), 'expert')
            ->get(route('capsa.sections'))->assertOk()->getContent();

        // The sheet works out "BSIT-4A" from these three rather than from the
        // cells, so moving a column cannot change what it says.
        $this->assertStringContainsString('data-program="BSIT"', $html);
        $this->assertStringContainsString('data-year="4"', $html);
        $this->assertStringContainsString('data-letter="A"', $html);
    }

    public function test_the_section_sheet_offers_only_sections_the_capsa_handles(): void
    {
        $this->student();

        $html = $this->actingAs($this->capsa(['BSIT-4A', 'BSIT-4B']), 'expert')
            ->get(route('capsa.sections'))->assertOk()->getContent();

        $this->assertSame(1, preg_match('/id="bulk-section-letter".*?<\/select>/s', $html, $m));
        $this->assertStringContainsString('value="A"', $m[0]);
        $this->assertStringContainsString('value="B"', $m[0]);
        // The endpoint refuses a move into somebody else's section, so this
        // must not offer one.
        $this->assertStringNotContainsString('value="C"', $m[0]);

        $this->assertSame(1, preg_match('/id="bulk-section-year".*?<\/select>/s', $html, $y));
        $this->assertStringContainsString('4th', $y[0]);
        $this->assertStringNotContainsString('3rd', $y[0]);
    }

    public function test_the_capsa_expert_row_menu_names_both_of_its_actions(): void
    {
        $html = $this->actingAs($this->capsa(), 'expert')
            ->get(route('capsa.experts'))->assertOk()->getContent();

        $this->assertStringContainsString('rowmenu-btn', $html);
        $this->assertStringContainsString('Set roles', $html);
        $this->assertStringContainsString('Remove expert', $html);
        $this->assertStringNotContainsString('window.confirm(', $html);
    }

    public function test_the_admin_row_menu_separates_the_role_from_the_account(): void
    {
        $capsa = $this->capsa();

        $html = $this->actingAs($this->admin(), 'expert')
            ->get(route('admin.experts'))->assertOk()->getContent();

        // The two used to be a person-plus and a person-minus icon, which is
        // the shape of an add-friend button and said nothing about capstone.
        $this->assertStringContainsString('Remove CaPSA role', $html);
        $this->assertStringContainsString('Deactivate', $html);
        $this->assertStringContainsString('Edit sections', $html);
        $this->assertStringNotContainsString('window.confirm(', $html);
    }

    public function test_the_selection_bar_is_built_into_the_heading_row(): void
    {
        $this->student();

        foreach ([route('capsa.sections'), route('capsa.experts')] as $url) {
            $html = $this->actingAs($this->capsa(), 'expert')->get($url)->assertOk()->getContent();

            // The bar is one heading row and the column names are the other,
            // so the table keeps its height when a row is ticked.
            $this->assertStringContainsString("barRow.className = 'rs-bar'", $html);
            $this->assertStringContainsString('headRow.hidden = any', $html);
        }
    }
}
