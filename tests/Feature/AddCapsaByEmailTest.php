<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\Student;
use Database\Seeders\RoleSeeder;
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
        $this->seed(RoleSeeder::class);
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

    private function capsaWith(array $sections): CapstoneExpert
    {
        $capsa = CapstoneExpert::create([
            'email' => 'holder.capsa@test.com',
            'first_name' => 'Hol',
            'last_name' => 'Der',
            'approved_status' => true,
            'sections' => $sections,
        ]);
        $capsa->roles()->attach(DB::table('roles')->where('role_name', CapstoneExpert::CAPSA_ROLE)->value('role_id'));

        return $capsa;
    }

    /**
     * Left on the row, capsaFor() - which reads sections, not the role - kept
     * routing those sections to someone who was no longer a CaPSA.
     */
    public function test_taking_the_role_away_frees_their_sections(): void
    {
        $capsa = $this->capsaWith(['BSIT-3A']);

        $this->actingAs($this->admin(), 'expert')
            ->putJson(route('admin.experts.capsa.update', $capsa))
            ->assertOk()
            ->assertJson(['is_capsa' => false, 'sections' => []]);
    }

    /**
     * The page names a section nobody covers, and says why Deactivate is
     * greyed out for a CaPSA who still holds one.
     */
    public function test_the_page_names_uncovered_sections_and_why_a_capsa_cannot_be_deactivated(): void
    {
        $this->capsaWith(['BSIT-3A']);
        Student::create([
            'email' => 'nobody.covers@my.cspc.edu.ph', 'f_name' => 'No', 'l_name' => 'Cover',
            'course' => 'BSIT', 'year_section' => '3rd '.json_decode('"·"').' B',
        ]);

        $this->actingAs($this->admin(), 'expert')
            ->get(route('admin.experts'))
            ->assertOk()
            ->assertSee('1 section has no CaPSA yet')
            ->assertSee('BSIT-3B')
            ->assertSee('is still the CaPSA for BSIT-3A', false);
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

    /**
     * The address is the only thing asked for.
     *
     * A name was required here until a round of testing pointed out that
     * whoever types it is guessing: Google hands over the real one at the
     * person's first sign-in and overwrites whatever was typed. What must
     * never happen is the old behaviour of reading a name off the address -
     * that works for "juan.delacruz@" and gives "Markanthonybalilla21" for
     * an address with nothing to split on. Blank stays blank, and the list
     * says so in the row.
     */
    public function test_a_capsa_can_be_added_with_nothing_but_an_email(): void
    {
        $this->actingAs($this->admin(), 'expert');

        $this->postJson(route('admin.experts.capsa.store'), [
            'email' => 'noname@cspc.edu.ph',
        ])->assertOk()->assertJson(['success' => true, 'created' => true]);

        $made = CapstoneExpert::where('email', 'noname@cspc.edu.ph')->first();

        $this->assertNotNull($made);
        $this->assertSame('', $made->name);
        $this->assertTrue($this->isCapsa('noname@cspc.edu.ph'));
    }

    /**
     * A full name splits on the LAST space.
     *
     * "Mark Anthony Balilla" is Mark Anthony Balilla, not Mark of the family
     * Anthony Balilla. Two given names is the ordinary case here.
     */
    public function test_a_full_name_splits_on_the_last_space(): void
    {
        $this->actingAs($this->admin(), 'expert');

        $this->postJson(route('admin.experts.capsa.store'), [
            'email' => 'threenames@cspc.edu.ph',
            'name' => 'Mark Anthony Balilla',
            'sections' => ['BSIT-4B'],
        ])->assertOk();

        $expert = CapstoneExpert::where('email', 'threenames@cspc.edu.ph')->first();

        $this->assertSame('Mark Anthony', $expert->first_name);
        $this->assertSame('Balilla', $expert->last_name);
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

    /**
     * A new account is not credited with a team capacity nobody agreed to.
     *
     * The column defaulted to 4 and the model handed back 4 for a null, so
     * every account ever made looked willing to take four teams - and that
     * number fed the "slots left" reading and the assignment checks, so it
     * decided who could still be given work.
     */
    public function test_a_new_account_has_no_team_capacity_until_it_is_set(): void
    {
        $this->actingAs($this->admin(), 'expert');

        $this->postJson(route('admin.experts.capsa.store'), [
            'name' => 'Test Person',
            'email' => 'nocapacity@cspc.edu.ph',
            'sections' => ['BSIT-4B'],
        ])->assertOk();

        $expert = CapstoneExpert::where('email', 'nocapacity@cspc.edu.ph')->first();

        $this->assertNull($expert->max_teams);
        $this->assertSame([['ALL', 0]], $expert->parsedCapacity());
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
            'name' => 'Test Person',
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
            'name' => 'Test Person',
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

    /**
     * Sections can come later.
     *
     * They used to be required at this moment, which meant an Admin who
     * knew who to add but not yet which sections they would cover could not
     * add them at all. The same modal assigns sections afterwards.
     */
    public function test_a_capsa_can_be_added_before_their_sections_are_known(): void
    {
        $this->actingAs($this->admin(), 'expert');

        $this->postJson(route('admin.experts.capsa.store'), [
            'name' => 'Test Person',
            'email' => 'nosections@cspc.edu.ph',
            'sections' => [],
        ])->assertOk()->assertJson(['success' => true]);

        $made = CapstoneExpert::where('email', 'nosections@cspc.edu.ph')->first();

        $this->assertNotNull($made);
        $this->assertSame([], $made->sections ?? []);
        $this->assertTrue($this->isCapsa('nosections@cspc.edu.ph'));
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
            'name' => 'Test Person',
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
