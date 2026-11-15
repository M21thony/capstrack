<?php

namespace Tests\Feature;

use App\Models\Recruitment;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Support\TeamSelectionFeed;
use Database\Seeders\StudentRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Team selection: the section's recruitment posts, eight at a time, filtered
 * by what the post is for (building a team or finding one).
 */
class TeamSelectionTest extends TestCase
{
    use RefreshDatabase;

    private int $people = 0;

    protected function setUp(): void
    {
        parent::setUp();
        (new StudentRoleSeeder)->run();
    }

    private function classmate(): Student
    {
        $this->people++;
        $student = Student::create([
            'email' => "mate{$this->people}@my.cspc.edu.ph", 'f_name' => 'Mate', 'l_name' => (string) $this->people,
            'course' => 'Bachelor of Science in Information Technology', 'year_section' => '3rd · B', 'p_picture' => 'avatar-01',
        ]);
        StudentSchoolYear::create(['student_id' => $student->student_id, 'school_year' => 2026, 'program' => 'BSIT']);

        return $student;
    }

    /** A student looking for a team, with a post. */
    private function seeker(): Student
    {
        $student = $this->classmate();
        Recruitment::create(['student_id' => $student->student_id, 'recruitment_goal' => 'find_a_team', 'introduction' => 'Hi', 'looking_for' => 'Hi', 'member_count' => 5]);

        return $student;
    }

    /** A leader of a two-person team building it up, with a post. */
    private function builder(): Student
    {
        $leader = $this->classmate();
        $team = Team::create(['team_name' => 'Team '.$leader->student_id, 'leader_id' => $leader->student_id, 'team_status' => 'Active']);
        StudentSchoolYear::where('student_id', $leader->student_id)->update(['team_id' => $team->team_id]);
        StudentSchoolYear::where('student_id', $this->classmate()->student_id)->update(['team_id' => $team->team_id]);
        Recruitment::create(['student_id' => $leader->student_id, 'recruitment_goal' => 'build_a_team', 'looking_for' => 'Needs a tester', 'member_count' => 5, 'roles' => ['QA Tester/Documentation Writer']]);

        return $leader;
    }

    public function test_the_first_page_holds_eight_posts_and_says_how_many_there_are(): void
    {
        $viewer = $this->classmate();
        foreach (range(1, 10) as $i) {
            $this->seeker();
        }

        $page = TeamSelectionFeed::for($viewer)->page();

        $this->assertCount(8, $page->items());
        $this->assertSame(10, $page->total());
        $this->assertTrue($page->hasMorePages());
    }

    public function test_posts_are_counted_and_filtered_by_what_they_are_for(): void
    {
        $viewer = $this->classmate();
        $builders = [$this->builder(), $this->builder()];
        $this->seeker();
        $this->seeker();
        $this->seeker();

        $feed = TeamSelectionFeed::for($viewer);

        $this->assertSame(['all' => 5, 'build_a_team' => 2, 'find_a_team' => 3], $feed->counts());
        $this->assertEqualsCanonicalizing(
            array_map(fn ($s) => $s->student_id, $builders),
            collect($feed->page('build_a_team')->items())->pluck('student_id')->all()
        );
        $this->assertCount(3, $feed->page('find_a_team')->items());
    }

    public function test_a_card_of_someone_looking_for_a_team_names_their_primary_role_and_secondary_skills(): void
    {
        $viewer = $this->classmate();
        Recruitment::where('student_id', $this->seeker()->student_id)->first()
            ->update(['role_tags' => ['nd'], 'skills' => ['Web Development', 'Research']]);

        $html = $this->actingAs($viewer)->getJson(route('student.team.posts', ['goal' => 'find_a_team']))->json('html');

        $this->assertMatchesRegularExpression('/Primary<\/span><span class="ts-chip">Network Designer \/ UI Designer</', $html);
        $this->assertMatchesRegularExpression('/Secondary<\/span><span class="ts-chip">Web Development<\/span><span class="ts-chip">Research</', $html);
        $this->assertStringNotContainsString('(Primary)', $html);
    }

    public function test_load_more_returns_the_next_eight_as_cards(): void
    {
        $viewer = $this->classmate();
        foreach (range(1, 10) as $i) {
            $this->seeker();
        }

        $this->actingAs($viewer)->getJson(route('student.team.posts', ['page' => 2]))
            ->assertOk()
            ->assertJsonPath('shown', 10)
            ->assertJsonPath('total', 10)
            ->assertJsonPath('hasMore', false)
            ->assertJsonPath('counts.find_a_team', 10)
            ->assertJson(fn ($json) => $json->where('html', fn ($html) => substr_count($html, '<article class="ts-card') === 2)->etc());
    }

    public function test_a_filter_starts_again_from_the_first_eight(): void
    {
        $viewer = $this->classmate();
        $this->builder();
        foreach (range(1, 9) as $i) {
            $this->seeker();
        }

        $this->actingAs($viewer)->getJson(route('student.team.posts', ['goal' => 'find_a_team']))
            ->assertOk()
            ->assertJsonPath('shown', 8)
            ->assertJsonPath('total', 9)
            ->assertJsonPath('hasMore', true);
    }

    public function test_the_page_shows_the_first_eight_with_tabs_and_load_more(): void
    {
        $viewer = $this->classmate();
        $this->builder();
        foreach (range(1, 8) as $i) {
            $this->seeker();
        }

        $html = $this->actingAs($viewer)->get(route('student.team'))->assertOk()->getContent();

        $this->assertSame(8, substr_count($html, '<article class="ts-card'));
        $this->assertStringContainsString('Showing <span data-ts-shown>8</span> of <span data-ts-total>9</span>', $html);
        $this->assertMatchesRegularExpression('/data-ts-goal="build_a_team"[^>]*>.*?Find members.*?<span class="ts-tab-n">1<\/span>/s', $html);
        $this->assertMatchesRegularExpression('/data-ts-goal="find_a_team"[^>]*>.*?Find team.*?<span class="ts-tab-n">8<\/span>/s', $html);
    }
}
