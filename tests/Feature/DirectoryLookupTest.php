<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\Role;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Suggesting people who are already in CapsTrack, for the boxes where someone
 * is added one at a time.
 *
 * The point of the feature is that you pick a person instead of typing an
 * address and hoping, so what matters here is that it finds the right person,
 * shows enough to tell two people apart, and is not a way for anyone signed in
 * to page through the college.
 */
class DirectoryLookupTest extends TestCase
{
    use RefreshDatabase;

    private function staff(bool $isAdmin = false): CapstoneExpert
    {
        $expert = CapstoneExpert::factory()->create([
            'approved_status' => true,
            'is_admin' => $isAdmin,
            'sections' => ['BSIT-4A'],
        ]);

        $expert->roles()->attach(Role::firstOrCreate(['role_name' => 'capstone_adviser'])->role_id);

        return $expert;
    }

    private function student(array $attributes = []): Student
    {
        return Student::create(array_merge([
            'email' => 'a.bermudo@my.cspc.edu.ph',
            'student_number' => '21-04128',
            'f_name' => 'Angelo',
            'l_name' => 'Bermudo',
            'course' => 'BSIT',
            'year_section' => '4A',
        ], $attributes));
    }

    public function test_a_student_is_found_by_part_of_their_name(): void
    {
        $this->student();

        $people = $this->actingAs($this->staff(), 'expert')
            ->getJson(route('directory.people', ['q' => 'berm', 'kind' => 'student']))
            ->assertOk()
            ->json('people');

        $this->assertCount(1, $people);
        $this->assertSame('a.bermudo@my.cspc.edu.ph', $people[0]['email']);
        $this->assertSame('Angelo Bermudo', $people[0]['name']);
    }

    public function test_the_detail_says_where_they_already_are(): void
    {
        $this->student();

        $people = $this->actingAs($this->staff(), 'expert')
            ->getJson(route('directory.people', ['q' => 'angelo', 'kind' => 'student']))
            ->json('people');

        // Pulling a student out of a colleague's section should be a visible
        // decision, so where they are now travels with the suggestion.
        $this->assertStringContainsString('21-04128', $people[0]['detail']);
        $this->assertStringContainsString('BSIT', $people[0]['detail']);
        $this->assertStringContainsString('4A', $people[0]['detail']);
    }

    public function test_a_student_is_found_by_their_number(): void
    {
        $this->student();

        $people = $this->actingAs($this->staff(), 'expert')
            ->getJson(route('directory.people', ['q' => '04128', 'kind' => 'student']))
            ->json('people');

        $this->assertCount(1, $people);
    }

    public function test_an_expert_is_found_and_shows_the_roles_they_already_have(): void
    {
        $expert = CapstoneExpert::factory()->create([
            'first_name' => 'Rowena',
            'last_name' => 'Bituin',
            'email' => 'r.bituin@cspc.edu.ph',
            'approved_status' => true,
        ]);
        $expert->roles()->attach(Role::firstOrCreate(['role_name' => 'adviser'])->role_id);

        $people = $this->actingAs($this->staff(), 'expert')
            ->getJson(route('directory.people', ['q' => 'bitu', 'kind' => 'expert']))
            ->json('people');

        $this->assertSame('r.bituin@cspc.edu.ph', $people[0]['email']);
        // Adding someone who is already an adviser should look like a no-op,
        // not a surprise.
        $this->assertSame('Adviser', $people[0]['detail']);
    }

    public function test_two_letters_returns_nothing(): void
    {
        $this->student();

        // Short enough to match half the college; the floor is what stops the
        // box being a way to page through everyone.
        $this->actingAs($this->staff(), 'expert')
            ->getJson(route('directory.people', ['q' => 'be', 'kind' => 'student']))
            ->assertOk()
            ->assertJsonCount(0, 'people');
    }

    public function test_wildcards_in_the_query_are_not_treated_as_wildcards(): void
    {
        $this->student();

        // "%" would otherwise match everyone, which is exactly what the length
        // floor above is trying to prevent.
        $this->actingAs($this->staff(), 'expert')
            ->getJson(route('directory.people', ['q' => '%%%', 'kind' => 'student']))
            ->assertOk()
            ->assertJsonCount(0, 'people');
    }

    /**
     * The Admin is not in the directory.
     *
     * This box adds experts and names CaPSAs, and the Admin is neither - so
     * listing them did nothing but hand their address to anybody who typed
     * three letters of it, which on this system is every CaPSA.
     */
    public function test_the_admin_is_never_listed(): void
    {
        $admin = CapstoneExpert::factory()->create([
            'approved_status' => true,
            'is_admin' => true,
            'first_name' => 'Marites',
            'last_name' => 'Bandola',
            'email' => 'the.admin@cspc.edu.ph',
        ]);

        $capsa = $this->staff();

        foreach (['Marites', 'Bandola', 'the.admin'] as $query) {
            $found = $this->actingAs($capsa, 'expert')
                ->getJson(route('directory.people', ['q' => $query, 'kind' => 'expert']))
                ->assertOk()
                ->json('people');

            $this->assertNotContains(
                $admin->email,
                array_column($found, 'email'),
                "searching '{$query}' does not surface the admin"
            );
        }
    }

    /** An ordinary expert is still found, so the filter is not a blanket. */
    public function test_a_plain_expert_is_still_listed(): void
    {
        $expert = CapstoneExpert::factory()->create([
            'approved_status' => true,
            'is_admin' => false,
            'first_name' => 'Marites',
            'last_name' => 'Bandola',
            'email' => 'plain.expert@cspc.edu.ph',
        ]);

        $found = $this->actingAs($this->staff(), 'expert')
            ->getJson(route('directory.people', ['q' => 'Bandola', 'kind' => 'expert']))
            ->assertOk()
            ->json('people');

        $this->assertContains($expert->email, array_column($found, 'email'));
    }

    public function test_a_guest_cannot_use_it(): void
    {
        $this->student();

        $response = $this->getJson(route('directory.people', ['q' => 'berm', 'kind' => 'student']));

        $this->assertNotSame(200, $response->getStatusCode());
    }

    /**
     * Hiding the Admin from the directory stopped the suggestion, not the
     * submission. An address typed out in full still reached the add
     * endpoint, which looked the account up by email, found the Admin, and
     * attached a student-facing role to it - so they never appeared in the
     * CaPSA's own list, and did appear in front of every team on
     * student/experts.
     */
    public function test_the_admin_cannot_be_added_as_an_expert(): void
    {
        $admin = CapstoneExpert::factory()->create([
            'approved_status' => true, 'is_admin' => true,
            'first_name' => 'Mark', 'last_name' => 'Balilla',
            'email' => 'the.admin@cspc.edu.ph',
        ]);

        foreach (CapstoneExpert::STUDENT_FACING_ROLES as $role) {
            Role::firstOrCreate(['role_name' => $role]);
        }

        $this->actingAs($this->staff(), 'expert')
            ->postJson(route('capsa.experts.store'), [
                'email' => $admin->email,
                'name' => 'Mark Balilla',
                'roles' => ['consultant'],
            ])
            ->assertStatus(422)
            ->assertJsonFragment(['success' => false]);

        $this->assertCount(0, $admin->fresh()->roles);
    }

    /**
     * And the refusal does not say why.
     *
     * "That is the system administrator account" confirms which address
     * belongs to the Admin to anybody who guesses it - the refusal itself is
     * already a weak signal, and there is no reason to make it a plain one.
     */
    public function test_the_refusal_does_not_name_the_admin(): void
    {
        $admin = CapstoneExpert::factory()->create([
            'approved_status' => true, 'is_admin' => true,
            'email' => 'the.admin@cspc.edu.ph',
        ]);

        foreach (CapstoneExpert::STUDENT_FACING_ROLES as $role) {
            Role::firstOrCreate(['role_name' => $role]);
        }

        $message = $this->actingAs($this->staff(), 'expert')
            ->postJson(route('capsa.experts.store'), [
                'email' => $admin->email, 'name' => 'Somebody', 'roles' => ['consultant'],
            ])
            ->assertStatus(422)
            ->json('message');

        foreach (['administrator', 'admin', 'system'] as $word) {
            $this->assertStringNotContainsStringIgnoringCase($word, $message);
        }
    }

    /** Nor made a CaPSA by the same route. */
    public function test_the_admin_cannot_be_made_a_capsa(): void
    {
        $admin = CapstoneExpert::factory()->create([
            'approved_status' => true, 'is_admin' => true,
            'email' => 'the.admin@cspc.edu.ph',
        ]);

        Role::firstOrCreate(['role_name' => CapstoneExpert::CAPSA_ROLE]);

        $this->actingAs($this->staff(true), 'expert')
            ->postJson(route('admin.experts.capsa.store'), ['email' => $admin->email])
            ->assertStatus(422);

        $this->assertCount(0, $admin->fresh()->roles);
    }

    /** An ordinary expert still goes through, so this is not a blanket. */
    public function test_a_plain_expert_can_still_be_added(): void
    {
        foreach (CapstoneExpert::STUDENT_FACING_ROLES as $role) {
            Role::firstOrCreate(['role_name' => $role]);
        }

        $this->actingAs($this->staff(), 'expert')
            ->postJson(route('capsa.experts.store'), [
                'email' => 'new.expert@cspc.edu.ph',
                'name' => 'Rosa Villanueva',
                'roles' => ['consultant'],
            ])
            ->assertSuccessful();

        $this->assertNotNull(CapstoneExpert::where('email', 'new.expert@cspc.edu.ph')->first());
    }
}
