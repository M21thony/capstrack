<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * How a faculty member who is on nobody's list gets an account.
 *
 * The login page has always told instructors to "request account access", and
 * for a while the two routes behind that sentence were commented out - so the
 * only way in was to find a CaPSA and ask. This is that way back, with two
 * things it never asked before: which programs they take teams from, and how
 * many of each.
 */
class ExpertAccountRequestTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_replace([
            'first_name' => 'Neil',
            'last_name' => 'Inocencio',
            'credentials' => 'MIT',
            'email' => 'neil.inocencio@cspc.edu.ph',
            'program_slots' => ['BSIT' => 2, 'BSIS' => 1],
            'expertise' => ['Systems Analysis', 'Database Systems'],
        ], $overrides);
    }

    public function test_the_form_is_reachable(): void
    {
        $this->get(route('account.request'))
            ->assertOk()
            ->assertSee('Who you are')
            ->assertSee('What you will take on')
            // The Skip is a control of its own, not a word inside a sentence.
            ->assertSee('id="ra-skip"', false)
            ->assertSee('Skip for now');
    }

    public function test_a_request_stores_the_programs_and_the_teams(): void
    {
        $this->postJson(route('account.request.store'), $this->payload())
            ->assertOk()
            ->assertJson(['success' => true]);

        $expert = CapstoneExpert::where('email', 'neil.inocencio@cspc.edu.ph')->first();

        $this->assertNotNull($expert);
        $this->assertFalse((bool) $expert->approved_status);
        // The shape parsedCapacity() reads back.
        $this->assertSame('2 - BSIT, 1 - BSIS', $expert->program_slots);
        $this->assertSame(3, (int) $expert->max_teams);

        $this->assertSame(
            ['Database Systems', 'Systems Analysis'],
            DB::table('expert_expertise')->where('expert_id', $expert->expert_id)
                ->pluck('expertise_field')->sort()->values()->all()
        );
    }

    public function test_the_programs_come_back_in_a_stable_order(): void
    {
        $this->postJson(route('account.request.store'), $this->payload([
            'program_slots' => ['BLIS' => 1, 'BSCS' => 2],
        ]))->assertOk();

        // Sent BLIS first, stored in the canonical PROGRAMS order.
        $this->assertSame(
            '2 - BSCS, 1 - BLIS',
            CapstoneExpert::where('email', 'neil.inocencio@cspc.edu.ph')->value('program_slots')
        );
    }

    public function test_both_optional_steps_can_be_skipped(): void
    {
        $this->postJson(route('account.request.store'), $this->payload([
            'program_slots' => [],
            'expertise' => [],
        ]))->assertOk()->assertJson(['success' => true]);

        $expert = CapstoneExpert::where('email', 'neil.inocencio@cspc.edu.ph')->first();

        // Which leaves them exactly where an approved expert who has never
        // opened their profile already is: on the list, not yet assignable.
        $this->assertNull($expert->program_slots);
        $this->assertNull($expert->max_teams);
        $this->assertSame(0, DB::table('expert_expertise')->where('expert_id', $expert->expert_id)->count());
    }

    public function test_a_zero_in_a_program_is_not_a_choice(): void
    {
        $this->postJson(route('account.request.store'), $this->payload([
            'program_slots' => ['BSIT' => 0, 'BSIS' => 2],
        ]))->assertOk();

        $this->assertSame(
            '2 - BSIS',
            CapstoneExpert::where('email', 'neil.inocencio@cspc.edu.ph')->value('program_slots')
        );
    }

    public function test_a_program_that_does_not_exist_is_refused(): void
    {
        $this->postJson(route('account.request.store'), $this->payload([
            'program_slots' => ['BSHM' => 2],
        ]))->assertStatus(422);

        $this->assertSame(0, CapstoneExpert::count());
    }

    /**
     * Sign-in takes cspc.edu.ph and gmail.com for faculty; this used to take
     * gmail only. A lecturer turned away at sign-in with their real address
     * landed here with it filled in and could not submit the form.
     */
    public function test_a_cspc_address_is_accepted_like_sign_in_accepts_it(): void
    {
        $this->postJson(route('account.request.store'), $this->payload([
            'email' => 'r.villanueva@cspc.edu.ph',
        ]))->assertOk();

        $this->postJson(route('account.request.store'), $this->payload([
            'email' => 'r.villanueva@yahoo.com',
        ]))->assertStatus(422);
    }

    public function test_an_approved_account_is_sent_to_sign_in(): void
    {
        CapstoneExpert::factory()->create([
            'email' => 'neil.inocencio@cspc.edu.ph',
            'approved_status' => true,
        ]);

        $this->postJson(route('account.request.store'), $this->payload())
            ->assertStatus(409)
            ->assertJsonFragment(['message' => 'Your account has already been approved. Please sign in instead.']);
    }

    public function test_a_pending_request_is_not_duplicated(): void
    {
        CapstoneExpert::factory()->create([
            'email' => 'neil.inocencio@cspc.edu.ph',
            'approved_status' => false,
            'rejection_reason' => null,
        ]);

        $this->postJson(route('account.request.store'), $this->payload())->assertStatus(409);
        $this->assertSame(1, CapstoneExpert::count());
    }

    public function test_a_rejected_request_can_be_sent_again_and_the_reason_clears(): void
    {
        $expert = CapstoneExpert::factory()->create([
            'email' => 'neil.inocencio@cspc.edu.ph',
            'approved_status' => false,
            'rejection_reason' => 'Not on any faculty list.',
            'reject_attempts' => 1,
        ]);

        $this->postJson(route('account.request.store'), $this->payload())
            ->assertOk()
            ->assertJson(['success' => true]);

        $fresh = $expert->fresh();
        $this->assertNull($fresh->rejection_reason);
        $this->assertSame('pending', $fresh->status);
        // Not reset: this was their second attempt, not a fresh start.
        $this->assertSame(1, (int) $fresh->reject_attempts);
    }

    public function test_the_third_rejection_holds_them_off_for_three_days(): void
    {
        CapstoneExpert::factory()->create([
            'email' => 'neil.inocencio@cspc.edu.ph',
            'approved_status' => false,
            'rejection_reason' => 'Third time.',
            'reject_attempts' => 3,
            'reject_cooldown_until' => now()->addDays(3),
        ]);

        $this->postJson(route('account.request.store'), $this->payload())->assertStatus(429);
    }

    public function test_once_the_wait_is_over_the_count_starts_again(): void
    {
        $expert = CapstoneExpert::factory()->create([
            'email' => 'neil.inocencio@cspc.edu.ph',
            'approved_status' => false,
            'rejection_reason' => 'Third time.',
            'reject_attempts' => 3,
            'reject_cooldown_until' => now()->subMinute(),
        ]);

        $this->postJson(route('account.request.store'), $this->payload())->assertOk();

        $fresh = $expert->fresh();
        $this->assertSame(0, (int) $fresh->reject_attempts);
        $this->assertNull($fresh->reject_cooldown_until);
    }

    public function test_the_role_is_never_set_from_this_form(): void
    {
        $this->postJson(route('account.request.store'), $this->payload())->assertOk();

        $expert = CapstoneExpert::where('email', 'neil.inocencio@cspc.edu.ph')->first();

        // A CaPSA assigns it at approval. Letting an applicant name themselves
        // Adviser would be letting them approve themselves.
        $this->assertSame(0, $expert->roles()->count());
        $this->assertFalse((bool) $expert->is_admin);
    }

    /**
     * Picking your own role used to happen on this form. A leftover
     * "specialization" in the body - an old cached page, a replayed request -
     * has to be dropped on the floor rather than honoured.
     */
    public function test_a_submitted_role_field_is_ignored_rather_than_honoured(): void
    {
        $this->postJson(route('account.request.store'), $this->payload([
            'specialization' => ['capstone_adviser', 'adviser'],
            'is_admin' => true,
            'approved_status' => true,
        ]))->assertOk();

        $expert = CapstoneExpert::where('email', 'neil.inocencio@cspc.edu.ph')->first();

        $this->assertSame(0, $expert->roles()->count());
        $this->assertFalse((bool) $expert->is_admin);
        $this->assertFalse((bool) $expert->approved_status);
        // is_capsa is holding the capstone_adviser role, not a column.
        $this->assertFalse($expert->is_capsa, 'A fresh request must not come out as a CaPSA.');
    }
}
