<?php

namespace Tests\Feature;

use App\Models\CapstoneDocument;
use App\Models\CapstoneExpert;
use App\Models\CapstonePreProposal;
use App\Models\Checklist;
use App\Models\DefensePanel;
use App\Models\DefenseSchedule;
use App\Models\Deliverable;
use App\Models\DeliverableCheck;
use App\Models\DuplicationCheck;
use App\Models\ExpertRole;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Models\TeamExpertAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Team Management is the CaPSA's one page for the teams in her sections.
 *
 * The axis matters: this is section oversight - every team in the sections
 * she covers, whoever advises them - and NOT "My Expert Duties", which is
 * the separate question of teams that engaged her as an adviser.
 */
class CapsaTeamManagementTest extends TestCase
{
    use RefreshDatabase;

    private const SECTION = 'BSIT-3A';

    private function makeCapsaWithSections(array $sections = [self::SECTION]): CapstoneExpert
    {
        $capsa = CapstoneExpert::factory()->create([
            'approved_status' => true,
            'sections' => $sections,
        ]);

        $this->makeCapsa($capsa);
        $this->actingAs($capsa, 'expert');

        return $capsa;
    }

    /** A team whose members put it in SECTION. */
    private function makeTeamInSection(string $name, array $overrides = []): array
    {
        $leader = Student::factory()->withCompleteProfile()->create([
            'email' => 'leader.'.strtolower(str_replace(' ', '', $name)).'@my.cspc.edu.ph',
            'course' => 'Bachelor of Science in Information Technology',
            'year_section' => '3rd · A',
        ]);

        $team = Team::create(array_merge([
            'team_name' => $name,
            'team_status' => 'Active',
            'leader_id' => $leader->student_id,
            'is_confirmed' => false,
            'member_count' => 5,
        ], $overrides));

        $sy = StudentSchoolYear::create([
            'student_id' => $leader->student_id,
            'team_id' => $team->team_id,
        ]);

        return [$team, $leader, $sy];
    }

    private function addProposal(Team $team, StudentSchoolYear $sy, int $slot, bool $approved = false): CapstonePreProposal
    {
        $check = DuplicationCheck::create([
            'student_s_y_id' => $sy->s_y_id,
            'document_path' => "checks/slot{$slot}.pdf",
            'concepts' => ['title' => "Slot {$slot}"],
            'top_score' => 20,
            'verdict' => 'Low Similarity',
            'matches' => [],
            'instructors_suggested' => [],
        ]);

        return CapstonePreProposal::create([
            'team_id' => $team->team_id,
            'duplication_check_id' => $check->id,
            'slot' => $slot,
            'title' => "Slot {$slot} Smart Automated Irrigation Monitoring System",
            'sdg_goals' => 'SDG 4',
            'research_agenda' => ['Applications and Systems Development'],
            'problem_statement' => 'A problem.',
            'proposed_solution' => 'A solution.',
            'scope' => 'A scope.',
            'limitations' => 'A limitation.',
            'sdm' => 'Agile',
            'approved_status' => $approved,
            'submitted_at' => now(),
        ]);
    }

    public function test_it_lists_teams_in_the_capsas_sections(): void
    {
        $this->makeCapsaWithSections();
        [$mine] = $this->makeTeamInSection('Team Mine');

        $other = Student::factory()->withCompleteProfile()->create([
            'email' => 'elsewhere@my.cspc.edu.ph',
            'course' => 'Bachelor of Science in Computer Science',
            'year_section' => '4th · A',
        ]);
        $theirs = Team::create([
            'team_name' => 'Team Elsewhere',
            'team_status' => 'Active',
            'leader_id' => $other->student_id,
            'is_confirmed' => false,
            'member_count' => 5,
        ]);
        StudentSchoolYear::create(['student_id' => $other->student_id, 'team_id' => $theirs->team_id]);

        $response = $this->get(route('capsa.team_management'));

        $response->assertOk();
        $response->assertSee($mine->team_name);
        $response->assertDontSee($theirs->team_name);
    }

    // Team Verification only ever showed teams already marked Complete. A
    // team that stalled while still forming was invisible to the CaPSA -
    // which is exactly the team an oversight page should surface.
    public function test_it_shows_teams_that_have_not_reached_complete_yet(): void
    {
        $this->makeCapsaWithSections();
        [$stalled] = $this->makeTeamInSection('Team Stalled', ['team_status' => 'Active']);

        $response = $this->get(route('capsa.team_management'));

        $response->assertOk();
        $response->assertSee($stalled->team_name);
    }

    public function test_it_shows_milestone_progress_for_a_team(): void
    {
        $capsa = $this->makeCapsaWithSections();
        [$team] = $this->makeTeamInSection('Team Progress');

        // Worked out from the CaPSA's checks and the dates on her checklist,
        // never stored.
        $milestone = Checklist::create(['capsa_id' => $capsa->expert_id, 'program' => 'BSIT', 'title' => 'Capstone 1 Checklist'])
            ->milestones()->create(['name' => 'Getting started', 'position' => 0]);

        foreach (['done', 'done', 'open', 'open'] as $i => $state) {
            $deliverable = $milestone->deliverables()->create([
                'title' => "Deliverable {$i}",
                'kind' => Deliverable::FORM,
                'position' => $i,
                // Of the two open ones, one is past its due date - overdue -
                // and one is not due yet.
                'due_date' => $i === 3 ? now()->addWeek()->toDateString() : now()->subWeek()->toDateString(),
            ]);

            if ($state === 'done') {
                DeliverableCheck::create([
                    'deliverable_id' => $deliverable->id, 'team_id' => $team->team_id,
                    'status' => DeliverableCheck::COMPLETE, 'checked_at' => now()->subDays(10),
                ]);
            }
        }

        $response = $this->get(route('capsa.team_management', ['tab' => 'teams']));

        $response->assertOk();
        // One block per requirement-bearing milestone, and a line saying how far along.
        $response->assertSee('aria-label="0 of 1 milestones complete"', false);
        $response->assertSee('1 overdue');
    }

    /**
     * The team page draws only the requirements that fit the window; the
     * rest sit behind "See all", which opens the team in the tracker.
     */
    public function test_the_team_page_links_a_milestones_requirements_to_the_tracker(): void
    {
        $capsa = $this->makeCapsaWithSections();
        [$team] = $this->makeTeamInSection('Team Long Milestone');

        $milestone = Checklist::create(['capsa_id' => $capsa->expert_id, 'program' => 'BSIT', 'title' => 'Capstone 1 Checklist'])
            ->milestones()->create(['name' => 'Getting started', 'position' => 0]);

        foreach (range(0, 6) as $i) {
            $milestone->deliverables()->create(['title' => "Deliverable {$i}", 'kind' => Deliverable::FORM, 'position' => $i]);
        }

        $response = $this->get(route('capsa.team_management.show', $team->team_id))->assertOk();

        $response->assertSee('data-tp-more="7" hidden', false);
        $response->assertSee('See all 7 requirements');
        $response->assertSee(e(route('capsa.team_management', ['tab' => 'milestones', 'section' => self::SECTION, 'team' => $team->team_id])), false);
    }

    /**
     * A signed paper closes on the CaPSA's check, and the check lives in the
     * Milestones tracker. On the team's own page the line used to read
     * "Handed in" and stop there, with nothing to click.
     */
    public function test_a_paper_waiting_on_her_check_is_clickable_from_the_team_page(): void
    {
        $capsa = $this->makeCapsaWithSections();
        [$team, $leader] = $this->makeTeamInSection('Team Waiting');

        $form = Checklist::create(['capsa_id' => $capsa->expert_id, 'program' => 'BSIT', 'title' => 'Capstone 1 Checklist'])
            ->milestones()->create(['name' => 'Getting started', 'position' => 0])
            ->deliverables()->create(['title' => 'Signed Project Team Assignment Form', 'kind' => Deliverable::FORM, 'position' => 0]);

        CapstoneDocument::create([
            'student_id' => $leader->student_id, 'file_name' => 'scan.pdf', 'deliverable_id' => $form->id,
            'file_path' => 'submissions/scan.pdf', 'file_version' => 1, 'document_status' => 'Pending',
        ]);

        $response = $this->get(route('capsa.team_management.show', $team->team_id));

        $response->assertOk();
        // Straight to the tracker's check for this very paper, not just the tab.
        $response->assertSee('team='.$team->team_id.'&amp;check='.$form->id, false);
        // and back to this page once it is checked or put down.
        $response->assertSee('return='.urlencode('/capsa/team-management/'.$team->team_id), false);
    }

    /**
     * The roles shown are the ones the team engaged, read from the
     * assignment's expert_role_id. An expert who is both an adviser and a
     * consultant must count as whichever this team took them for, not both.
     */
    public function test_it_shows_the_role_a_team_engaged_an_expert_as(): void
    {
        $this->makeCapsaWithSections();
        [$team] = $this->makeTeamInSection('Team Roles');

        $expert = CapstoneExpert::factory()->create(['approved_status' => true, 'last_name' => 'Dualrole']);
        foreach (['adviser', 'consultant'] as $roleName) {
            $expert->roles()->attach(Role::firstOrCreate(['role_name' => $roleName])->role_id);
        }

        $consultantRoleId = ExpertRole::where('expert_id', $expert->expert_id)
            ->where('role_id', Role::where('role_name', 'consultant')->value('role_id'))
            ->value('expert_role_id');

        TeamExpertAssignment::create([
            'team_id' => $team->team_id,
            'expert_id' => $expert->expert_id,
            'expert_role_id' => $consultantRoleId,
            'status' => 'approved',
        ]);

        $response = $this->get(route('capsa.team_management.show', $team->team_id));

        $response->assertOk();
        // The filled seat names the role the team engaged them AS, not every
        // role they hold; the two empty seats say which role is still open.
        $response->assertSeeInOrder([$expert->name, 'Consultant', 'No grammarian yet']);
        $response->assertSee('No adviser yet');
        $response->assertDontSee('<small>Adviser</small>', false);
    }

    // Two approved titles is the state that otherwise looks like an
    // unexplained dead end: verification is blocked and nothing says why,
    // because it is the team's move, not the CaPSA's.
    public function test_it_explains_why_a_team_with_two_approved_titles_cannot_be_verified(): void
    {
        $this->makeCapsaWithSections();
        [$team, , $sy] = $this->makeTeamInSection('Team Choosing', ['team_status' => 'Active']);

        $this->addProposal($team, $sy, 1, approved: true);
        $this->addProposal($team, $sy, 2, approved: true);

        $response = $this->get(route('capsa.team_management'));

        $response->assertOk();
        $response->assertSee('Team Choosing');
        $response->assertSee('2 recommended - the team is choosing');
    }

    /**
     * Recommending or rejecting is a decision about ONE title, so both live
     * on that title's own page rather than in a modal stacked over a list.
     * The reject dialog names the title: a team submits three, and a dialog
     * that could be about any of them invites rejecting the wrong one.
     */
    /**
     * The research agenda is a list on the form, and reads as one here - a
     * scan that ran the bullets together into one item included.
     */
    public function test_the_title_page_lists_the_research_agenda_one_item_per_line(): void
    {
        $this->makeCapsaWithSections();
        [$team, , $sy] = $this->makeTeamInSection('Team Agenda');

        $proposal = $this->addProposal($team, $sy, 1);
        $proposal->update([
            'research_agenda' => ['• Applications and Systems Development • e-Services • Data Science'],
            'sdg_goals' => 'SDG 4 – Quality Education, SDG 9 – Industry, Innovation and Infrastructure',
        ]);

        $this->get(route('capsa.team_management.title', [$team->team_id, $proposal->id]))
            ->assertOk()
            ->assertSee('Research agenda and priority areas')
            ->assertSeeInOrder(['<ul class="ans-list">', '<li>Applications and Systems Development</li>', '<li>e-Services</li>', '<li>Data Science</li>'], false)
            ->assertSeeInOrder(['<li>SDG 4 – Quality Education</li>', '<li>SDG 9 – Industry, Innovation and Infrastructure</li>'], false)
            ->assertDontSee('• e-Services');
    }

    public function test_the_title_page_carries_the_recommend_and_reject_dialogs(): void
    {
        $this->makeCapsaWithSections();
        [$team, , $sy] = $this->makeTeamInSection('Team Dialogs');

        $proposal = $this->addProposal($team, $sy, 1);

        $html = $this->get(route('capsa.team_management.title', [$team->team_id, $proposal->id]))
            ->assertOk()->getContent();

        $this->assertStringContainsString('id="rec-dlg"', $html);
        $this->assertStringContainsString('id="rej-dlg"', $html);
        $this->assertStringContainsString('Reject this title', $html);
        $this->assertStringContainsString('Rejecting: '.$proposal->title, $html);

        // Native dialogs on the page the decision is about - no scrim to
        // stack, and nothing left of the old review modal.
        $this->assertStringNotContainsString('cf-scrim', $html);
    }

    /**
     * Seven equal tabs with red count badges read as a wall of errors and
     * gave no sense of what belonged to what. The strip is gone: the teams
     * are the page, and the head says where her decisions wait.
     */
    public function test_the_head_says_where_her_decisions_wait(): void
    {
        $this->makeCapsaWithSections();
        [$team, , $sy] = $this->makeTeamInSection('Team Toolbar');

        $this->addProposal($team, $sy, 1);

        $html = $this->get(route('capsa.team_management'))
            ->assertOk()->getContent();

        // The summary names the waiting work, and To do lists it.
        $this->assertStringContainsString('1 to do', $html);
        $this->assertStringContainsString('Review 1 title', $html);

        // The old strip and its red badges are gone with it.
        $this->assertStringNotContainsString('tm-toolbar', $html);
        $this->assertStringNotContainsString('tab-count', $html);
    }

    /**
     * A team that just turned a title in is the first row, marked, with how
     * long ago - she no longer scrolls to find it.
     */
    public function test_the_team_that_just_submitted_is_on_top_and_marked(): void
    {
        $this->makeCapsaWithSections();
        [$fresh, , $freshSy] = $this->makeTeamInSection('Team Fresh');
        [$older, , $olderSy] = $this->makeTeamInSection('Team Older');
        $this->makeTeamInSection('Team Quiet');

        $this->addProposal($fresh, $freshSy, 1);
        $this->addProposal($older, $olderSy, 1)->update(['submitted_at' => now()->subDays(2)]);

        $html = $this->get(route('capsa.team_management'))->assertOk()->getContent();
        $teams = substr($html, strpos($html, 'id="tm-teams"'), strpos($html, 'class="legend"') - strpos($html, 'id="tm-teams"'));

        $this->assertLessThan(strpos($teams, 'Team Older'), strpos($teams, 'Team Fresh'));
        $this->assertLessThan(strpos($teams, 'Team Quiet'), strpos($teams, 'Team Older'));

        $this->assertSame(2, substr_count($teams, 'class="wait"'));
        $this->assertStringContainsString('To do: Review 1 title', $teams);
        // To do says how long each has waited, and marks only today's as new.
        $this->assertStringContainsString('2 days ago', $html);
        $this->assertSame(1, substr_count($html, 'class="new" aria-label="New"'));
    }

    /** The list refreshes itself in place: the rows and counts, not the page. */
    public function test_the_list_refreshes_itself_without_reloading_the_page(): void
    {
        $this->makeCapsaWithSections();
        $this->makeTeamInSection('Team Live');

        $html = $this->get(route('capsa.team_management'))->assertOk()->getContent();

        $this->assertStringContainsString('setInterval(refresh, 15000);', $html);
        $this->assertStringContainsString("const parts = ['#tm-todo-waiting', '#tm-todo-decided', '#tm-apps-waiting', '#tm-apps-declined', '#tm-apps-data',", $html);
        $this->assertStringContainsString("'#tm-apps-nw', '#tm-apps-nd', '#tm-teams tbody',", $html);
        // Signed out meanwhile: nothing is swapped in.
        $this->assertStringContainsString('if (!res.ok || res.redirected) { return; }', $html);
        $this->assertStringNotContainsString('location.reload', $html);
    }

    /** Members show their profile photo; anyone without one keeps their initials. */
    public function test_the_team_page_shows_members_profile_photos(): void
    {
        $this->makeCapsaWithSections();
        [$team, $leader] = $this->makeTeamInSection('Team Photos');
        $leader->update(['f_name' => 'Ana', 'l_name' => 'Reyes', 'p_picture' => 'https://lh3.googleusercontent.com/a/ana-photo']);
        $member = Student::factory()->withCompleteProfile()->create(['f_name' => 'Ben', 'l_name' => 'Cruz', 'p_picture' => null]);
        StudentSchoolYear::create(['student_id' => $member->student_id, 'team_id' => $team->team_id]);

        $html = $this->get(route('capsa.team_management.show', $team->team_id))->assertOk()->getContent();

        $this->assertStringContainsString('<img src="https://lh3.googleusercontent.com/a/ana-photo"', $html);
        $this->assertSame(1, substr_count($html, 'referrerpolicy="no-referrer" onerror="this.remove()"'));
        // Ben has no photo: his initials are what shows.
        $this->assertMatchesRegularExpression('/>BC\s*<\/span>/', $html);
    }

    public function test_the_team_page_counts_titles_awaiting_review(): void
    {
        $this->makeCapsaWithSections();
        [$team, , $sy] = $this->makeTeamInSection('Team Titles');

        $this->addProposal($team, $sy, 1);
        $this->addProposal($team, $sy, 2);
        $this->addProposal($team, $sy, 3, approved: true);

        $response = $this->get(route('capsa.team_management.show', $team->team_id));

        $response->assertOk();
        $response->assertSee('2 waiting');
    }

    /**
     * The page ordered and printed the schedule by a column that does not
     * exist, so opening a team that had ever been given a defense date blew
     * up. The date lives in schedule_date.
     */
    public function test_the_team_page_prints_the_defense_date(): void
    {
        $this->makeCapsaWithSections();
        [$team] = $this->makeTeamInSection('Team Scheduled');

        DefenseSchedule::create([
            'team_id' => $team->team_id,
            'defense_type' => 'Title Defense',
            'attempt' => 1,
            'schedule_date' => '2026-03-11 08:00:00',
            'end_date' => '2026-03-11 10:00:00',
            'venue' => 'Mac Lab',
        ]);

        $this->get(route('capsa.team_management.show', $team->team_id))
            ->assertOk()
            ->assertSee('Mar 11, 2026');
    }

    /**
     * Papers reach this tab only under the subject_adviser role - her CaPSA
     * hat. What she reviews as somebody's adviser is a different job and
     * stays in her To do with the rest of her expert work.
     */
    public function test_the_team_page_shows_only_papers_sent_to_her_as_subject_adviser(): void
    {
        $capsa = $this->makeCapsaWithSections();
        [$team, $leader] = $this->makeTeamInSection('Team Papers');

        $formatCheck = $this->makeDocumentReview($capsa, $leader, 'Chapter 1 Format.pdf', 'subject_adviser');
        $adviserWork = $this->makeDocumentReview($capsa, $leader, 'Chapter 2 Content.pdf', 'adviser');

        $response = $this->get(route('capsa.team_management.show', $team->team_id));

        $response->assertOk();
        $response->assertSee('Chapter 1 Format.pdf');
        $response->assertDontSee('Chapter 2 Content.pdf');
        $this->assertNotSame($formatCheck, $adviserWork);
    }

    /** A paper sent for the format check opens with Review, like every other paper. */
    public function test_a_format_check_paper_opens_with_review(): void
    {
        $capsa = $this->makeCapsaWithSections();
        [$team, $leader] = $this->makeTeamInSection('Team Format');
        $reviewId = $this->makeDocumentReview($capsa, $leader, 'Chapter 1 Format.pdf', 'subject_adviser');
        $link = 'href="'.route('instructor.docreview.annotate', $reviewId).'">Review</a>';

        $this->get(route('capsa.team_management.show', $team->team_id))
            ->assertOk()
            ->assertSee($link, false)
            ->assertDontSee('Check format');

        $this->get(route('capsa.team_management'))
            ->assertOk()
            ->assertSee('Chapter 1 Format.pdf')
            ->assertDontSee('Check format');
    }

    /** Each paper's to-do row says what it is: a chapter, a manuscript, the diagrams, or just a paper. */
    public function test_a_paper_row_is_named_by_what_the_paper_is(): void
    {
        $capsa = $this->makeCapsaWithSections();
        [, $leader] = $this->makeTeamInSection('Team Named');
        $revised = Checklist::create(['capsa_id' => $capsa->expert_id, 'program' => 'BSIT', 'title' => 'Capstone 1 Checklist'])
            ->milestones()->create(['name' => 'Title Defense', 'position' => 0])
            ->deliverables()->create(['title' => 'Revised Proposal Manuscript', 'kind' => Deliverable::DOCUMENT, 'chapter' => 'revised', 'position' => 0])
            ->id;

        $this->makeDocumentReview($capsa, $leader, 'Ch4_Draft.pdf', 'subject_adviser', 'Chapter 4');
        $this->makeDocumentReview($capsa, $leader, 'Revised_Proposal.pdf', 'subject_adviser', null, $revised);
        $this->makeDocumentReview($capsa, $leader, 'Final Chapter 1-5.pdf', 'subject_adviser', 'Chapter 1');
        $this->makeDocumentReview($capsa, $leader, 'use_case.drawio', 'subject_adviser', null);
        $this->makeDocumentReview($capsa, $leader, 'Proposal.pdf', 'subject_adviser', null);

        $this->get(route('capsa.team_management', ['tab' => 'todo']))
            ->assertOk()
            ->assertSee('Review Chapter 4')
            ->assertSee('Chapters 1–3 · Revised_Proposal.pdf')
            ->assertSee('Chapters 1–5 · Final Chapter 1-5.pdf')
            ->assertSee('Review Manuscript')
            ->assertSee('Review Diagrams')
            ->assertSee('Review paper')
            ->assertDontSee('Review Chapter 1<', false);
    }

    /** The panel sits under Experts, and her own seat on it reads "You". */
    public function test_the_team_page_lists_the_panel_and_marks_her_own_seat(): void
    {
        $capsa = $this->makeCapsaWithSections();
        [$team] = $this->makeTeamInSection('Team Panel');
        $other = CapstoneExpert::factory()->create(['approved_status' => true, 'first_name' => 'Lara', 'last_name' => 'Santos']);

        $schedule = DefenseSchedule::create([
            'team_id' => $team->team_id,
            'defense_type' => 'Title Defense',
            'attempt' => 1,
            'schedule_date' => now()->addWeek(),
            'end_date' => now()->addWeek()->addHours(2),
            'venue' => 'Mac Lab',
        ]);
        DefensePanel::create(['defense_id' => $schedule->defense_id, 'expert_id' => $capsa->expert_id, 'panel_role' => 'Panel Chair']);
        DefensePanel::create(['defense_id' => $schedule->defense_id, 'expert_id' => $other->expert_id, 'panel_role' => null]);

        $response = $this->get(route('capsa.team_management.show', $team->team_id))->assertOk();

        $response->assertSeeInOrder(['Experts', 'Panel', 'Lara Santos']);
        // No stage label beside it; the stage is already in Progress and Next.
        $response->assertSee('id="tp-pan">Panel</h3>', false);
        $response->assertSee('<span class="you">You</span> · Panel Chair', false);
        $response->assertSeeInOrder(['Lara Santos', 'Panelist']);
    }

    /** Previous and Next walk every team in her sections, and wrap around. */
    public function test_the_team_page_links_to_the_previous_and_next_team(): void
    {
        $this->makeCapsaWithSections();
        [$alpha] = $this->makeTeamInSection('Team Alpha');
        [$beta] = $this->makeTeamInSection('Team Beta');

        $html = $this->get(route('capsa.team_management.show', $alpha->team_id))->assertOk()->getContent();

        $this->assertStringContainsString('href="'.route('capsa.team_management.show', $beta->team_id).'"', $html);
        $this->assertStringContainsString('Next team, Team Beta', $html);
        $this->assertStringContainsString('Previous team, Team Beta', $html);
    }

    public function test_a_lone_team_has_no_previous_or_next(): void
    {
        $this->makeCapsaWithSections();
        [$team] = $this->makeTeamInSection('Team Alone');

        $this->get(route('capsa.team_management.show', $team->team_id))
            ->assertOk()
            ->assertDontSee('Next team,');
    }

    /** What waits on her shows once, at the top, with the button that does it. */
    public function test_the_team_page_says_what_is_waiting_on_her(): void
    {
        $this->makeCapsaWithSections();
        [$team, , $sy] = $this->makeTeamInSection('Team Waiting');
        $first = $this->addProposal($team, $sy, 1);
        $this->addProposal($team, $sy, 2);

        $response = $this->get(route('capsa.team_management.show', $team->team_id))->assertOk();

        $response->assertSee('Waiting on you');
        $response->assertSee('Review 2 titles');
        $response->assertSee('href="'.route('capsa.team_management.title', [$team->team_id, $first->id]).'">Review titles', false);
    }

    public function test_nothing_waiting_means_no_banner(): void
    {
        $this->makeCapsaWithSections();
        [$team] = $this->makeTeamInSection('Team Quiet');

        $this->get(route('capsa.team_management.show', $team->team_id))
            ->assertOk()
            ->assertDontSee('Waiting on you');
    }

    /** Untitled: the titles open first in the card under Progress, beside Activity. */
    public function test_an_untitled_team_shows_its_titles_on_the_team_page(): void
    {
        $this->makeCapsaWithSections();
        [$team, , $sy] = $this->makeTeamInSection('Team Untitled');
        $proposal = $this->addProposal($team, $sy, 1);

        $response = $this->get(route('capsa.team_management.show', $team->team_id))->assertOk();

        $response->assertSee('data-tp-pane="tp-titles" aria-pressed="true"', false);
        $response->assertSee($proposal->title);
        $response->assertSee('data-tp-pane="tp-activity"', false);
        $response->assertDontSee('data-tp-pane="tp-papers"', false);
    }

    /** Titled: Papers and Activity share one card, Papers open first. */
    public function test_a_titled_team_shows_papers_and_activity_as_tabs(): void
    {
        $this->makeCapsaWithSections();
        [$team] = $this->makeTeamInSection('Team Titled', ['project_title' => 'A Library Kiosk']);

        $response = $this->get(route('capsa.team_management.show', $team->team_id))->assertOk();

        $response->assertSee('data-tp-pane="tp-papers" aria-pressed="true"', false);
        $response->assertSee('<div class="tp-pane" id="tp-activity" hidden>', false);
        $response->assertDontSee('data-tp-pane="tp-titles"', false);
    }

    /** Activity is read off the records: files sent, titles turned in, the day it was formed. */
    public function test_the_activity_page_lists_every_update(): void
    {
        $capsa = $this->makeCapsaWithSections();
        [$team, $leader, $sy] = $this->makeTeamInSection('Team Busy');
        $leader->update(['f_name' => 'Ana', 'l_name' => 'Reyes']);
        $this->addProposal($team, $sy, 1);
        $this->makeDocumentReview($capsa, $leader, 'Chapter 1 Format.pdf', 'subject_adviser');

        $response = $this->get(route('capsa.team_management.log', [$team->team_id, 'activity']))->assertOk();

        $response->assertSee('Ana Reyes');
        $response->assertSee('sent Chapter 1 · v1');
        $response->assertSee('turned in title #1');
        $response->assertSee('formed the team');
    }

    public function test_the_papers_page_lists_every_paper_sent_to_her(): void
    {
        $capsa = $this->makeCapsaWithSections();
        [$team, $leader] = $this->makeTeamInSection('Team Many Papers');
        foreach (range(1, 5) as $n) {
            $this->makeDocumentReview($capsa, $leader, "Chapter {$n} Format.pdf", 'subject_adviser');
        }

        $response = $this->get(route('capsa.team_management.log', [$team->team_id, 'papers']))->assertOk();

        $response->assertSee('5 papers, 5 to check');
        foreach (range(1, 5) as $n) {
            $response->assertSee("Chapter {$n} Format.pdf");
        }
    }

    public function test_a_see_all_page_only_exists_for_papers_and_activity(): void
    {
        $this->makeCapsaWithSections();
        [$team] = $this->makeTeamInSection('Team Logs');

        $this->get('/capsa/team-management/'.$team->team_id.'/members')->assertNotFound();
    }

    public function test_a_see_all_page_is_closed_to_teams_outside_her_sections(): void
    {
        $this->makeCapsaWithSections(['BSIT-4B']);
        [$team] = $this->makeTeamInSection('Team Elsewhere');

        $this->get(route('capsa.team_management.log', [$team->team_id, 'activity']))->assertNotFound();
    }

    private function makeDocumentReview(CapstoneExpert $expert, Student $student, string $fileName, string $role, ?string $chapter = 'Chapter 1', ?int $deliverableId = null): int
    {
        $fileId = DB::table('capstone_documents')->insertGetId([
            'student_id' => $student->student_id,
            'file_name' => $fileName,
            'file_path' => 'submissions/'.$fileName,
            'file_version' => 1,
            'document_status' => 'Pending',
            'chapter' => $chapter,
            'deliverable_id' => $deliverableId,
            'created_at' => now(),
        ]);

        return (int) DB::table('capstone_document_review')->insertGetId([
            'file_id' => $fileId,
            'expert_id' => $expert->expert_id,
            'role' => $role,
            'review_status' => 'To Review',
            'version' => '1',
            'drive_status' => 'pending',
        ]);
    }

    public function test_a_capsa_with_no_sections_is_told_why_the_page_is_empty(): void
    {
        $this->makeCapsaWithSections([]);
        $this->makeTeamInSection('Team Somewhere');

        $response = $this->get(route('capsa.team_management'));

        $response->assertOk();
        $response->assertSee('You have no sections assigned yet');
    }

    public function test_the_old_team_verification_url_still_works(): void
    {
        $this->makeCapsaWithSections();

        $this->get(route('capsa.verify_teams'))
            ->assertRedirect(route('capsa.team_management'));
    }

    // The CaPSA decides whether a title is too close to something already
    // done, so she needs the report the team saw - the matched abstracts and
    // what the check made of them - not a title and a percentage.
    public function test_the_title_and_similar_pages_carry_the_whole_duplication_report(): void
    {
        $this->makeCapsaWithSections();
        [$team, , $sy] = $this->makeTeamInSection('Team Checked');

        $check = DuplicationCheck::create([
            'student_s_y_id' => $sy->s_y_id,
            'document_path' => 'checks/full.pdf',
            'concepts' => ['title' => 'Solar Monitoring', 'keywords' => ['solar', 'monitoring']],
            'top_score' => 71,
            'verdict' => 'High Similarity',
            'matches' => [
                [
                    'title' => 'An Earlier Solar Monitoring Capstone',
                    'score' => 71,
                    'reason' => 'Same domain and approach.',
                    'program' => 'BSIT',
                    'academic_year' => '2023-2024',
                    'abstract' => 'This study built a solar monitoring platform for rural sites.',
                    'similarities' => 'Both monitor solar output remotely.',
                    'differences' => 'The earlier one had no mobile client.',
                    'suggestion' => 'Narrow the scope to the mobile side.',
                ],
                ['title' => 'A Fourth Match', 'score' => 40],
                ['title' => 'A Fifth Match', 'score' => 35],
                ['title' => 'A Sixth Match', 'score' => 30],
            ],
            'instructors_suggested' => [],
        ]);

        $proposal = CapstonePreProposal::create([
            'team_id' => $team->team_id,
            'duplication_check_id' => $check->id,
            'slot' => 1,
            'title' => 'Slot 1 Smart Automated Irrigation Monitoring System',
            'sdg_goals' => 'SDG 4',
            'research_agenda' => ['Applications and Systems Development'],
            'problem_statement' => 'p', 'proposed_solution' => 's', 'scope' => 'sc', 'limitations' => 'l',
            'sdm' => 'Agile',
            'approved_status' => false,
            'submitted_at' => now(),
        ]);

        // The title page ranks every match - all of them, not the first three.
        $report = $this->get(route('capsa.team_management.title', [$team->team_id, $proposal->id]));

        $report->assertOk();
        $report->assertSee('An Earlier Solar Monitoring Capstone', false);
        $report->assertSee('A Sixth Match', false);
        $report->assertSee('2023-2024', false);

        // And each match has a page with the abstract and the check's notes,
        // not just a title and a percentage.
        $match = $this->get(route('capsa.team_management.similar', [$team->team_id, $proposal->id, 1]));

        $match->assertOk();
        $match->assertSee('This study built a solar monitoring platform', false);
        $match->assertSee('Both monitor solar output remotely.', false);
        $match->assertSee('The earlier one had no mobile client.', false);
        $match->assertSee('Narrow the scope to the mobile side.', false);
    }
}
