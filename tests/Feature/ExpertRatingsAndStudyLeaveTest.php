<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\ExpertEvaluation;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Find experts shows what team leaders rated each expert, and a quiet
 * "Study leave" tag the CaPSA sets for an expert who is slow to reply and
 * hard to book for a while - and takes off again when the leave is over.
 */
class ExpertRatingsAndStudyLeaveTest extends TestCase
{
    use RefreshDatabase;

    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->student = Student::create(['email' => 'looking@my.cspc.edu.ph', 'f_name' => 'Team', 'l_name' => 'Leader']);
        $team = Team::create([
            'team_name' => 'Looking Team', 'team_status' => 'Active', 'leader_id' => $this->student->student_id,
            'is_confirmed' => true, 'member_count' => 5,
        ]);
        StudentSchoolYear::create(['student_id' => $this->student->student_id, 'team_id' => $team->team_id]);
    }

    private function adviser(array $attributes = []): CapstoneExpert
    {
        $expert = CapstoneExpert::factory()->create($attributes + ['approved_status' => true, 'max_teams' => 4, 'program_slots' => '4 - BSIT']);
        $expert->roles()->attach(Role::firstOrCreate(['role_name' => 'adviser'])->role_id);

        return $expert;
    }

    private function rated(CapstoneExpert $expert, int $rating, string $comment = 'Fine.'): void
    {
        $team = Team::create(['team_name' => 'Rater '.uniqid(), 'team_status' => 'Active', 'is_confirmed' => true, 'member_count' => 5]);
        ExpertEvaluation::factory()->create([
            'team_id' => $team->team_id, 'expert_id' => $expert->expert_id, 'checklist_milestone_id' => 1,
            'role' => 'adviser', 'rating' => $rating, 'comment' => $comment, 'student_id' => $this->student->student_id,
        ]);
    }

    private function findExperts(): TestResponse
    {
        return $this->actingAs($this->student, 'web')->get(route('student.experts', ['tab' => 'find', 'role' => 'adviser']));
    }

    public function test_find_experts_shows_the_average_rating_and_how_many(): void
    {
        $expert = $this->adviser();
        $this->rated($expert, 5);
        $this->rated($expert, 4);
        $this->rated($expert, 4);

        $this->findExperts()->assertOk()
            ->assertSee('Rated 4.3 out of 5')
            ->assertSee('4.3<small>/5</small>', false)
            ->assertSee('3 reviews');
    }

    /** A student sees the stars and how many - never what was answered, or by whom. */
    public function test_students_see_only_the_rating_and_the_count(): void
    {
        $expert = $this->adviser();
        $this->rated($expert, 5, 'Replied within a day every time.');
        $writer = Team::latest('team_id')->first();

        $this->findExperts()->assertOk()
            ->assertSee('1 review')
            ->assertDontSee('View comments')
            ->assertDontSee('Replied within a day every time.')
            ->assertDontSee($writer->team_name);
    }

    public function test_an_expert_nobody_rated_says_so(): void
    {
        $this->adviser();

        $this->findExperts()->assertOk()->assertSee('No reviews yet');
    }

    public function test_an_expert_on_study_leave_carries_a_quiet_tag(): void
    {
        $this->adviser(['first_name' => 'Lena', 'on_study_leave' => true]);

        $this->findExperts()->assertOk()
            ->assertSee('Study leave')
            ->assertSee('May be slow to reply and hard to book.')
            ->assertDontSee('master');
    }

    public function test_an_expert_not_on_leave_has_no_tag(): void
    {
        $this->adviser();

        $this->findExperts()->assertOk()->assertDontSee('<span class="xh-leave-tag">Study leave</span> May be slow to reply and hard to book.</p>', false);
    }

    private function capsa(): CapstoneExpert
    {
        $this->seed(RoleSeeder::class);

        return $this->makeCapsa(CapstoneExpert::create([
            'email' => 'capsa.leave@gmail.com', 'first_name' => 'Cap', 'last_name' => 'Sa',
            'is_admin' => false, 'approved_status' => true,
        ]));
    }

    public function test_the_capsa_can_mark_a_new_expert_as_on_study_leave(): void
    {
        $this->actingAs($this->capsa(), 'expert')
            ->postJson(route('capsa.experts.store'), ['email' => 'on.leave@cspc.edu.ph', 'roles' => ['adviser'], 'on_study_leave' => true])
            ->assertOk()->assertJson(['success' => true]);

        $this->assertTrue(CapstoneExpert::where('email', 'on.leave@cspc.edu.ph')->value('on_study_leave'));
    }

    public function test_adding_an_existing_expert_again_leaves_their_leave_alone(): void
    {
        $capsa = $this->capsa();
        $expert = $this->adviser(['email' => 'already@cspc.edu.ph', 'on_study_leave' => true]);

        $this->actingAs($capsa, 'expert')
            ->postJson(route('capsa.experts.store'), ['email' => 'already@cspc.edu.ph', 'roles' => ['consultant']])
            ->assertOk();

        $this->assertTrue($expert->fresh()->on_study_leave);
    }

    public function test_the_capsa_turns_study_leave_on_and_off_from_the_expert(): void
    {
        $capsa = $this->capsa();
        $expert = $this->adviser();

        $this->actingAs($capsa, 'expert')
            ->putJson(route('capsa.experts.study-leave.update', $expert), ['on_study_leave' => true])
            ->assertOk()->assertJson(['success' => true, 'on_study_leave' => true]);
        $this->assertTrue($expert->fresh()->on_study_leave);

        $this->actingAs($capsa, 'expert')
            ->putJson(route('capsa.experts.study-leave.update', $expert), ['on_study_leave' => false])
            ->assertOk()->assertJson(['on_study_leave' => false]);
        $this->assertFalse($expert->fresh()->on_study_leave);

        $this->actingAs($capsa, 'expert')
            ->putJson(route('capsa.experts.study-leave.update', $expert), [])
            ->assertStatus(422);
    }

    public function test_the_expert_list_shows_who_is_on_study_leave(): void
    {
        $capsa = $this->capsa();
        $this->adviser(['on_study_leave' => true]);

        $this->actingAs($capsa, 'expert')->get(route('capsa.experts'))
            ->assertOk()
            ->assertSee('class="cx-leave-tag"', false)
            ->assertSee('End study leave')
            ->assertSee('id="addExpertStudyLeave"', false);
    }
}
