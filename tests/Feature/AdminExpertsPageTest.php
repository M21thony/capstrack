<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\ExpertExpertise;
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
        // Roles sit under the name now rather than in a column of their own.
        $response->assertSee('cx-roles"', false);
        $response->assertSee('Capstone Subject Adviser', false);
        $response->assertSee('Action', false);

        // The CaPSA is listed; the plain expert is not.
        $response->assertSee('Rosa', false);
        $response->assertDontSee('Mara', false);
        // The count is a figure and a noun in their own elements now, rather
        // than one grey sentence, so they are read apart.
        $response->assertSee('class="page-count">1<', false);
        $response->assertSee('class="page-count-noun">CaPSA<', false);

        // Row actions that still belong to Admin. Setting roles is not among
        // them - see AdminHasNoRoleManagementTest for why.
        $response->assertSee('Edit sections', false);
        $response->assertSee('Remove CaPSA role', false);
        $response->assertSee('Deactivate', false);
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

    /**
     * A CaPSA who advises nobody has no team slots, and is not "Full".
     *
     * Team slots belong to the roles that take teams. Running the subject is
     * not one of them, so the row used to read "0/0 - 0 left" with a Full
     * badge beside it: an overload badge on somebody carrying nothing.
     */
    public function test_a_capsa_with_no_team_taking_role_shows_no_slots(): void
    {
        $admin = CapstoneExpert::create([
            'email' => 'admin.slots@test.com',
            'first_name' => 'Admin',
            'last_name' => 'Slots',
            'is_admin' => true,
            'approved_status' => true,
        ]);

        $this->makeCapsa(CapstoneExpert::create([
            'email' => 'plain.capsa@test.com',
            'first_name' => 'Plain',
            'last_name' => 'Capsa',
            'approved_status' => true,
        ]));

        $this->actingAs($admin, 'expert');

        $response = $this->get(route('admin.experts'));

        $response->assertOk();
        $response->assertSee('Team Slot', false);
        $response->assertDontSee('Capacity</th>', false);

        // No slot reading, and no overload badge for someone with no slots.
        $response->assertDontSee('0/0', false);
        $response->assertDontSee('badge-amber">Full', false);
    }

    /**
     * Team slots are not shown here even for a CaPSA who also advises.
     *
     * How many teams someone takes is the expert's own answer, and it is
     * already on Expert Management. On this page the column took the room the
     * sections a CaPSA handles - the thing Admin actually sets - needed.
     */
    public function test_team_slots_are_left_to_expert_management(): void
    {
        $admin = CapstoneExpert::create([
            'email' => 'admin.slots2@test.com',
            'first_name' => 'Admin',
            'last_name' => 'Slots',
            'is_admin' => true,
            'approved_status' => true,
        ]);

        $both = $this->makeCapsa(CapstoneExpert::create([
            'email' => 'both.roles@test.com',
            'first_name' => 'Both',
            'last_name' => 'Roles',
            'approved_status' => true,
            'max_teams' => 3,
        ]));

        DB::table('expert_roles')->insert([
            'expert_id' => $both->expert_id,
            'role_id' => DB::table('roles')->where('role_name', 'adviser')->value('role_id'),
        ]);

        $this->actingAs($admin, 'expert');

        $this->get(route('admin.experts'))
            ->assertOk()
            ->assertDontSee('0/3', false)
            ->assertDontSee('expert sets</span></th>', false);
    }

    /**
     * The compact table: one line per person, status as a dot, skills as
     * chips.
     *
     * The filter used to find the status by counting to the sixth column,
     * which is a thing that breaks the moment a column moves - and this
     * moves one. It reads the row's own attributes now, so the assertions
     * here are about those as much as about the markup.
     */
    public function test_the_table_is_compact_and_filterable_by_row_attributes(): void
    {
        $admin = CapstoneExpert::create([
            'email' => 'admin.compact@test.com',
            'first_name' => 'Admin',
            'last_name' => 'Compact',
            'is_admin' => true,
            'approved_status' => true,
        ]);

        $capsa = $this->makeCapsa(CapstoneExpert::create([
            'email' => 'chips@test.com',
            'first_name' => 'Many',
            'last_name' => 'Skills',
            'approved_status' => true,
            'sections' => ['BSIT-4B'],
        ]));

        foreach (['Research Methodology', 'Data Analytics', 'Machine Learning', 'Web Development'] as $skill) {
            ExpertExpertise::create(['expert_id' => $capsa->expert_id, 'expertise_field' => $skill]);
        }

        // Signed in before, so the row shows them rather than an invite.
        $capsa->forceFill(['first_login_at' => now()->subDay()])->save();

        $this->actingAs($admin, 'expert');

        $response = $this->get(route('admin.experts'));

        $response->assertOk();

        // Each section a CaPSA handles by its own name; status is a dot on
        // the avatar rather than a column.
        $response->assertSee('>Sections handled</th>', false);
        $response->assertSee('class="tk-sec">BSIT-4B<', false);
        $response->assertDontSee('<th>Status</th>', false);
        $response->assertSee('tk-pres tk-pres-', false);

        // No photo, so their initials on a tint rather than a grey silhouette.
        $response->assertSee('tk-av tk-tone-', false);

        // Two skills as chips, the rest behind a counter that opens the panel.
        $response->assertSee('cx-chip">Research Methodology', false);
        $response->assertSee('class="tk-more"', false);
        $response->assertSee('+2', false);

        // What the filter reads, so it no longer counts columns. Roles are
        // not among them any more: Admin's copy of this page lists CaPSAs and
        // nothing else, so a filter over roles could only ever return nothing.
        $response->assertSee('data-status="', false);
        $response->assertSee('data-search="', false);
        $response->assertDontSee('data-roles="', false);
    }
}
