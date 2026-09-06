<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Switching an expert account off instead of throwing it away.
 *
 * Removing somebody used to be a real DELETE, so a lecturer on study leave had
 * to be created from nothing when they came back - and their expertise, their
 * teams and everything else hanging off expert_id went with them the first
 * time.
 *
 * The thing worth pinning down is not the button. It is that a deactivated
 * expert stops existing for every query that looks for one - the browse page a
 * student applies from, panel assignment, the API - without any of those
 * queries having been touched. That is what the model-level scope buys, and it
 * is also what would break silently if somebody removed it.
 */
class ExpertDeactivationTest extends TestCase
{
    use RefreshDatabase;

    private function capsa(): CapstoneExpert
    {
        $capsa = CapstoneExpert::factory()->create([
            'approved_status' => true, 'is_admin' => false, 'sections' => ['BSIT-4A'],
        ]);

        $capsa->roles()->attach(Role::firstOrCreate(['role_name' => 'capstone_adviser'])->role_id);

        return $capsa;
    }

    private function expert(): CapstoneExpert
    {
        $expert = CapstoneExpert::factory()->create([
            'email' => 'r.villanueva@cspc.edu.ph',
            'approved_status' => true,
            'is_admin' => false,
        ]);

        $expert->roles()->attach(Role::firstOrCreate(['role_name' => 'adviser'])->role_id);

        return $expert;
    }

    public function test_removing_an_expert_keeps_the_row(): void
    {
        $expert = $this->expert();

        $this->actingAs($this->capsa(), 'expert')
            ->deleteJson(route('capsa.experts.destroy', $expert))
            ->assertOk();

        // Gone from every ordinary query...
        $this->assertNull(CapstoneExpert::find($expert->expert_id));
        // ...and still there.
        $this->assertNotNull(CapstoneExpert::withTrashed()->find($expert->expert_id));
        $this->assertNotNull(CapstoneExpert::withTrashed()->find($expert->expert_id)->deactivated_at);
    }

    public function test_it_can_be_switched_back_on(): void
    {
        $expert = $this->expert();
        $expert->delete();

        $this->actingAs($this->capsa(), 'expert')
            ->putJson(route('capsa.experts.reactivate', $expert->expert_id))
            ->assertOk()
            ->assertJson(['success' => true]);

        $back = CapstoneExpert::find($expert->expert_id);
        $this->assertNotNull($back);
        $this->assertNull($back->deactivated_at);
        // The whole point: they come back as themselves, not as a new account.
        $this->assertSame('r.villanueva@cspc.edu.ph', $back->email);
        $this->assertSame(1, $back->roles()->count());
    }

    public function test_switching_on_an_account_that_is_already_on_is_refused(): void
    {
        $expert = $this->expert();

        $this->actingAs($this->capsa(), 'expert')
            ->putJson(route('capsa.experts.reactivate', $expert->expert_id))
            ->assertStatus(422);
    }

    /**
     * The reason this lives on the model rather than in a dozen where()
     * clauses: none of these queries know deactivation exists.
     */
    public function test_a_deactivated_expert_is_invisible_to_every_ordinary_query(): void
    {
        $expert = $this->expert();

        $this->assertSame(1, CapstoneExpert::where('email', $expert->email)->count());
        $this->assertTrue(CapstoneExpert::where('approved_status', true)
            ->pluck('expert_id')->contains($expert->expert_id));

        $expert->delete();

        $this->assertSame(0, CapstoneExpert::where('email', $expert->email)->count());
        $this->assertFalse(CapstoneExpert::where('approved_status', true)
            ->pluck('expert_id')->contains($expert->expert_id));
        $this->assertFalse(CapstoneExpert::query()->acceptsProgram('BSIT')
            ->pluck('expert_id')->contains($expert->expert_id));
    }

    public function test_the_management_page_still_lists_them_so_they_can_be_switched_back(): void
    {
        $expert = $this->expert();
        $expert->delete();

        $html = $this->actingAs($this->capsa(), 'expert')
            ->get(route('capsa.experts'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString($expert->email, $html);
        $this->assertStringContainsString('Deactivated', $html);
        $this->assertStringContainsString('Switch this account back on', $html);
    }

    public function test_an_active_row_offers_the_way_off_and_not_the_way_back(): void
    {
        $this->expert();

        $html = $this->actingAs($this->capsa(), 'expert')
            ->get(route('capsa.experts'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Deactivate this account', $html);
        // Nobody on the page is off, so nothing offers the way back.
        $this->assertStringNotContainsString('Switch this account back on', $html);
    }

    /**
     * Without this they fall through to "no account for this address yet",
     * which invites them to apply for the account they already have and were
     * deliberately removed from.
     */
    public function test_signing_in_says_deactivated_rather_than_unknown(): void
    {
        // The mock sign-in path only knows @gmail.com for faculty, so this one
        // uses that rather than the CSPC address the others do.
        $expert = CapstoneExpert::factory()->create([
            'email' => 'r.villanueva@gmail.com',
            'approved_status' => true,
            'is_admin' => false,
        ]);
        $expert->delete();

        $this->withSession(['oauth_state' => 'test-state'])
            ->postJson(route('login.google.mock.post'), [
                'email' => $expert->email,
                'name' => 'Rosa Villanueva',
                'state' => 'test-state',
            ])
            ->assertStatus(403)
            ->assertJsonFragment([
                'message' => 'This account has been deactivated. Ask a CaPSA to switch it back on.',
            ]);
    }
}
