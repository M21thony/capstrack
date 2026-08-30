<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\CapstoneExpert;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

// Role selection ("specialization") used to happen here, at request time -
// removed per the CAPSA/Admin split: CAPSA assigns the role later, when
// approving the request (see AdminApprovalControllerTest /
// AdminApprovalController::approve()). These tests now lock in that a
// fresh request never gets a role, is_admin, or is_capsa, no matter what's
// submitted.
class ExpertAccountRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['capstone_adviser', 'adviser', 'consultant', 'grammarian'] as $roleName) {
            DB::table('roles')->insert([
                'role_name' => $roleName,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Grom',
            'last_name' => 'Hellscream',
            'email' => 'grom@gmail.com',
            'expertise' => ['PHP'],
        ], $overrides);
    }

    public function test_a_fresh_request_gets_no_role_and_no_admin_or_capsa_flag(): void
    {
        $response = $this->postJson(route('account.request.store'), $this->payload());

        $response->assertOk()->assertJson(['success' => true]);

        $expertId = DB::table('capstone_experts')->where('email', 'grom@gmail.com')->value('expert_id');
        $this->assertNotNull($expertId);

        $roles = DB::table('expert_roles')->where('expert_id', $expertId)->count();
        $this->assertSame(0, $roles);

        $expert = DB::table('capstone_experts')->where('expert_id', $expertId)->first();
        $this->assertFalse((bool) $expert->is_admin);
        $this->assertFalse((bool) $expert->approved_status);

        // Being a CaPSA is holding the capstone_adviser role - there is no
        // is_capsa column any more.
        $this->assertFalse(
            CapstoneExpert::find($expertId)->is_capsa,
            'A fresh account request must not come out as a CaPSA.'
        );
    }

    // A leftover "specialization" field in the request body (e.g. an old
    // cached client) must be silently ignored, not error out or sneak a
    // role in - role assignment only ever happens through
    // AdminApprovalController::approve() now.
    public function test_a_submitted_specialization_field_is_ignored(): void
    {
        $response = $this->postJson(route('account.request.store'), $this->payload([
            'specialization' => ['capstone_adviser', 'adviser'],
        ]));

        $response->assertOk()->assertJson(['success' => true]);

        $expertId = DB::table('capstone_experts')->where('email', 'grom@gmail.com')->value('expert_id');
        $roles = DB::table('expert_roles')->where('expert_id', $expertId)->count();

        $this->assertSame(0, $roles);
        $this->assertFalse((bool) DB::table('capstone_experts')->where('expert_id', $expertId)->value('is_admin'));
    }

    public function test_expertise_is_still_saved_without_a_role_selection(): void
    {
        $response = $this->postJson(route('account.request.store'), $this->payload([
            'expertise' => ['PHP', 'Laravel'],
        ]));

        $response->assertOk()->assertJson(['success' => true]);

        $expertId = DB::table('capstone_experts')->where('email', 'grom@gmail.com')->value('expert_id');
        $fields = DB::table('expert_expertise')->where('expert_id', $expertId)->pluck('expertise_field')->sort()->values();

        $this->assertSame(['Laravel', 'PHP'], $fields->all());
    }
}
