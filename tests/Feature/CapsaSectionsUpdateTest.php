<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\Student;
use App\Support\SectionCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Changing which sections a CaPSA covers.
 *
 * Sections could only be set while granting the role, so changing them meant
 * removing the person as CaPSA and making them one again - which cuts off
 * their account approval, panel, verification and financial access in
 * between, for a change that was never about their role.
 */
class CapsaSectionsUpdateTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): CapstoneExpert
    {
        return CapstoneExpert::create([
            'email' => 'super@test.com',
            'first_name' => 'Super',
            'last_name' => 'Admin',
            'is_admin' => true,
            'is_staff' => true,
            'approved_status' => true,
        ]);
    }

    private function capsa(array $sections = ['BSIT-3A']): CapstoneExpert
    {
        return $this->makeCapsa(CapstoneExpert::create([
            'email' => 'sections@test.com',
            'first_name' => 'Sec',
            'last_name' => 'Tion',
            'approved_status' => true,
            'sections' => $sections,
        ]));
    }

    public function test_sections_can_be_changed_without_touching_the_role(): void
    {
        $this->actingAs($this->admin(), 'expert');
        $capsa = $this->capsa(['BSIT-3A']);

        $this->putJson(route('admin.experts.sections', $capsa), [
            'sections' => ['BSIT-4A', 'BSIT-4B'],
        ])->assertOk()->assertJson([
            'success' => true,
            'sections' => ['BSIT-4A', 'BSIT-4B'],
        ]);

        $capsa->refresh();

        $this->assertSame(['BSIT-4A', 'BSIT-4B'], $capsa->sections);
        $this->assertTrue($capsa->is_capsa, 'Changing sections took the role away.');
    }

    /** An empty set would leave a CaPSA covering nothing at all. */
    public function test_at_least_one_section_is_required(): void
    {
        $this->actingAs($this->admin(), 'expert');
        $capsa = $this->capsa(['BSIT-3A']);

        $this->putJson(route('admin.experts.sections', $capsa), ['sections' => []])
            ->assertStatus(422);

        $this->assertSame(['BSIT-3A'], $capsa->fresh()->sections);
    }

    /** Sections mean nothing on someone who does not hold the role. */
    public function test_a_non_capsa_has_no_sections_to_change(): void
    {
        $this->actingAs($this->admin(), 'expert');

        $plain = CapstoneExpert::create([
            'email' => 'plain@test.com',
            'first_name' => 'Plain',
            'last_name' => 'Expert',
            'approved_status' => true,
        ]);

        $response = $this->putJson(route('admin.experts.sections', $plain), [
            'sections' => ['BSIT-4A'],
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('not a CaPSA', $response->json('message'));
        $this->assertNull($plain->fresh()->sections);
    }

    /** The same section listed twice is one section. */
    public function test_duplicates_are_collapsed(): void
    {
        $this->actingAs($this->admin(), 'expert');
        $capsa = $this->capsa();

        $this->putJson(route('admin.experts.sections', $capsa), [
            'sections' => ['BSIT-4A', 'BSIT-4A', 'BSIT-4B'],
        ])->assertOk();

        $this->assertSame(['BSIT-4A', 'BSIT-4B'], $capsa->fresh()->sections);
    }

    /**
     * The page has to offer it. Without a button, the only way to change a
     * section is still to remove the person as CaPSA and start again.
     */
    /**
     * Assigning takes a section, not a year: each program's letters, standing
     * for its starting (3rd-year) class. A 4th-year section is offered on its
     * own, so saving a CaPSA who already holds one keeps it.
     */
    public function test_the_picker_offers_sections_without_a_year(): void
    {
        SectionCatalog::store(['BSIT-3A', 'BSIT-3B']);
        Student::create([
            'email' => 'fourth@my.cspc.edu.ph', 'f_name' => 'F', 'l_name' => 'Y',
            'course' => 'Bachelor of Science in Information Technology', 'year_section' => '4th '.json_decode('"·"').' B',
        ]);

        $html = $this->actingAs($this->admin(), 'expert')->get(route('admin.experts'))
            ->assertOk()
            ->assertViewHas('groupedSections', fn ($groups) => $groups->map->values()->map->all()->all() === ['BSIT' => ['BSIT-3A', 'BSIT-3B']])
            ->assertViewHas('laterSections', fn ($later) => $later->all() === ['BSIT-4B'])
            ->assertSee('Moved up to 4th year')
            ->getContent();

        // The letter is what shows; the whole label is what is saved.
        $this->assertMatchesRegularExpression('/data-value="BSIT-3A"\s+data-show="BSIT · A"[^>]*><input[^>]*><span>A<\/span>/', $html);
    }

    public function test_the_experts_page_offers_it_for_a_capsa(): void
    {
        $this->actingAs($this->admin(), 'expert');
        $capsa = $this->capsa(['BSIT-3A']);

        $html = $this->get(route('admin.experts'))->assertOk()->getContent();

        // The label was a tooltip on an icon for a while, because four labelled
        // buttons were most of the width of the table. The row's actions live
        // in a menu now, so it is a sentence again. What this guards either
        // way is that the control is offered at all.
        $this->assertStringContainsString('Edit sections', $html);
        $this->assertStringContainsString('editCapsaSections('.$capsa->expert_id, $html);
    }
}
