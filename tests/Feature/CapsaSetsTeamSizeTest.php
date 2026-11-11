<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The CaPSA/TSA decides how many members a team may have.
 *
 * The number used to be written out as 5 in seven places - the invitation
 * check, both recruitment paths, the team page - so it could not be changed
 * without finding all of them, and they had already drifted apart.
 */
class CapsaSetsTeamSizeTest extends TestCase
{
    use RefreshDatabase;

    private function capsa(): CapstoneExpert
    {
        $capsa = CapstoneExpert::factory()->create([
            'approved_status' => true, 'is_admin' => false, 'sections' => ['BSIT-4A'],
        ]);
        $capsa->roles()->attach(Role::firstOrCreate(['role_name' => CapstoneExpert::CAPSA_ROLE])->role_id);

        return $capsa;
    }

    /** The team rules are one resource, so every save carries all of them. */
    private function rules(int $max): array
    {
        return ['max_members' => ['BSIT' => $max], 'allow_creation' => true, 'allow_joining' => true];
    }

    public function test_five_until_a_capsa_says_otherwise(): void
    {
        $this->assertSame(5, Team::maxMembers());
    }

    public function test_a_capsa_can_set_it(): void
    {
        $this->actingAs($this->capsa(), 'expert')
            ->putJson(route('capsa.team_rules.update'), $this->rules(3))
            ->assertOk()
            ->assertJson(['success' => true, 'max_members' => ['BSIT' => 3]]);

        $this->assertSame(3, Team::maxMembers('BSIT-4A'));
    }

    /** Outside three to five is refused - that is the range the course runs. */
    public function test_a_size_outside_the_range_is_refused(): void
    {
        foreach ([2, 6, 0] as $bad) {
            $this->actingAs($this->capsa(), 'expert')
                ->putJson(route('capsa.team_rules.update'), $this->rules($bad))
                ->assertStatus(422)
                ->assertJsonValidationErrors('max_members.BSIT');
        }

        $this->assertSame(5, Team::maxMembers('BSIT-4A'));
    }

    /** A stored value outside the range never escapes the model either. */
    public function test_a_stored_value_is_still_clamped(): void
    {
        Setting::set('max_team_members', '99');

        $this->assertSame(5, Team::maxMembers());
    }

    /**
     * Every team follows the CaPSA/TSA, including one carrying a member_count.
     *
     * That column is written as a flat 5 whenever a team is made, so reading
     * it as a per-team override gave every team an "override" of 5 and the
     * setting changed nothing.
     */
    public function test_every_team_follows_the_capsa(): void
    {
        Setting::set('max_team_members', '3');

        $carriesFive = Team::create(['team_name' => 'Fixed', 'member_count' => 5]);
        $carriesNone = Team::create(['team_name' => 'Follows']);

        $this->assertSame(3, $carriesFive->memberLimit());
        $this->assertSame(3, $carriesNone->memberLimit());
    }
}
