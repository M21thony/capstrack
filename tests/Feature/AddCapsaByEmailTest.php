<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * An Admin makes a CaPSA by typing their email address.
 *
 * There is no permanent CaPSA - the job moves between faculty - and making
 * one used to mean getting them into the system through the expert list
 * upload first, then finding them in it to grant the role. For one person
 * that is a spreadsheet to serve a single row.
 */
class AddCapsaByEmailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The roles are reference data a real install has from its seeder;
        // without them there is nothing to point the grant at.
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    private function admin(): CapstoneExpert
    {
        return CapstoneExpert::create([
            'email' => 'admin.capsa@test.com',
            'first_name' => 'Ad',
            'last_name' => 'Min',
            'is_admin' => true,
            'approved_status' => true,
        ]);
    }

    private function isCapsa(string $email): bool
    {
        return DB::table('expert_roles')
            ->join('roles', 'roles.role_id', '=', 'expert_roles.role_id')
            ->join('capstone_experts', 'capstone_experts.expert_id', '=', 'expert_roles.expert_id')
            ->where('capstone_experts.email', $email)
            ->where('roles.role_name', CapstoneExpert::CAPSA_ROLE)
            ->exists();
    }

    /** An email and a section is the whole of it. */
    public function test_an_email_alone_makes_a_capsa(): void
    {
        $this->actingAs($this->admin(), 'expert');

        $this->assertNull(CapstoneExpert::where('email', 'newcapsa@cspc.edu.ph')->first());

        $response = $this->postJson(route('admin.experts.capsa.store'), [
            'email' => 'newcapsa@cspc.edu.ph',
            'name' => 'Rosa Villanueva',
            'sections' => ['BSIT-4B'],
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true, 'created' => true]);

        $expert = CapstoneExpert::where('email', 'newcapsa@cspc.edu.ph')->first();

        $this->assertNotNull($expert);
        $this->assertSame('Rosa', $expert->first_name);
        $this->assertSame('Villanueva', $expert->last_name);
        $this->assertSame(['BSIT-4B'], $expert->sections);

        // Admin naming them is the vetting, so nobody is left to approve them.
        $this->assertTrue((bool) $expert->approved_status);
        $this->assertTrue($this->isCapsa('newcapsa@cspc.edu.ph'));
    }

    /** Without a name, the address is the only thing there is to go on. */
    public function test_a_name_is_taken_from_the_email_when_none_is_given(): void
    {
        $this->actingAs($this->admin(), 'expert');

        $this->postJson(route('admin.experts.capsa.store'), [
            'email' => 'juan.delacruz@cspc.edu.ph',
            'sections' => ['BSIT-4B'],
        ])->assertOk();

        $expert = CapstoneExpert::where('email', 'juan.delacruz@cspc.edu.ph')->first();

        $this->assertSame('Juan', $expert->first_name);
        $this->assertSame('Delacruz', $expert->last_name);
    }

    /**
     * An address with nothing to split on gives one name, not the same one
     * twice.
     *
     * "hellscreamgrom9@gmail.com" has no surname in it, and writing the whole
     * thing into both halves reads as "Hellscreamgrom9 Hellscreamgrom9" on
     * every screen that shows a name.
     */
    public function test_an_email_with_no_surname_in_it_does_not_repeat_the_name(): void
    {
        $this->actingAs($this->admin(), 'expert');

        $this->postJson(route('admin.experts.capsa.store'), [
            'email' => 'hellscreamgrom9@gmail.com',
            'sections' => ['BSIT-4B'],
        ])->assertOk();

        $expert = CapstoneExpert::where('email', 'hellscreamgrom9@gmail.com')->first();

        $this->assertSame('Hellscreamgrom9', $expert->first_name);
        $this->assertSame('', $expert->last_name);
        $this->assertSame('Hellscreamgrom9', $expert->name);
    }

    /** A one-word name typed in is treated the same way. */
    public function test_a_single_word_name_is_not_repeated_either(): void
    {
        $this->actingAs($this->admin(), 'expert');

        $this->postJson(route('admin.experts.capsa.store'), [
            'email' => 'oneword@gmail.com',
            'name' => 'Madonna',
            'sections' => ['BSIT-4B'],
        ])->assertOk();

        $this->assertSame('Madonna', CapstoneExpert::where('email', 'oneword@gmail.com')->first()->name);
    }

    /** Somebody who already has an account is granted the role, not remade. */
    public function test_an_existing_account_is_granted_the_role_rather_than_duplicated(): void
    {
        $this->actingAs($this->admin(), 'expert');

        $existing = CapstoneExpert::create([
            'email' => 'already@gmail.com',
            'first_name' => 'Al',
            'last_name' => 'Ready',
            'approved_status' => true,
        ]);

        $response = $this->postJson(route('admin.experts.capsa.store'), [
            'email' => 'already@gmail.com',
            'sections' => ['BSIT-4B'],
        ]);

        $response->assertOk();
        $response->assertJson(['created' => false]);

        // The name they are known by is theirs, not something this rewrites.
        $existing->refresh();
        $this->assertSame('Al', $existing->first_name);
        $this->assertSame(1, CapstoneExpert::where('email', 'already@gmail.com')->count());
        $this->assertTrue($this->isCapsa('already@gmail.com'));
    }

    /** The address is matched however it was typed. */
    public function test_the_email_is_matched_whatever_case_it_is_typed_in(): void
    {
        $this->actingAs($this->admin(), 'expert');

        CapstoneExpert::create([
            'email' => 'mixed@gmail.com',
            'first_name' => 'Mixed',
            'last_name' => 'Case',
            'approved_status' => true,
        ]);

        $this->postJson(route('admin.experts.capsa.store'), [
            'email' => 'Mixed@Gmail.com',
            'sections' => ['BSIT-4B'],
        ])->assertOk()->assertJson(['created' => false]);

        $this->assertSame(1, CapstoneExpert::where('email', 'mixed@gmail.com')->count());
    }

    /**
     * An address nobody can sign in with is refused.
     *
     * Creating the account would be worse than doing nothing: it would look
     * made, and then never be reachable by the person it was made for.
     */
    public function test_an_address_nobody_could_sign_in_with_is_refused(): void
    {
        $this->actingAs($this->admin(), 'expert');

        foreach (['someone@yahoo.com', 'student@my.cspc.edu.ph'] as $email) {
            $this->postJson(route('admin.experts.capsa.store'), [
                'email' => $email,
                'sections' => ['BSIT-4B'],
            ])->assertStatus(422)->assertJson(['success' => false]);

            $this->assertNull(CapstoneExpert::where('email', $email)->first());
        }
    }

    /** A CaPSA covers sections, so it cannot be granted without any. */
    public function test_a_capsa_cannot_be_added_without_a_section(): void
    {
        $this->actingAs($this->admin(), 'expert');

        $this->postJson(route('admin.experts.capsa.store'), [
            'email' => 'nosections@cspc.edu.ph',
            'sections' => [],
        ])->assertStatus(422)->assertJson(['success' => false]);

        $this->assertNull(CapstoneExpert::where('email', 'nosections@cspc.edu.ph')->first());
    }

    /** Granting CaPSA is Admin's, so a CaPSA cannot hand it out. */
    public function test_a_capsa_cannot_add_another_capsa(): void
    {
        $capsa = $this->makeCapsa(CapstoneExpert::create([
            'email' => 'plain.capsa@gmail.com',
            'first_name' => 'Plain',
            'last_name' => 'Capsa',
            'approved_status' => true,
        ]));

        $this->actingAs($capsa, 'expert');

        $this->postJson(route('admin.experts.capsa.store'), [
            'email' => 'sneaky@gmail.com',
            'sections' => ['BSIT-4B'],
        ])->assertForbidden();

        $this->assertNull(CapstoneExpert::where('email', 'sneaky@gmail.com')->first());
    }

    /** The button and its fields are on the page. */
    public function test_the_page_offers_adding_a_capsa_by_email(): void
    {
        $this->actingAs($this->admin(), 'expert');

        $response = $this->get(route('admin.experts'));

        $response->assertOk();
        $response->assertSee('Add CaPSA', false);
        $response->assertSee('id="capsaEmail"', false);
        $response->assertSee('id="capsaNewFields"', false);
    }
}
