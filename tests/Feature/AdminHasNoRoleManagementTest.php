<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * An Admin names CaPSAs. Who advises, consults or proofreads for a team is
 * the CaPSA's call, made from their own copy of Expert Management.
 *
 * The page was offering it anyway - a row button, a bulk action and a filter -
 * and none of it could work: Admin's copy lists CaPSAs and nothing else, so
 * there was never an expert on it whose roles were in question, and the save
 * went to a CaPSA-guarded address that answered 403. It is gone at both ends,
 * which is what these pin down.
 */
class AdminHasNoRoleManagementTest extends TestCase
{
    use RefreshDatabase;

    /**
     * An Admin and nothing else - no capstone_adviser role, which is what
     * is_capsa is read off. The seeded account on a real install usually
     * holds both, and that is exactly the case where "an Admin can do it"
     * hides the fact that they can only do it as a CaPSA.
     */
    private function admin(): CapstoneExpert
    {
        return CapstoneExpert::factory()->create([
            'approved_status' => true, 'is_admin' => true, 'sections' => [],
        ]);
    }

    public function test_the_page_offers_no_way_to_set_roles(): void
    {
        $this->makeCapsa(CapstoneExpert::factory()->create(['approved_status' => true]));

        $html = $this->actingAs($this->admin(), 'expert')
            ->get(route('admin.experts'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('Manage roles', $html);
        $this->assertStringNotContainsString('openRoles(', $html);
        $this->assertStringNotContainsString('bulkRoles', $html);
        $this->assertStringNotContainsString('experts-role-filter', $html);

        // The roles are still shown, under each name: a CaPSA who also
        // advises says so there, and it is read-only either way.
        $this->assertStringContainsString('cx-roles"', $html);
    }

    public function test_there_is_no_admin_address_for_it(): void
    {
        $this->assertNull(Route::getRoutes()->getByName('admin.experts.update-roles'));
        $this->assertNotNull(Route::getRoutes()->getByName('capsa.experts.update-roles'));
    }

    public function test_an_admin_who_is_not_a_capsa_is_turned_away_from_the_endpoint(): void
    {
        $target = CapstoneExpert::factory()->create(['approved_status' => true, 'is_admin' => false]);

        $this->actingAs($this->admin(), 'expert')
            ->putJson(route('capsa.experts.update-roles', $target), ['role_ids' => []])
            ->assertStatus(403);
    }
}
