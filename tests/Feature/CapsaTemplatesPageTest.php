<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Templates & Lessons: the CaPSA/TSA's side of the students' Resources page.
 * One list with search and filters, rows that open in place, and an upload
 * panel that picks a category from chips.
 */
class CapsaTemplatesPageTest extends TestCase
{
    use RefreshDatabase;

    private function capsa(): CapstoneExpert
    {
        $expert = CapstoneExpert::factory()->create(['approved_status' => true, 'sections' => ['BSIT-4A', 'BSIT-4B']]);
        $expert->roles()->attach(Role::firstOrCreate(['role_name' => 'capstone_adviser'])->role_id);

        return $expert;
    }

    public function test_the_list_has_search_filters_sort_and_one_upload_button(): void
    {
        $this->actingAs($this->capsa(), 'expert')->get(route('capsa.resources'))
            ->assertOk()
            ->assertSee('What you post here shows up on your students’ Resources page.')
            ->assertSee('id="search-input"', false)
            ->assertSee('id="category-filter"', false)
            ->assertSee('id="section-filter"', false)
            ->assertSee('id="sort-filter"', false)
            ->assertSee('<option value="az">A to Z</option>', false)
            ->assertSee('id="open-upload-btn"', false)
            ->assertSee('Upload files');
    }

    public function test_the_upload_panel_picks_a_category_from_chips_and_sections_from_boxes(): void
    {
        $this->actingAs($this->capsa(), 'expert')->get(route('capsa.resources'))
            ->assertOk()
            ->assertSee('<input type="hidden" id="res-category" value="">', false)
            ->assertSee('role="radiogroup" aria-label="Category" id="res-category-chips"', false)
            ->assertSee('data-cat="ch1-3">Chapters 1–3</button>', false)
            ->assertSee('data-cat="lesson">Lessons</button>', false)
            ->assertSeeInOrder(['id="res-sections"', 'BSIT-4A', 'BSIT-4B'], false)
            ->assertSee('<summary>More options</summary>', false)
            ->assertSee('Post to Resources');
    }
}
