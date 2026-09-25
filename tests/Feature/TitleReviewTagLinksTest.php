<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\CapstonePreProposal;
use App\Models\DuplicationCheck;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Support\ProjectTags;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The project tags on a title under review are links.
 *
 * They used to be one comma-joined string in the answers list, which threw
 * away the only thing they are good for: the same fixed vocabulary drives the
 * Analytics tag filter, so "IoT" here can show every other team doing IoT -
 * which is exactly the question a CaPSA is asking while judging a title.
 */
class TitleReviewTagLinksTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_tag_links_to_the_teams_filtered_by_it(): void
    {
        $tag = ProjectTags::OPTIONS[5];

        $this->assertStringContainsString(
            'tag='.urlencode($tag),
            route('capsa.analytics', ['tag' => $tag]),
            'the analytics route no longer takes the tag as a query parameter'
        );
    }

    public function test_the_page_renders_each_tag_as_a_link_to_that_filter(): void
    {
        $tag = ProjectTags::OPTIONS[5];

        $leader = Student::factory()->withCompleteProfile()->create([
            'email' => 'leader.taggers@my.cspc.edu.ph',
            'course' => 'Bachelor of Science in Information Technology',
            'year_section' => '3rd · A',
        ]);
        $team = Team::create([
            'team_name' => 'Taggers',
            'team_status' => 'Active',
            'leader_id' => $leader->student_id,
            'is_confirmed' => false,
            'member_count' => 5,
        ]);
        $sy = StudentSchoolYear::create([
            'student_id' => $leader->student_id,
            'team_id' => $team->team_id,
        ]);
        $check = DuplicationCheck::create([
            'student_s_y_id' => $sy->s_y_id, 'document_path' => 'checks/slot1.pdf',
            'concepts' => ['title' => 'Slot 1'], 'top_score' => 20, 'verdict' => 'Low Similarity',
            'matches' => [], 'instructors_suggested' => [],
        ]);
        $proposal = CapstonePreProposal::create([
            'team_id' => $team->team_id,
            'duplication_check_id' => $check->id,
            'slot' => 1,
            'title' => 'Slot 1 Smart Automated Irrigation Monitoring System',
            'tags' => [$tag],
            'sdg_goals' => 'SDG 4',
            'research_agenda' => ['Applications and Systems Development'],
            'problem_statement' => 'A problem.',
            'proposed_solution' => 'A solution.',
            'scope' => 'A scope.',
            'limitations' => 'A limitation.',
            'sdm' => 'Agile',
            'submitted_at' => now(),
        ]);

        $capsa = CapstoneExpert::factory()->create([
            'approved_status' => true,
            'sections' => ['BSIT-3A'],
        ]);
        $this->makeCapsa($capsa);

        $html = $this->actingAs($capsa, 'expert')
            ->get(route('capsa.team_management.title', [$team->team_id, $proposal->id]))
            ->assertOk()
            ->getContent();

        // The tag is a real anchor pointing at the filtered view...
        $this->assertStringContainsString('href="'.e(route('capsa.analytics', ['tag' => $tag])).'"', $html);
        // ...it says where it goes on hover...
        $this->assertStringContainsString('See every team tagged '.e($tag), $html);
        // ...and it is no longer buried in a comma-joined line.
        $this->assertStringNotContainsString('<dd>'.e($tag).', ', $html);
    }

    public function test_the_tag_filter_still_matches_this_vocabulary_exactly(): void
    {
        // The link is only worth anything while both sides read the same list.
        // DefenseAnalytics filters with a strict in_array against a team's tags,
        // and those are filtered to ProjectTags::OPTIONS, so a tag rendered here
        // must be a member of that list.
        $analytics = file_get_contents(app_path('Support/DefenseAnalytics.php'));

        $this->assertStringContainsString("in_array(\$filters['tag']", $analytics);
        $this->assertStringContainsString('onTheList', $analytics);
    }

    public function test_a_tag_a_team_actually_carries_survives_to_analytics(): void
    {
        $tag = ProjectTags::OPTIONS[3];

        $leader = Student::create([
            'email' => 'leader@my.cspc.edu.ph', 'f_name' => 'Mark', 'l_name' => 'Balilla',
            'course' => 'BSIT', 'year_section' => '3rd · A',
        ]);
        $team = Team::create(['team_name' => 'Taggers', 'leader_id' => $leader->student_id]);
        $sy = StudentSchoolYear::create(['student_id' => $leader->student_id, 'team_id' => $team->team_id]);
        $check = DuplicationCheck::create([
            'student_s_y_id' => $sy->s_y_id, 'document_path' => 'duplication-checks/x.pdf',
            'concepts' => [], 'top_score' => 20, 'verdict' => 'Passed', 'matches' => [], 'instructors_suggested' => [],
        ]);
        CapstonePreProposal::create([
            'team_id' => $team->team_id,
            'duplication_check_id' => $check->id,
            'slot' => 1,
            'title' => 'A Tagged Study of Smart Automated Irrigation Monitoring',
            'tags' => [$tag],
            'sdg_goals' => 'SDG 4',
            'research_agenda' => ['Applications and Systems Development'],
            'problem_statement' => 'A problem.',
            'proposed_solution' => 'A solution.',
            'scope' => 'A scope.',
            'limitations' => 'A limitation.',
            'sdm' => 'Agile',
            'approved_status' => true,
            'submitted_at' => now(),
        ]);

        $capsa = CapstoneExpert::factory()->create([
            'approved_status' => true, 'is_admin' => false, 'sections' => [],
        ]);
        $capsa->roles()->attach(Role::firstOrCreate(['role_name' => 'capstone_adviser'])->role_id);

        // The page the tag links to opens, and the tag survives as its filter.
        $this->actingAs($capsa, 'expert')
            ->get(route('capsa.analytics', ['tag' => $tag]))
            ->assertOk()
            ->assertSee($tag);
    }
}
