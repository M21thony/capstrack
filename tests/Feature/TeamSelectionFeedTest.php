<?php

namespace Tests\Feature;

use App\Models\Recruitment;
use App\Models\Setting;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use Database\Seeders\StudentRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Which teams' posts Team selection lists.
 *
 * A team's post shows while it is below the CaPSA's member limit and goes
 * once it reaches it. The feed counts the members each time: the stored
 * "Full" status is never set back when somebody leaves, and member_count is
 * a flat 5 written at creation, so trusting either listed teams that were
 * full and hid teams that had room.
 */
class TeamSelectionFeedTest extends TestCase
{
    use RefreshDatabase;

    private int $people = 0;

    protected function setUp(): void
    {
        parent::setUp();
        (new StudentRoleSeeder)->run();
    }

    private function sectionmate(): Student
    {
        $this->people++;

        return Student::create([
            'email' => "mate{$this->people}@my.cspc.edu.ph",
            'f_name' => 'Mate',
            'l_name' => (string) $this->people,
            'course' => 'Bachelor of Science in Information Technology',
            'year_section' => '3rd · B',
            'p_picture' => 'avatar-01',
        ]);
    }

    private function viewer(): Student
    {
        $viewer = $this->sectionmate();
        StudentSchoolYear::create(['student_id' => $viewer->student_id, 'school_year' => 2026, 'program' => 'BSIT']);

        return $viewer;
    }

    /** A team of $size with a recruitment post, its stored status and member_count left as given. */
    private function postingTeam(int $size, string $status = 'Active'): Team
    {
        $leader = $this->sectionmate();
        $team = Team::create(['team_name' => 'Team '.$this->people, 'team_status' => $status, 'leader_id' => $leader->student_id, 'member_count' => 5]);

        StudentSchoolYear::create(['student_id' => $leader->student_id, 'team_id' => $team->team_id, 'school_year' => 2026, 'program' => 'BSIT']);
        for ($i = 1; $i < $size; $i++) {
            StudentSchoolYear::create(['student_id' => $this->sectionmate()->student_id, 'team_id' => $team->team_id, 'school_year' => 2026, 'program' => 'BSIT']);
        }

        Recruitment::create([
            'student_id' => $leader->student_id,
            'recruitment_goal' => 'build_a_team',
            'roles' => ['QA Tester/Documentation Writer'],
            'member_count' => 5,
        ]);

        return $team;
    }

    /** @return list<int> */
    private function pageFeed(Student $viewer): array
    {
        return $this->actingAs($viewer)->get(route('student.team'))->assertOk()
            ->viewData('otherRecruitments')->pluck('student_id')->all();
    }

    /** @return list<int> */
    private function apiFeed(Student $viewer): array
    {
        $ids = collect($this->actingAs($viewer)->getJson('/api/recruitments')->assertOk()->json('data'))
            ->pluck('recruitment_id')->all();

        return Recruitment::whereIn('recruitment_id', $ids)->pluck('student_id')->all();
    }

    public function test_a_team_at_the_capsa_limit_is_off_the_page_feed(): void
    {
        Setting::set('max_team_members', '3');
        $viewer = $this->viewer();
        $full = $this->postingTeam(3);
        $open = $this->postingTeam(2);

        $this->assertSame([$open->leader_id], $this->pageFeed($viewer));
        $this->assertNotContains($full->leader_id, $this->pageFeed($viewer));
    }

    public function test_a_team_at_the_capsa_limit_is_off_the_api_feed(): void
    {
        Setting::set('max_team_members', '3');
        $viewer = $this->viewer();
        $this->postingTeam(3);
        $open = $this->postingTeam(2);

        $this->assertSame([$open->leader_id], $this->apiFeed($viewer));
    }

    public function test_a_team_marked_full_that_lost_a_member_is_back_on_the_page_feed(): void
    {
        Setting::set('max_team_members', '3');
        $viewer = $this->viewer();
        $reopened = $this->postingTeam(2, 'Full');

        $this->assertSame([$reopened->leader_id], $this->pageFeed($viewer));
    }

    public function test_a_team_marked_full_that_lost_a_member_is_back_on_the_api_feed(): void
    {
        Setting::set('max_team_members', '3');
        $viewer = $this->viewer();
        $reopened = $this->postingTeam(2, 'Full');

        $this->assertSame([$reopened->leader_id], $this->apiFeed($viewer));
    }

    public function test_the_feed_follows_the_sections_team_rules_over_the_school_wide_limit(): void
    {
        Setting::set('max_team_members', '5');
        $viewer = $this->viewer();
        $atSectionLimit = $this->postingTeam(3);
        Setting::set(Team::RULES_KEY.$atSectionLimit->section(), json_encode(['max' => 3, 'create' => true, 'join' => true]));
        $open = $this->postingTeam(2);

        $this->assertSame([$open->leader_id], $this->pageFeed($viewer));
        $this->assertSame([$open->leader_id], $this->apiFeed($viewer));
    }
}
