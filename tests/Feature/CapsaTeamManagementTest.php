<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\CapstonePreProposal;
use App\Models\Checklist;
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

        $response = $this->get(route('capsa.team_management'));

        $response->assertOk();
        $response->assertSee('2/4');
        $response->assertSee('1 overdue');
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

        $response = $this->get(route('capsa.team_management'));

        $response->assertOk();
        // The filled seat names the role the team engaged them AS, not every
        // role they hold; the two empty seats say which role is still open.
        $response->assertSee('Consultant: '.$expert->name);
        $response->assertSee('No adviser yet');
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

        // The summary names the waiting work.
        $this->assertStringContainsString('Waiting on you', $html);
        $this->assertStringContainsString('1 title to review', $html);

        // The old strip and its red badges are gone with it.
        $this->assertStringNotContainsString('tm-toolbar', $html);
        $this->assertStringNotContainsString('tab-count', $html);
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
     * Papers reach this tab only under the subject_adviser role - her CaPSA
     * hat. What she reviews as somebody's adviser is a different job and
     * stays in My Expert Duties -> Document Review.
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

    private function makeDocumentReview(CapstoneExpert $expert, Student $student, string $fileName, string $role): int
    {
        $fileId = DB::table('capstone_documents')->insertGetId([
            'student_id' => $student->student_id,
            'file_name' => $fileName,
            'file_path' => 'submissions/'.$fileName,
            'file_version' => 1,
            'document_status' => 'Pending',
            'chapter' => 'Chapter 1',
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
