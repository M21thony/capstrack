<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * A long staff list scrolls inside itself under headings that stay, rather
 * than the whole page scrolling the headings, filters and counts away. The
 * admin's Students and Experts lists already did; Team Management, the
 * Activity Log and Document Review now do too.
 */
class StaffListScrollTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Team Management is laid out as the Student Section List: a line of
     * counts, a line of search, filters and ways in, and the list filling
     * the rest of the screen with the developers' credit at its end.
     */
    public function test_team_management_is_laid_out_as_the_section_list(): void
    {
        $css = File::get(public_path('css/team-management.css'));

        $this->assertStringContainsString('.content:has(> .tm-list){display:flex;flex-direction:column;min-height:0;overflow:hidden}', $css);
        $this->assertStringContainsString('.tm-list .tcard > .tscroll{flex:1;min-height:0;overflow:auto}', $css);
        $this->assertStringContainsString('position:sticky;top:0;z-index:2;', $css);
        $this->assertStringContainsString(
            "first('.list-card, .tm-list .tscroll, .dx-table-wrap, .fx-scroll', scrolls)",
            File::get(resource_path('views/partials/mobile-tabbar.blade.php'))
        );

        $capsa = $this->makeCapsa(CapstoneExpert::factory()->create(['approved_status' => true, 'is_admin' => false, 'sections' => ['BSIT-3A']]));
        $this->actingAs($capsa, 'expert')->get(route('capsa.team_management'))->assertOk()
            ->assertSee('<div class="tmx tm-list">', false)
            ->assertSeeInOrder(['class="page-count"', 'class="page-count-noun"', 'in progress', 'for review', 'completed'], false)
            ->assertSeeInOrder(['id="tm-q"', 'data-v="progress"', 'Milestones', 'Leave Requests', 'id="setBtn"', 'id="tm-teams"'], false)
            ->assertDontSee('class="stats"', false)
            ->assertDontSee('class="phead"', false);
    }

    public function test_the_activity_log_is_a_scrolling_list_with_its_page_controls_outside(): void
    {
        $admin = CapstoneExpert::factory()->create(['approved_status' => true, 'is_admin' => true]);

        $this->actingAs($admin, 'expert')->get(route('admin.activitylog'))->assertOk()
            ->assertSee('<div class="content list-page">', false)
            ->assertSee('<div class="card list-card">', false)
            ->assertSeeInOrder(['<div class="card list-card">', '</table>', '<div class="list-foot">'], false);
    }

    public function test_document_review_scrolls_its_documents_under_sticky_headings(): void
    {
        $expert = CapstoneExpert::factory()->create(['approved_status' => true, 'is_admin' => false]);

        $this->actingAs($expert, 'expert')->get(route('instructor.docreview'))->assertOk()
            ->assertSee('<div class="content dr-page">', false)
            ->assertSee('<div class="card dr-card" style="padding:0">', false)
            ->assertSee('.content.dr-page > .dr-card { flex: 1 1 0; min-height: 260px; overflow: auto; }', false)
            ->assertSee('.content.dr-page { display: flex; flex-direction: column; }', false);
    }
}
