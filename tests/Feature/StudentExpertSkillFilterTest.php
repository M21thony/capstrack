<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The skill filter runs in the query rather than the browser, because the
 * expert list is paginated - a client-side filter would only ever search the
 * experts already on screen and report "none" for everyone on page two.
 */
class StudentExpertSkillFilterTest extends TestCase
{
    use RefreshDatabase;

    private function makeStudentInTeam(): Student
    {
        $student = Student::create([
            'email' => 'leader@my.cspc.edu.ph',
            'f_name' => 'Team',
            'l_name' => 'Leader',
        ]);

        $team = Team::create([
            'team_name' => 'Filtering Team',
            'team_status' => 'Active',
            'leader_id' => $student->student_id,
            'is_confirmed' => true,
            'member_count' => 5,
        ]);

        StudentSchoolYear::create(['student_id' => $student->student_id, 'team_id' => $team->team_id]);

        return $student;
    }

    private function makeExpert(string $lastName, array $skills): CapstoneExpert
    {
        $expert = CapstoneExpert::factory()->create([
            'approved_status' => true,
            'last_name' => $lastName,
            'max_teams' => 4,
        ]);

        $expert->roles()->attach(Role::firstOrCreate(['role_name' => 'adviser'])->role_id);

        foreach ($skills as $skill) {
            DB::table('expert_expertise')->insert([
                'expert_id' => $expert->expert_id,
                'expertise_field' => $skill,
            ]);
        }

        return $expert;
    }

    public function test_selecting_a_skill_narrows_the_list_to_experts_who_have_it(): void
    {
        $student = $this->makeStudentInTeam();
        $this->makeExpert('Machinelearner', ['Machine Learning', 'Data Mining']);
        $this->makeExpert('Grammaticus', ['Technical Writing']);

        $response = $this->actingAs($student, 'web')->get('/student/experts?skill=Machine+Learning');

        $response->assertStatus(200);
        $response->assertSee('Machinelearner');
        $response->assertDontSee('Grammaticus');
    }

    // Dozens of free-text spellings in one flat list is unbrowsable, so the
    // picker files them into the curated areas from SkillCatalog.
    public function test_skills_are_offered_grouped_by_area(): void
    {
        $student = $this->makeStudentInTeam();
        $this->makeExpert('Machinelearner', ['Machine Learning']);
        $this->makeExpert('Grammaticus', ['Technical Writing']);

        $response = $this->actingAs($student, 'web')->get('/student/experts');

        $response->assertStatus(200);
        $response->assertSee('Machine Learning');
        $response->assertSee('Technical Writing');
        $response->assertSee('Data, AI &amp; Systems', false);
        $response->assertSee('Research &amp; Writing (Adviser)', false);
    }

    // A group nothing is filed under would be a filter that can only ever
    // return nothing, so it is not offered at all.
    public function test_areas_with_no_skills_in_use_are_not_offered(): void
    {
        $student = $this->makeStudentInTeam();
        $this->makeExpert('Machinelearner', ['Machine Learning']);

        $response = $this->actingAs($student, 'web')->get('/student/experts');

        $response->assertStatus(200);
        $response->assertSee('Data, AI &amp; Systems', false);
        $response->assertDontSee('Information &amp; Library Science', false);
    }

    // Picking a whole area is the point of grouping - the student shouldn't
    // need to know which of its skills each expert happened to write down.
    public function test_filtering_by_an_area_matches_every_skill_in_it(): void
    {
        $student = $this->makeStudentInTeam();
        $this->makeExpert('Machinelearner', ['Machine Learning']);
        $this->makeExpert('Dataminer', ['Data Mining']);
        $this->makeExpert('Grammaticus', ['Technical Writing']);

        $response = $this->actingAs($student, 'web')
            ->get('/student/experts?skill_group='.urlencode('Data, AI & Systems'));

        $response->assertStatus(200);
        $response->assertSee('Machinelearner');
        $response->assertSee('Dataminer');
        $response->assertDontSee('Grammaticus');
    }

    // Wording the catalogue doesn't recognise still has to be reachable -
    // grouping is a browsing aid, not a whitelist.
    public function test_an_unrecognised_skill_is_kept_under_other_skills(): void
    {
        $student = $this->makeStudentInTeam();
        $this->makeExpert('Basketweaver', ['Underwater Basket Weaving']);

        $response = $this->actingAs($student, 'web')->get('/student/experts');

        $response->assertStatus(200);
        $response->assertSee('Other skills');
        $response->assertSee('Underwater Basket Weaving');
    }

    public function test_an_unknown_area_is_ignored_rather_than_returning_nothing(): void
    {
        $student = $this->makeStudentInTeam();
        $this->makeExpert('Machinelearner', ['Machine Learning']);

        $response = $this->actingAs($student, 'web')->get('/student/experts?skill_group=Not+An+Area');

        $response->assertStatus(200);
        $response->assertSee('Machinelearner');
    }

    // The experts type their own skills, so the same skill turns up with
    // different casing and stray spaces. Matching those exactly would hide
    // experts who are genuinely qualified.
    public function test_matching_ignores_casing_and_surrounding_spaces(): void
    {
        $student = $this->makeStudentInTeam();
        $this->makeExpert('Machinelearner', ['  machine learning ']);

        $response = $this->actingAs($student, 'web')->get('/student/experts?skill=Machine+Learning');

        $response->assertStatus(200);
        $response->assertSee('Machinelearner');
    }

    public function test_an_unmatched_skill_says_so_instead_of_showing_an_empty_page(): void
    {
        $student = $this->makeStudentInTeam();
        $this->makeExpert('Machinelearner', ['Machine Learning']);

        $response = $this->actingAs($student, 'web')->get('/student/experts?skill=Underwater+Basket+Weaving');

        $response->assertStatus(200);
        $response->assertDontSee('Machinelearner');
        $response->assertSee('Show all experts');
    }

    // Experts write their own skills, so the same one arrives worded several
    // ways. A partial match is what makes that survivable: one search for
    // "web" reaches every spelling instead of the student having to guess
    // which exact phrasing each expert used.
    public function test_a_partial_word_matches_every_spelling_of_a_skill(): void
    {
        $student = $this->makeStudentInTeam();
        $this->makeExpert('Fullstack', ['Web Development']);
        $this->makeExpert('Frontender', ['Web Dev (React)']);
        $this->makeExpert('Grammaticus', ['Technical Writing']);

        $response = $this->actingAs($student, 'web')->get('/student/experts?skill=web');

        $response->assertStatus(200);
        $response->assertSee('Fullstack');
        $response->assertSee('Frontender');
        $response->assertDontSee('Grammaticus');
    }

    // With dozens of distinct spellings an A-Z list buries the useful ones,
    // so suggestions lead with the wording the most experts actually use.
    public function test_suggestions_are_ordered_by_how_many_experts_use_them(): void
    {
        $student = $this->makeStudentInTeam();
        $this->makeExpert('Alpha', ['Aardvark Studies', 'Web Development']);
        $this->makeExpert('Beta', ['Web Development']);
        $this->makeExpert('Gamma', ['Web Development']);

        $response = $this->actingAs($student, 'web')->get('/student/experts');
        $html = $response->getContent();

        $response->assertStatus(200);
        // Both sit in "Other skills" / "Software & Development"; what matters
        // is that within its group the popular wording is listed first.
        $this->assertLessThan(
            strpos($html, 'Aardvark Studies'),
            strpos($html, 'Web Development'),
            'the skill three experts share should be listed before the one only one expert lists'
        );
    }

    // A student typing a LIKE wildcard should search for it literally rather
    // than matching every expert on the page.
    public function test_a_wildcard_character_is_searched_for_literally(): void
    {
        $student = $this->makeStudentInTeam();
        $this->makeExpert('Machinelearner', ['Machine Learning']);

        $response = $this->actingAs($student, 'web')->get('/student/experts?skill=%25');

        $response->assertStatus(200);
        $response->assertDontSee('Machinelearner');
    }
}
