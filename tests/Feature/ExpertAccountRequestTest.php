<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

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
            'specialization' => ['consultant'],
            'expertise' => ['PHP'],
        ], $overrides);
    }

    // Regression test: an expert who registered under both Adviser and
    // Consultant showed up twice everywhere their team role is displayed
    // (e.g. student/consultation listed "2 advisers") because role was
    // derived by joining expert_id -> expert_roles, which returned every
    // role they'd registered under. An expert can only ever serve a team
    // in one of these three capacities, so more than one must be rejected
    // at signup.
    public function test_rejects_more_than_one_team_role(): void
    {
        $response = $this->postJson(route('account.request.store'), $this->payload([
            'specialization' => ['adviser', 'consultant'],
        ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('specialization');
        $this->assertDatabaseMissing('capstone_experts', ['email' => 'grom@gmail.com']);
    }

    public function test_accepts_a_single_team_role(): void
    {
        $response = $this->postJson(route('account.request.store'), $this->payload());

        $response->assertOk()->assertJson(['success' => true]);

        $expertId = DB::table('capstone_experts')->where('email', 'grom@gmail.com')->value('expert_id');
        $this->assertNotNull($expertId);

        $roles = DB::table('expert_roles as er')
            ->join('roles as r', 'r.role_id', '=', 'er.role_id')
            ->where('er.expert_id', $expertId)
            ->pluck('r.role_name');

        $this->assertSame(['consultant'], $roles->all());
    }

    // The capstone_adviser (admin) flag is a separate axis from the three
    // team roles and may still be combined with exactly one of them - e.g.
    // the seeded CapsTrack Admin account is both capstone_adviser and
    // adviser.
    public function test_allows_capstone_adviser_alongside_one_team_role(): void
    {
        $response = $this->postJson(route('account.request.store'), $this->payload([
            'specialization' => ['capstone_adviser', 'adviser'],
        ]));

        $response->assertOk()->assertJson(['success' => true]);

        $expertId = DB::table('capstone_experts')->where('email', 'grom@gmail.com')->value('expert_id');
        $roles = DB::table('expert_roles as er')
            ->join('roles as r', 'r.role_id', '=', 'er.role_id')
            ->where('er.expert_id', $expertId)
            ->pluck('r.role_name');

        $this->assertSame(['adviser'], $roles->all());
        $this->assertTrue((bool) DB::table('capstone_experts')->where('expert_id', $expertId)->value('is_admin'));
    }
}
