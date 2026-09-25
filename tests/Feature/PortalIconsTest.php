<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Support\PortalIcons;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Every portal draws its navigation icons from one set (App\Support\PortalIcons):
 * the sidebar, the phone tab bar, the dashboard stat cards and their empty
 * states. These hold that each portal really does, that nothing in a sidebar
 * is left without a picture, and that the set itself is sound.
 */
class PortalIconsTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_icon_is_a_well_formed_stroked_svg(): void
    {
        foreach (PortalIcons::names() as $name) {
            $svg = PortalIcons::svg($name);

            $this->assertNotFalse(simplexml_load_string($svg), "The [{$name}] icon is not well-formed SVG.");
            $this->assertStringContainsString('viewBox="0 0 24 24"', $svg);
            $this->assertStringContainsString('stroke="currentColor"', $svg);
            $this->assertStringContainsString('data-icon="'.$name.'"', $svg);
            $this->assertStringContainsString('aria-hidden="true"', $svg);
        }
    }

    public function test_a_size_is_only_set_when_asked_for(): void
    {
        $this->assertStringNotContainsString(' width=', PortalIcons::svg('home'));
        $this->assertStringContainsString('width="20" height="20"', PortalIcons::svg('home', 20));
    }

    public function test_an_unknown_icon_name_fails_loudly(): void
    {
        $this->expectException(InvalidArgumentException::class);

        PortalIcons::paths('no-such-icon');
    }

    public function test_the_student_sidebar_gives_each_page_its_own_icon(): void
    {
        $html = $this->actingAs($this->studentInATeam())->get(route('student.dashboard'))
            ->assertOk()
            ->assertSeeInOrder([
                'data-icon="dashboard"', 'Dashboard',
                'data-icon="team"', 'Team Management',
                'data-icon="expert"', 'Capstone Expert',
                'data-icon="file-up"', 'Document Submission',
                'data-icon="feedback"', 'Review Viewer',
                'data-icon="similarity"', 'Pre-Proposal Submission',
                'data-icon="consultation"', 'Consultation',
                'data-icon="progress"', 'Progress Tracker',
                'data-icon="receipt"', 'Financial Records',
                'data-icon="book"', 'Resources',
                'data-icon="archive"', 'Archived Capstones',
                'data-icon="tour"', 'Portal Tour',
                'data-icon="sign-out"', 'Sign Out',
            ], false)
            ->getContent();

        $this->assertEveryNavItemHasAnIcon($html);
        $this->assertSame(1, substr_count($html, '.sidebar .nav-item.active > svg{'), 'The sidebar icon styles are printed once.');
    }

    /**
     * The student dashboard used to show loading cards first and remove them
     * once it had loaded; the stat card styles must not be printed inside a
     * loading card, or they go with it.
     */
    public function test_the_stat_card_styles_are_printed_with_a_real_card_not_a_loading_one(): void
    {
        $html = (string) $this->blade(
            '<div id="loading"><x-dashboard.stat-chip loading /></div>'
            .'<div id="real"><x-dashboard.stat-chip value="3" label="Teams" icon="team" color="var(--green)" /></div>'
        );

        $this->assertSame(1, substr_count($html, '.stat-chip.has-icon { position:relative'));
        $this->assertGreaterThan(strpos($html, 'id="real"'), strpos($html, '.stat-chip.has-icon { position:relative'));
        $this->assertStringContainsString('<span class="stat-ic" style="--stat-c:var(--green)">', $html);
        $this->assertStringContainsString('class="stat-chip has-icon"', $html);
    }

    public function test_the_capsa_portal_uses_the_shared_icons(): void
    {
        $capsa = $this->makeCapsa(CapstoneExpert::factory()->create(['approved_status' => true, 'is_admin' => false, 'sections' => ['BSIT-3A']]));

        $html = $this->actingAs($capsa, 'expert')->get(route('capsa.dashboard'))
            ->assertOk()
            ->assertSeeInOrder([
                'data-icon="dashboard"', 'Dashboard',
                'data-icon="announcement"', 'Announcements',
                'data-icon="expert"', 'Expert Management',
                'data-icon="section-list"', 'Student Section List',
                'data-icon="teams-manage"', 'Team Management',
                'data-icon="user-plus"', 'Panel Assignment',
                'data-icon="defense"', 'Defense Scheduling',
                'data-icon="rubric"', 'Grading Rubrics',
                'data-icon="receipt"', 'Financial Records',
                'data-icon="archive"', 'Archived Capstones',
                'data-icon="book"', 'Templates &amp; Lessons',
                'data-icon="reports"', 'Reports',
                'data-icon="analytics"', 'Analytics',
                'data-icon="history"', 'Activity Log',
            ], false)
            // Stat cards, each with its own picture.
            ->assertSeeInOrder(['data-icon="verified"', 'Teams to Verify', 'data-icon="file-check"', 'Titles to Review', 'data-icon="receipt"', 'Receipts to Check'], false)
            // No defenses coming up, so that card shows its empty state's icon.
            ->assertSeeInOrder(['data-icon="defense"', 'No defenses in the next two weeks.'], false)
            // The phone tab bar draws from the same set.
            ->assertSee(PortalIcons::paths('teams-manage'), false)
            ->getContent();

        $this->assertEveryNavItemHasAnIcon($html);
    }

    public function test_the_admin_portal_uses_the_shared_icons(): void
    {
        $admin = CapstoneExpert::factory()->create(['approved_status' => true, 'is_admin' => true]);

        $html = $this->actingAs($admin, 'expert')->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSeeInOrder([
                'data-icon="expert"', 'Expert Management',
                'data-icon="student"', 'Student Accounts',
                'data-icon="settings"', 'System Settings',
            ], false)
            ->getContent();

        $this->assertEveryNavItemHasAnIcon($html);
    }

    public function test_the_expert_portal_uses_the_shared_icons(): void
    {
        $expert = CapstoneExpert::factory()->create(['approved_status' => true, 'is_admin' => false]);

        $html = $this->actingAs($expert, 'expert')->get(route('instructor.dashboard'))
            ->assertOk()
            ->assertSeeInOrder([
                'data-icon="dashboard"', 'Dashboard',
                'data-icon="team"', 'Team Overview',
                'data-icon="grading"', 'Team Grading',
                'data-icon="inbox"', 'Team Assignments',
                'data-icon="consultation"', 'Schedule Management',
                'data-icon="file-review"', 'Document Review',
            ], false)
            ->assertSeeInOrder(['data-icon="inbox"', 'Team Requests', 'data-icon="file-review"', 'Documents to Review', 'data-icon="grading"', 'Grades to Submit'], false)
            ->assertSeeInOrder(['data-icon="announcement"', 'No announcements.'], false)
            ->assertSee(PortalIcons::paths('home'), false)
            ->getContent();

        $this->assertEveryNavItemHasAnIcon($html);
        // The Portal Tour button takes its size from .nav-item like its
        // neighbours, instead of the whole font of the list around it.
        $this->assertStringContainsString('class="nav-item" onclick="openPortalTour()" style="cursor:pointer;width:100%;background:none;border:0;border-left:2px solid transparent;font-family:inherit;', $html);
    }

    public function test_an_empty_card_with_no_icon_named_shows_the_inbox(): void
    {
        $this->blade('<x-dashboard.empty-state message="Nothing here." />')
            ->assertSee('data-icon="inbox"', false)
            ->assertSee('Nothing here.');
    }

    public function test_a_stat_card_without_an_icon_is_drawn_as_before(): void
    {
        $this->blade('<x-dashboard.stat-chip value="4" label="Plain" />')
            ->assertSee('class="stat-chip"', false)
            ->assertDontSee('<span class="stat-ic"', false)
            ->assertSee('Plain');
    }

    private function studentInATeam(): Student
    {
        $team = Team::create(['team_name' => 'Icon Team', 'project_title' => 'Icons', 'team_status' => 'Active']);
        $student = Student::factory()->withCompleteProfile()->create([
            'course' => 'Bachelor of Science in Information Technology', 'year_section' => '3rd · A',
        ]);
        StudentSchoolYear::create(['student_id' => $student->student_id, 'team_id' => $team->team_id, 'program' => 'BSIT']);

        return $student;
    }

    /** Every link in the sidebar's list wears exactly one icon from the set. */
    private function assertEveryNavItemHasAnIcon(string $html): void
    {
        $start = strpos($html, '<nav class="nav">');
        $this->assertNotFalse($start, 'No sidebar on the page.');
        $nav = substr($html, $start, strpos($html, '</nav>', $start) - $start);

        $items = substr_count($nav, 'class="nav-item');
        $this->assertGreaterThan(0, $items);
        $this->assertSame($items, substr_count($nav, 'data-icon="'), 'A sidebar link is missing its icon.');
    }
}
