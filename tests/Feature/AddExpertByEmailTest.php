<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\ExpertExpertise;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A CaPSA adds one expert from their email address.
 *
 * The list upload is for a whole faculty at once. This is for the person it
 * missed - without it, admitting one late arrival meant re-uploading a
 * spreadsheet to serve a single row.
 */
class AddExpertByEmailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    private function capsa(): CapstoneExpert
    {
        return $this->makeCapsa(CapstoneExpert::create([
            'email' => 'capsa.adder@gmail.com',
            'first_name' => 'Cap',
            'last_name' => 'Sa',
            'is_admin' => false,
            'approved_status' => true,
        ]));
    }

    private function rolesOf(string $email): array
    {
        return DB::table('expert_roles')
            ->join('roles', 'roles.role_id', '=', 'expert_roles.role_id')
            ->join('capstone_experts', 'capstone_experts.expert_id', '=', 'expert_roles.expert_id')
            ->where('capstone_experts.email', $email)
            ->pluck('roles.role_name')->sort()->values()->all();
    }

    /** An email and a role is the whole of it. */
    public function test_an_email_and_a_role_adds_an_expert(): void
    {
        $this->actingAs($this->capsa(), 'expert');

        $response = $this->postJson(route('capsa.experts.store'), [
            'email' => 'late.arrival@cspc.edu.ph',
            'name' => 'Rosa Villanueva',
            'roles' => ['adviser'],
        ]);

        $response->assertOk()->assertJson(['success' => true, 'created' => true]);

        $expert = CapstoneExpert::where('email', 'late.arrival@cspc.edu.ph')->first();

        $this->assertNotNull($expert);
        $this->assertSame('Rosa', $expert->first_name);
        $this->assertSame('Villanueva', $expert->last_name);
        // Naming them is the vetting, the same as putting them on the list.
        $this->assertTrue((bool) $expert->approved_status);
        $this->assertSame(['adviser'], $this->rolesOf('late.arrival@cspc.edu.ph'));
    }

    /**
     * A name is required, because a guessed one is wrong on every screen.
     *
     * It used to be read off the address when blank, which gives
     * "Markanthonybalilla21" for an address with nothing to split on.
     */
    public function test_an_expert_cannot_be_added_without_a_name(): void
    {
        $this->actingAs($this->capsa(), 'expert');

        $this->postJson(route('capsa.experts.store'), [
            'email' => 'noname@cspc.edu.ph',
            'roles' => ['consultant'],
        ])->assertStatus(422)->assertJson(['success' => false]);

        $this->assertNull(CapstoneExpert::where('email', 'noname@cspc.edu.ph')->first());
    }

    /** A full name splits on the last space, so two given names survive. */
    public function test_a_full_name_splits_on_the_last_space(): void
    {
        $this->actingAs($this->capsa(), 'expert');

        $this->postJson(route('capsa.experts.store'), [
            'email' => 'threenames@cspc.edu.ph',
            'name' => 'Maria Cristina Dela Cruz',
            'roles' => ['adviser'],
        ])->assertOk();

        $expert = CapstoneExpert::where('email', 'threenames@cspc.edu.ph')->first();

        $this->assertSame('Maria Cristina Dela', $expert->first_name);
        $this->assertSame('Cruz', $expert->last_name);
    }

    /** One person, more than one role. */
    public function test_more_than_one_role_can_be_given_at_once(): void
    {
        $this->actingAs($this->capsa(), 'expert');

        $this->postJson(route('capsa.experts.store'), [
            'name' => 'Test Person',
            'email' => 'two.roles@cspc.edu.ph',
            'roles' => ['consultant', 'grammarian'],
        ])->assertOk();

        $this->assertSame(['consultant', 'grammarian'], $this->rolesOf('two.roles@cspc.edu.ph'));
    }

    /**
     * A role is required here, unlike the bulk upload.
     *
     * Adding one person is a deliberate act, and one with no role in it
     * leaves an account that can sign in and do nothing.
     */
    public function test_an_expert_cannot_be_added_without_a_role(): void
    {
        $this->actingAs($this->capsa(), 'expert');

        $this->postJson(route('capsa.experts.store'), [
            'name' => 'Test Person',
            'email' => 'norole@cspc.edu.ph',
            'roles' => [],
        ])->assertStatus(422)->assertJson(['success' => false]);

        $this->assertNull(CapstoneExpert::where('email', 'norole@cspc.edu.ph')->first());
    }

    /** An address nobody could sign in with is refused, and nothing is made. */
    public function test_an_address_nobody_could_sign_in_with_is_refused(): void
    {
        $this->actingAs($this->capsa(), 'expert');

        $this->postJson(route('capsa.experts.store'), [
            'name' => 'Test Person',
            'email' => 'someone@yahoo.com',
            'roles' => ['adviser'],
        ])->assertStatus(422)->assertJson(['success' => false]);

        $this->assertNull(CapstoneExpert::where('email', 'someone@yahoo.com')->first());
    }

    /** Somebody who already has an account is given the roles, not remade. */
    public function test_an_existing_account_is_updated_rather_than_duplicated(): void
    {
        $this->actingAs($this->capsa(), 'expert');

        $existing = CapstoneExpert::create([
            'email' => 'already@gmail.com',
            'first_name' => 'Al',
            'last_name' => 'Ready',
            'approved_status' => true,
        ]);

        $this->postJson(route('capsa.experts.store'), [
            'email' => 'already@gmail.com',
            'name' => 'Something Else',
            'roles' => ['grammarian'],
        ])->assertOk()->assertJson(['created' => false]);

        $existing->refresh();

        // The name they are known by is theirs, not something this rewrites.
        $this->assertSame('Al', $existing->first_name);
        $this->assertSame(1, CapstoneExpert::where('email', 'already@gmail.com')->count());
        $this->assertSame(['grammarian'], $this->rolesOf('already@gmail.com'));
    }

    /** Adding an expert cannot hand out CaPSA - that stays Admin's. */
    public function test_capsa_cannot_be_granted_through_this(): void
    {
        $this->actingAs($this->capsa(), 'expert');

        $this->postJson(route('capsa.experts.store'), [
            'name' => 'Test Person',
            'email' => 'sneaky@cspc.edu.ph',
            'roles' => ['adviser', 'capstone_adviser'],
        ])->assertStatus(422);

        $this->assertNull(CapstoneExpert::where('email', 'sneaky@cspc.edu.ph')->first());
    }

    /** Expertise stays the expert's own to fill in. */
    public function test_a_new_expert_starts_with_no_expertise(): void
    {
        $this->actingAs($this->capsa(), 'expert');

        $this->postJson(route('capsa.experts.store'), [
            'name' => 'Test Person',
            'email' => 'blank.skills@cspc.edu.ph',
            'roles' => ['adviser'],
        ])->assertOk();

        $expert = CapstoneExpert::where('email', 'blank.skills@cspc.edu.ph')->first();

        $this->assertSame(0, ExpertExpertise::where('expert_id', $expert->expert_id)->count());
    }

    /** The button and its fields are on the CaPSA's page. */
    public function test_the_page_offers_adding_an_expert_by_email(): void
    {
        $this->actingAs($this->capsa(), 'expert');

        $response = $this->get(route('capsa.experts'));

        $response->assertOk();
        $response->assertSee('Add Expert', false);
        $response->assertSee('id="addExpertEmail"', false);
        $response->assertSee('id="addExpertModal"', false);
    }
}
