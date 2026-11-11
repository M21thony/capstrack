<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The CaPSA/TSA's expert list, minus the column that told them nothing.
 *
 * Sections are the Admin's: they are the thing a CaPSA/TSA is scoped by, and they
 * are set on the Admin's own expert page. Printing them here handed the CaPSA/TSA
 * their own sections back, and printed an em-dash against every ordinary
 * expert, who never has any to begin with.
 *
 * The Admin's half of the pair keeps its Sections column - what changed is one
 * page's reading of the data, not what a CaPSA/TSA is scoped by.
 */
class CapsaExpertsTableTest extends TestCase
{
    use RefreshDatabase;

    private function capsa(array $sections = ['BSIT-4A']): CapstoneExpert
    {
        $capsa = CapstoneExpert::factory()->create([
            'approved_status' => true, 'is_admin' => false, 'sections' => $sections,
        ]);

        $capsa->roles()->attach(Role::firstOrCreate(['role_name' => CapstoneExpert::CAPSA_ROLE])->role_id);

        return $capsa;
    }

    private function admin(): CapstoneExpert
    {
        $admin = CapstoneExpert::factory()->create([
            'approved_status' => true, 'is_admin' => true, 'sections' => [],
        ]);

        $admin->roles()->attach(Role::firstOrCreate(['role_name' => CapstoneExpert::CAPSA_ROLE])->role_id);

        return $admin;
    }

    public function test_the_capsa_expert_list_has_no_sections_column(): void
    {
        // An ordinary expert has no sections, so the column could only ever
        // print an em-dash against them.
        CapstoneExpert::factory()->create([
            'email' => 'r.villanueva@cspc.edu.ph',
            'approved_status' => true,
            'is_admin' => false,
        ]);

        $html = $this->actingAs($this->capsa(), 'expert')
            ->get(route('capsa.experts'))->assertOk()->getContent();

        $this->assertStringNotContainsString('<th>Sections</th>', $html);
        $this->assertStringNotContainsString('data-label="Sections"', $html);

        // The detail panel read the same value, so it goes with the column.
        $this->assertStringNotContainsString('detailSections', $html);
    }

    /** Removing one column must not take the ones that carry something. */
    public function test_the_capsa_expert_list_keeps_its_other_columns(): void
    {
        $html = $this->actingAs($this->capsa(), 'expert')
            ->get(route('capsa.experts'))->assertOk()->getContent();

        foreach (['>Expert</th>', '>Role(s)</th>', '>Research Areas</th>', 'Action</span></th>'] as $header) {
            $this->assertStringContainsString($header, $html);
        }
    }

    /** Sections stay the Admin's, and their page still shows them. */
    public function test_the_admin_expert_list_still_shows_sections(): void
    {
        $this->capsa(['BSIT-4A', 'BSIT-4B']);

        $html = $this->actingAs($this->admin(), 'expert')
            ->get(route('admin.experts'))->assertOk()->getContent();

        $this->assertStringContainsString('>Sections handled</th>', $html);
        $this->assertStringContainsString('class="tk-sec">BSIT-4A<', $html);
        $this->assertStringContainsString('class="tk-sec">BSIT-4B<', $html);
    }
}
