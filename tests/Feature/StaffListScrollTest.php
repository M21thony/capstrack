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

    public function test_team_management_scrolls_its_rows_under_sticky_headings(): void
    {
        $css = File::get(public_path('css/team-management.css'));

        $this->assertStringContainsString('.content:has(> .tmx){display:flex;flex-direction:column}', $css);
        $this->assertStringContainsString('.tmx .tcard{flex:1 1 0;min-height:320px;display:flex;flex-direction:column}', $css);
        $this->assertStringContainsString('.tmx .tcard > .tscroll{flex:1;min-height:0;overflow:auto}', $css);
        $this->assertStringContainsString('position:sticky;top:0;z-index:2;', $css);

        $capsa = $this->makeCapsa(CapstoneExpert::factory()->create(['approved_status' => true, 'is_admin' => false, 'sections' => ['BSIT-3A']]));
        $this->actingAs($capsa, 'expert')->get(route('capsa.team_management'))->assertOk()
            ->assertSee('<div class="tscroll"><table>', false);
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
