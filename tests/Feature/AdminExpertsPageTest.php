<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminExpertsPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Real migrations rather than a hand-built subset: this page renders
        // the full admin sidebar, which queries archived_documents,
        // financial_records, activity_log and more. Listing those by hand
        // meant the test 500'd on whichever one was missing next.
        DB::table('roles')->insert([
            ['role_name' => 'adviser', 'description' => 'Adviser'],
            ['role_name' => 'consultant', 'description' => 'Consultant'],
            ['role_name' => 'grammarian', 'description' => 'Grammarian'],
            ['role_name' => 'capstone_adviser', 'description' => 'Capstone Subject Adviser'],
        ]);
    }

    /**
     * Admin's copy of this page lists CaPSAs and nothing else.
     *
     * Onboarding the faculty and giving them their roles is the CaPSA's job,
     * from their own copy of this page. Listing every expert here offered the
     * same job from two places with different powers behind it.
     */
    public function test_the_admin_page_lists_capsas_and_not_other_experts(): void
    {
        $admin = CapstoneExpert::create([
            'email' => 'admin.experts@test.com',
            'first_name' => 'Admin',
            'last_name' => 'User',
            'is_admin' => true,
            'approved_status' => true,
        ]);

        $capsa = $this->makeCapsa(CapstoneExpert::create([
            'email' => 'capsa.listed@test.com',
            'first_name' => 'Rosa',
            'last_name' => 'Villanueva',
            'approved_status' => true,
        ]));

        // An adviser and consultant who is not a CaPSA - the CaPSA's own copy
        // of this page is where they are managed.
        $expert = CapstoneExpert::create([
            'email' => 'expert.one@test.com',
            'first_name' => 'Mara',
            'last_name' => 'Santos',
            'approved_status' => true,
        ]);

        DB::table('expert_roles')->insert([
            ['expert_id' => $expert->expert_id, 'role_id' => DB::table('roles')->where('role_name', 'adviser')->value('role_id')],
            ['expert_id' => $expert->expert_id, 'role_id' => DB::table('roles')->where('role_name', 'consultant')->value('role_id')],
        ]);

        $this->actingAs($admin, 'expert');

        $response = $this->get(route('admin.experts'));

        $response->assertOk();
        $response->assertSee('CaPSA Management', false);
        $response->assertSee('Role(s)', false);
        $response->assertSee('Action', false);

        // The CaPSA is listed; the plain expert is not.
        $response->assertSee('Rosa', false);
        $response->assertDontSee('Mara', false);
        $response->assertSee('1 CaPSA', false);

        // Row actions that still belong to Admin.
        $response->assertSee('Manage Roles', false);
        $response->assertSee('Remove', false);
        $response->assertDontSee('Transfer Rights', false);
    }

    /** Naming a CaPSA is the only thing this page starts. */
    public function test_the_admin_page_offers_only_adding_a_capsa(): void
    {
        $admin = CapstoneExpert::create([
            'email' => 'admin.only@test.com',
            'first_name' => 'Admin',
            'last_name' => 'Only',
            'is_admin' => true,
            'approved_status' => true,
        ]);

        $this->actingAs($admin, 'expert');

        $response = $this->get(route('admin.experts'));

        $response->assertOk();
        $response->assertSee('Add CaPSA', false);

        // Uploading the faculty list and adding one expert by hand are the
        // CaPSA's, so they are not offered from here as well.
        $response->assertDontSee('openExpertRosterModal()', false);
        $response->assertDontSee('+ Add Expert', false);
    }
}
