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

    public function test_admin_experts_page_renders_expected_roles_and_actions(): void
    {
        $admin = CapstoneExpert::create([
            'email' => 'admin.experts@test.com',
            'first_name' => 'Admin',
            'last_name' => 'User',
            'is_admin' => true,
            'approved_status' => true,
        ]);

        $expert = CapstoneExpert::create([
            'email' => 'expert.one@test.com',
            'first_name' => 'Mara',
            'last_name' => 'Santos',
            'approved_status' => true,
        ]);

        $adviserRoleId = DB::table('roles')->where('role_name', 'adviser')->value('role_id');
        $consultantRoleId = DB::table('roles')->where('role_name', 'consultant')->value('role_id');

        DB::table('expert_roles')->insert([
            ['expert_id' => $expert->expert_id, 'role_id' => $adviserRoleId],
            ['expert_id' => $expert->expert_id, 'role_id' => $consultantRoleId],
        ]);

        $this->actingAs($admin, 'expert');

        $response = $this->get(route('admin.experts'));

        $response->assertOk();
        $response->assertSee('Role(s)', false);
        $response->assertSee('Action', false);

        // The listed expert and the roles they hold. 'adviser' renders as
        // "Capstone Adviser" and capstone_adviser as "Capstone Subject
        // Adviser", so the two read as distinct things.
        $response->assertSee('Mara', false);
        $response->assertSee('Capstone Adviser', false);
        $response->assertSee('Consultant', false);

        // Row actions.
        $response->assertSee('Manage Roles', false);
        $response->assertSee('Remove', false);

        // Admin accounts are not experts, so this page never lists them.
        // Asserting the count rather than the admin's email: the sidebar
        // shows the signed-in user's email in a title attribute, so it is
        // legitimately on the page even though it is not in the table.
        $response->assertSee('1 registered experts', false);

        $response->assertDontSee('Transfer Rights', false);
    }
}
