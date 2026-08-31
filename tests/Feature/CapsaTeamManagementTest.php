<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\CapstonePreProposal;
use App\Models\DuplicationCheck;
use App\Models\ExpertRole;
use App\Models\Milestone;
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

        $response = $this->get(route('admin.team_management'));

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

        $response = $this->get(route('admin.team_management'));

        $response->assertOk();
        $response->assertSee($stalled->team_name);
    }

    public function test_it_shows_milestone_progress_for_a_team(): void
    {
        $this->makeCapsaWithSections();
        [$team] = $this->makeTeamInSection('Team Progress');

        foreach (['completed', 'completed', 'pending', 'overdue'] as $i => $status) {
            Milestone::create([
                'team_id' => $team->team_id,
                'title' => "Milestone {$i}",
                'status' => $status,
                'order' => $i,
            ]);
        }

        $response = $this->get(route('admin.team_management'));

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

        $response = $this->get(route('admin.team_management'));

        $response->assertOk();
        // The filled role carries the expert's name as its tooltip; the two
        // unfilled ones do not.
        $response->assertSee('tm-role tm-role-on', false);
        $response->assertSee($expert->name);
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

        $response = $this->get(route('admin.team_management', ['tab' => 'verification']));

        $response->assertOk();
        $response->assertSee('Team choosing');
        $response->assertSee('they pick which one stands before you can verify');
    }

    public function test_the_pre_proposals_tab_counts_titles_awaiting_review(): void
    {
        $this->makeCapsaWithSections();
        [$team, , $sy] = $this->makeTeamInSection('Team Titles');

        $this->addProposal($team, $sy, 1);
        $this->addProposal($team, $sy, 2);
        $this->addProposal($team, $sy, 3, approved: true);

        $response = $this->get(route('admin.team_management', ['tab' => 'pre_proposals']));

        $response->assertOk();
        $response->assertSee('2 to review');
    }

    /**
     * Papers reach this tab only under the subject_adviser role - her CaPSA
     * hat. What she reviews as somebody's adviser is a different job and
     * stays in My Expert Duties -> Document Review.
     */
    public function test_the_papers_tab_shows_only_papers_sent_to_her_as_subject_adviser(): void
    {
        $capsa = $this->makeCapsaWithSections();
        [$team, $leader] = $this->makeTeamInSection('Team Papers');

        $formatCheck = $this->makeDocumentReview($capsa, $leader, 'Chapter 1 Format.pdf', 'subject_adviser');
        $adviserWork = $this->makeDocumentReview($capsa, $leader, 'Chapter 2 Content.pdf', 'adviser');

        $response = $this->get(route('admin.team_management', ['tab' => 'papers']));

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

        $response = $this->get(route('admin.team_management'));

        $response->assertOk();
        $response->assertSee('You have no sections assigned yet');
    }

    public function test_the_old_team_verification_url_still_works(): void
    {
        $this->makeCapsaWithSections();

        $this->get(route('admin.verify_teams'))
            ->assertRedirect(route('admin.team_management', ['tab' => 'verification']));
    }
}
