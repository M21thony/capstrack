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

    // An expert can legitimately hold more than one of these three roles at
    // once (e.g. both Adviser and Consultant). This used to be rejected at
    // signup because a display query elsewhere (student/consultation) that
    // joined expert_id -> expert_roles wasn't deduplicating by expert,
    // so a multi-role expert showed up as duplicate entries - see
    // StudentConsultationController::loadConsultationData(), which now
    // groups by expert_id and combines role names instead of rejecting
    // multi-role signups in the first place.
    public function test_accepts_more_than_one_team_role(): void
    {
        $response = $this->postJson(route('account.request.store'), $this->payload([
            'specialization' => ['adviser', 'consultant'],
        ]));

        $response->assertOk()->assertJson(['success' => true]);

        $expertId = DB::table('capstone_experts')->where('email', 'grom@gmail.com')->value('expert_id');
        $this->assertNotNull($expertId);

        $roles = DB::table('expert_roles as er')
            ->join('roles as r', 'r.role_id', '=', 'er.role_id')
            ->where('er.expert_id', $expertId)
            ->pluck('r.role_name')
            ->sort()
            ->values();

        $this->assertSame(['adviser', 'consultant'], $roles->all());
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
    // team roles and may be combined with any of them - e.g. the seeded
    // CapsTrack Admin account is both capstone_adviser and adviser.
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
