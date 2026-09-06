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

    public function test_a_guest_cannot_use_it(): void
    {
        $this->student();

        $response = $this->getJson(route('directory.people', ['q' => 'berm', 'kind' => 'student']));

        $this->assertNotSame(200, $response->getStatusCode());
    }
}
