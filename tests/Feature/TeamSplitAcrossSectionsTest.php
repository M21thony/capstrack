<?php

namespace Tests\Feature;

use App\Models\Recruitment;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Support\ActivityScope;
use App\Support\DefenseBoard;
use App\Support\SectionScope;
use App\Support\TeamChecklist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsChecklists;
use Tests\TestCase;

/**
 * A team forms inside one section in 3rd year. By 4th year its members can
 * be split - one in 4A, one in 4B - and still be one team finishing one
 * capstone. The team keeps one section, its leader's, so it keeps one CaPSA,
 * one checklist and one place in team management and defenses.
 */
class TeamSplitAcrossSectionsTest extends TestCase
{
    use BuildsChecklists;
    use RefreshDatabase;

    private Team $team;

    private Student $leader;

    private Student $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->leader = Student::factory()->withCompleteProfile()->create(['course' => 'Bachelor of Science in Information Technology', 'year_section' => '4th · A']);
        $this->member = Student::factory()->withCompleteProfile()->create(['course' => 'Bachelor of Science in Information Technology', 'year_section' => '4th · B']);
        $this->team = Team::create(['team_name' => 'Team1', 'team_status' => 'Active', 'is_confirmed' => true, 'leader_id' => $this->leader->student_id, 'member_count' => 5]);

        // The member's row comes first: the old rule took whoever came back first.
        StudentSchoolYear::create(['student_id' => $this->member->student_id, 'team_id' => $this->team->team_id]);
        StudentSchoolYear::create(['student_id' => $this->leader->student_id, 'team_id' => $this->team->team_id]);
    }

    public function test_the_team_takes_its_leaders_section_whoever_is_listed_first(): void
    {
        $this->assertSame('BSIT-4A', app(SectionScope::class)->sectionFor($this->team));

        // Pre-loaded rows in any order give the same answer.
        $records = StudentSchoolYear::with('student')->where('team_id', $this->team->team_id)->orderByDesc('s_y_id')->get();
        $this->assertSame('BSIT-4A', app(SectionScope::class)->sectionFor($this->team, $records));
        $this->assertSame('BSIT-4A', app(SectionScope::class)->sectionFor($this->team, $records->reverse()));
    }

    public function test_a_leader_with_no_section_falls_back_to_the_members(): void
    {
        $this->leader->update(['year_section' => null]);

        $this->assertSame('BSIT-4B', app(SectionScope::class)->sectionFor($this->team));
    }

    public function test_one_capsa_handles_the_whole_team(): void
    {
        $capsaA = $this->capsaFor(['BSIT-4A']);
        $capsaB = $this->capsaFor(['BSIT-4B']);
        $board = app(DefenseBoard::class);

        $this->assertTrue($board->covers($capsaA, $this->team));
        $this->assertFalse($board->covers($capsaB, $this->team), 'not two CaPSAs deciding for one team');

        $this->actingAs($capsaA, 'expert')->get(route('capsa.team_management'))->assertOk()->assertSee('Team1');
        $this->actingAs($capsaB, 'expert')->get(route('capsa.team_management'))->assertOk()->assertDontSee('Team1');
    }

    public function test_both_members_follow_the_same_checklist(): void
    {
        $capsaA = $this->capsaFor(['BSIT-4A']);
        $checklist = $this->departmentChecklist($capsaA);
        $this->capsaFor(['BSIT-4B']);

        $this->assertSame($checklist->id, app(TeamChecklist::class)->for($this->team)?->id);

        foreach ([$this->leader, $this->member] as $student) {
            $this->actingAs($student, 'web')->get(route('student.progress'))->assertOk()
                ->assertSee('Team1')
                ->assertSee('Pre-proposal Defense')
                ->assertDontSee('Your CaPSA has not set up a checklist for your team yet.');
        }
    }

    /** The other section's CaPSA sees their student is on a team that belongs elsewhere. */
    public function test_the_section_list_labels_a_team_that_belongs_to_another_section(): void
    {
        $this->actingAs($this->capsaFor(['BSIT-4B']), 'expert')->get(route('capsa.sections'))->assertOk()
            ->assertSee($this->member->l_name)
            ->assertSee('Team1')
            ->assertSee('Team in BSIT-4A')
            ->assertSee("That section's CaPSA handles its checklist, defenses and grading.");

        // The leader's own section shows the team plainly.
        $this->actingAs($this->capsaFor(['BSIT-4A']), 'expert')->get(route('capsa.sections'))->assertOk()
            ->assertSee($this->leader->l_name)
            ->assertSee('Team1')
            ->assertDontSee('Team in BSIT-4A');
    }

    public function test_the_capsa_follows_the_member_in_the_other_section(): void
    {
        $ids = ActivityScope::studentIdsFor($this->capsaFor(['BSIT-4A']));

        $this->assertContains($this->leader->student_id, $ids);
        $this->assertContains($this->member->student_id, $ids, 'still on the team they handle');
    }

    /** 3rd year is unchanged: a student forming a team sees posts from their own section only. */
    public function test_forming_a_team_in_third_year_still_shows_only_your_section(): void
    {
        $post = function (string $yearSection, string $intro): void {
            $poster = Student::factory()->withCompleteProfile()->create(['course' => 'Bachelor of Science in Information Technology', 'year_section' => $yearSection]);
            StudentSchoolYear::create(['student_id' => $poster->student_id]);
            Recruitment::create(['student_id' => $poster->student_id, 'recruitment_goal' => 'find_a_team', 'introduction' => $intro, 'roles' => []]);
        };
        $viewer = Student::factory()->withCompleteProfile()->create(['course' => 'Bachelor of Science in Information Technology', 'year_section' => '3rd · A']);
        StudentSchoolYear::create(['student_id' => $viewer->student_id]);
        $post('3rd · A', 'Classmate in my section looking for a team');
        $post('3rd · B', 'Someone from another section');

        $this->actingAs($viewer, 'web')->get(route('student.team'))->assertOk()
            ->assertSee('Classmate in my section looking for a team')
            ->assertDontSee('Someone from another section');
    }
}
