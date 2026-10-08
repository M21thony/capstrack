<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * My Profile is split into tabs - Details, Research areas, Submission folders
 * and Notifications - so nothing is a long scroll away. ?tab= picks one, and
 * a save comes back to the tab it was made from.
 */
class CapsaProfileTabsTest extends TestCase
{
    use RefreshDatabase;

    private function capsa(): CapstoneExpert
    {
        $this->seed(RoleSeeder::class);

        return $this->makeCapsa(CapstoneExpert::factory()->create(['approved_status' => true, 'sections' => ['BSIT-3A']]));
    }

    public function test_the_profile_opens_on_details_with_the_other_parts_in_tabs(): void
    {
        $capsa = $this->capsa();

        $response = $this->actingAs($capsa, 'expert')->get(route('capsa.profile'))->assertOk()
            ->assertSee('data-pf-tab="details"', false)
            ->assertSee('data-pf-tab="research"', false)
            ->assertSee('data-pf-tab="notifications"', false)
            ->assertSee('Save changes');

        $html = $response->getContent();
        $this->assertMatchesRegularExpression('/data-pf-panel="details" role="tabpanel"\s*>/', $html, 'details is open');
        $this->assertMatchesRegularExpression('/data-pf-panel="notifications" role="tabpanel"\s+hidden/', $html, 'notifications waits behind its tab');
    }

    public function test_the_address_picks_the_tab(): void
    {
        $html = $this->actingAs($this->capsa(), 'expert')->get(route('capsa.profile', ['tab' => 'notifications']))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/data-pf-panel="notifications" role="tabpanel"\s*>/', $html);
        $this->assertMatchesRegularExpression('/data-pf-panel="details" role="tabpanel"\s+hidden/', $html);
        $this->assertMatchesRegularExpression('/class="pf-savebar" data-pf-save\s+hidden/', $html, 'nothing to save on this tab');
    }

    public function test_an_unknown_tab_falls_back_to_details(): void
    {
        $html = $this->actingAs($this->capsa(), 'expert')->get(route('capsa.profile', ['tab' => 'nope']))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/data-pf-panel="details" role="tabpanel"\s*>/', $html);
    }

    /** A CaPSA is the Subject Adviser, so their folders get a tab of their own. */
    public function test_a_subject_adviser_has_a_folders_tab(): void
    {
        $this->actingAs($this->capsa(), 'expert')->get(route('capsa.profile', ['tab' => 'folders']))->assertOk()
            ->assertSee('data-pf-tab="folders"', false)
            ->assertSee('id="folderListContainer"', false);
    }

    public function test_a_save_comes_back_to_the_tab_it_was_made_from(): void
    {
        $capsa = $this->capsa();

        $this->actingAs($capsa, 'expert')
            ->put(route('capsa.profile.update'), ['first_name' => 'Rosa', 'last_name' => 'Cruz', 'tab' => 'research'])
            ->assertRedirect(route('capsa.profile', ['tab' => 'research']));

        $this->actingAs($capsa, 'expert')
            ->put(route('capsa.profile.update'), ['first_name' => 'Rosa', 'last_name' => 'Cruz', 'tab' => 'elsewhere'])
            ->assertRedirect(route('capsa.profile'));
    }

    /** Sections and the school year are the Admin's; a CaPSA's own save neither changes nor wipes them. */
    public function test_a_capsa_cannot_change_their_own_sections_or_school_year(): void
    {
        $capsa = $this->capsa();
        $capsa->forceFill(['school_year' => '2026-2027'])->save();

        $this->actingAs($capsa, 'expert')->put(route('capsa.profile.update'), [
            'first_name' => 'Rosa', 'last_name' => 'Cruz',
            'sections' => ['BSCS-1A'], 'school_year' => '1999-2000',
        ])->assertRedirect();

        $capsa = $capsa->fresh();
        $this->assertSame('Rosa', $capsa->first_name);
        $this->assertSame(['BSIT-3A'], $capsa->sections);
        $this->assertSame('2026-2027', $capsa->school_year);

        // A save with no sections at all used to clear them.
        $this->actingAs($capsa, 'expert')->put(route('capsa.profile.update'), ['first_name' => 'Rosa', 'last_name' => 'Reyes'])->assertRedirect();
        $this->assertSame(['BSIT-3A'], $capsa->fresh()->sections);

        $this->actingAs($capsa, 'expert')->get(route('capsa.profile'))->assertOk()
            ->assertDontSee('name="sections[]"', false)
            ->assertDontSee('name="school_year"', false)
            ->assertSee('Set by the administrator.');
    }
}
