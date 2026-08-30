<?php

namespace Tests\Feature;

use App\Models\ArchivedDocument;
use App\Models\CapstonePreProposal;
use App\Models\DuplicationCheck;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Services\OllamaSSHClient;
use App\Services\SimilarityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class PreProposalControllerTest extends TestCase
{
    use RefreshDatabase;

    private const TITLE = 'Smart Automated Irrigation Monitoring System Using Sensor Networks For Sustainable Agriculture Development';

    private function makeLeaderWithTeam(): array
    {
        $student = Student::create(['email' => 'pp.leader@my.cspc.edu.ph', 'f_name' => 'PP', 'l_name' => 'Leader']);
        $team = Team::create(['team_name' => 'PP Team', 'leader_id' => $student->student_id]);
        StudentSchoolYear::create(['student_id' => $student->student_id, 'team_id' => $team->team_id]);

        return [$student, $team];
    }

    private function addMember(Team $team): Student
    {
        $member = Student::create(['email' => 'pp.member@my.cspc.edu.ph', 'f_name' => 'PP', 'l_name' => 'Member']);
        StudentSchoolYear::create(['student_id' => $member->student_id, 'team_id' => $team->team_id]);

        return $member;
    }

    private function mockSimilarityPipeline(int $topScore = 20): void
    {
        $archiveTeam = Team::create(['team_name' => 'Archive Source Team']);
        $archived = ArchivedDocument::create([
            'team_id' => $archiveTeam->team_id,
            'title' => 'An Existing Archived Capstone',
            'abstract' => 'Some abstract.',
            'academic_year' => '2024-2025',
            'program' => 'BSIT',
            'file_path' => 'archives/existing.pdf',
        ]);

        $this->mock(OllamaSSHClient::class, function ($mock) {
            $mock->shouldReceive('warmup')->andReturn(null);
        });

        $this->mock(SimilarityService::class, function ($mock) use ($archived, $topScore) {
            $mock->shouldReceive('extractPdfText')->andReturn('Some pre-proposal text.');
            $mock->shouldReceive('isPreProposal')->andReturn(['is_pre_proposal' => true, 'reason' => '']);
            $mock->shouldReceive('extractKeyConcepts')->andReturn([
                'title' => self::TITLE,
                'main_topic' => 'Testing',
                'domain' => 'Web System',
                'keywords' => ['test'],
                'problem_statement' => 'A problem.',
                'proposed_solution' => 'A solution.',
                'scope' => 'A scope.',
                'technologies' => ['Laravel'],
                'target_users' => 'Students',
            ]);
            $mock->shouldReceive('findSimilarDocuments')->andReturn([
                ['document' => $archived, 'score' => $topScore, 'reason' => 'Somewhat similar.', 'is_relevant' => true, 'matched_on' => []],
            ]);
            $mock->shouldReceive('suggestInstructors')->andReturn([]);
            $mock->shouldReceive('extractHighlightWords')->andReturn(['test']);
        });
    }

    private function payload(int $checkId): array
    {
        return [
            'title' => self::TITLE,
            'sdg_goals' => 'SDG 4 – Quality Education',
            'research_agenda' => ['Applications and Systems Development'],
            'problem_statement' => 'A problem.',
            'proposed_solution' => 'A solution.',
            'scope' => 'A scope.',
            'limitations' => 'A limitation.',
            'sdm' => 'Agile Development Methodology',
            'duplication_check_id' => $checkId,
        ];
    }

    public function test_run_check_persists_a_duplication_check_and_returns_extracted_concepts(): void
    {
        [$student, $team] = $this->makeLeaderWithTeam();
        $this->mockSimilarityPipeline(20);
        $this->actingAs($student, 'web');

        $response = $this->post(route('student.pre-proposal.check'), [
            'document' => UploadedFile::fake()->create('proposal.pdf', 10, 'application/pdf'),
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true, 'verdict' => 'Passed']);
        $this->assertNotNull($response->json('duplication_check_id'));
        $this->assertSame(self::TITLE, $response->json('concepts.title'));

        $sy = StudentSchoolYear::where('student_id', $student->student_id)->first();
        $this->assertDatabaseHas('duplication_checks', ['student_s_y_id' => $sy->s_y_id, 'verdict' => 'Passed']);
    }

    public function test_store_rejects_without_a_linked_duplication_check(): void
    {
        [$student, $team] = $this->makeLeaderWithTeam();
        $this->actingAs($student, 'web');

        $response = $this->post(route('student.pre-proposal.store'), $this->payload(9999));

        $response->assertSessionHas('error');
        $this->assertDatabaseMissing('capstone_pre_proposals', ['team_id' => $team->team_id]);
    }

    private function checkAndStore(string $fileName): void
    {
        $checkId = $this->post(route('student.pre-proposal.check'), [
            'document' => UploadedFile::fake()->create($fileName, 10, 'application/pdf'),
        ])->json('duplication_check_id');

        $this->post(route('student.pre-proposal.store'), $this->payload($checkId));
    }

    public function test_store_auto_assigns_slots_in_order_and_only_marks_complete_once_all_three_are_in(): void
    {
        [$student, $team] = $this->makeLeaderWithTeam();
        $this->mockSimilarityPipeline();
        $this->actingAs($student, 'web');

        $this->checkAndStore('p1.pdf');
        $this->assertSame('on-going', $team->fresh()->team_status);
        $this->assertDatabaseHas('capstone_pre_proposals', ['team_id' => $team->team_id, 'slot' => 1, 'duplication_status' => 'free']);

        $this->checkAndStore('p2.pdf');
        $this->assertSame('on-going', $team->fresh()->team_status);
        $this->assertDatabaseHas('capstone_pre_proposals', ['team_id' => $team->team_id, 'slot' => 2]);

        // Third one auto-assigns slot 3 and flips team_status to complete.
        $this->checkAndStore('p3.pdf');
        $this->assertDatabaseHas('capstone_pre_proposals', ['team_id' => $team->team_id, 'slot' => 3]);
        $this->assertSame('complete', $team->fresh()->team_status);
        $this->assertSame(3, $team->preProposals()->count());
    }

    public function test_store_rejects_a_fourth_proposal_once_three_are_awaiting_review(): void
    {
        [$student, $team] = $this->makeLeaderWithTeam();
        $this->mockSimilarityPipeline();
        $this->actingAs($student, 'web');

        $this->checkAndStore('p1.pdf');
        $this->checkAndStore('p2.pdf');
        $this->checkAndStore('p3.pdf');

        $checkId = $this->post(route('student.pre-proposal.check'), [
            'document' => UploadedFile::fake()->create('p4.pdf', 10, 'application/pdf'),
        ])->json('duplication_check_id');
        $response = $this->post(route('student.pre-proposal.store'), $this->payload($checkId));

        $response->assertSessionHas('error');
        $this->assertSame(3, $team->preProposals()->count());
    }

    public function test_store_replaces_a_rejected_proposal_instead_of_adding_a_fourth(): void
    {
        [$student, $team] = $this->makeLeaderWithTeam();
        $this->mockSimilarityPipeline();
        $this->actingAs($student, 'web');

        $this->checkAndStore('p1.pdf');
        $this->checkAndStore('p2.pdf');
        $this->checkAndStore('p3.pdf');

        $team->preProposals()->where('slot', 2)->first()->update(['rejection_reason' => 'Too similar to an archived project.']);

        $checkId = $this->post(route('student.pre-proposal.check'), [
            'document' => UploadedFile::fake()->create('p2-revised.pdf', 10, 'application/pdf'),
        ])->json('duplication_check_id');
        $response = $this->post(route('student.pre-proposal.store'), $this->payload($checkId));

        $response->assertSessionHas('status');
        $this->assertSame(3, $team->preProposals()->count());
        $slot2 = $team->preProposals()->where('slot', 2)->first();
        $this->assertNull($slot2->rejection_reason);
        $this->assertSame($checkId, $slot2->duplication_check_id);
    }

    public function test_team_update_still_rejects_complete_without_three_proposals(): void
    {
        [$student, $team] = $this->makeLeaderWithTeam();
        $this->actingAs($student, 'web');

        $response = $this->put(route('team.update', $team->team_id), [
            'team_name' => 'PP Team',
            'team_status' => 'complete',
        ]);

        $response->assertSessionHas('error');
        $this->assertSame('on-going', $team->fresh()->team_status);
    }

    // Direct regression for a user report of seeing another, unrelated
    // team's pre-proposals while logged into a different student account.
    // Goes through the real HTTP + middleware pipeline (not a direct
    // controller call) so it also exercises route-level scoping and the
    // no-cache headers that guard against a stale back-button/bfcache page
    // showing a previous account's content after switching users.
    public function test_a_students_pre_proposals_are_not_visible_to_an_unrelated_student(): void
    {
        [$studentA, $teamA] = $this->makeLeaderWithTeam();
        $syA = StudentSchoolYear::where('student_id', $studentA->student_id)->first();
        $checkA = DuplicationCheck::create([
            'student_s_y_id' => $syA->s_y_id, 'document_path' => 'a.pdf', 'concepts' => [],
            'top_score' => 10, 'verdict' => 'Passed', 'matches' => [], 'instructors_suggested' => [],
        ]);
        CapstonePreProposal::create([
            'team_id' => $teamA->team_id, 'duplication_check_id' => $checkA->id, 'slot' => 1,
            'title' => 'CONFIDENTIAL TEAM A ONLY Automated Sensor Based Crop Yield Prediction System',
            'sdg_goals' => 'SDG 2', 'research_agenda' => ['x'], 'problem_statement' => 'p',
            'proposed_solution' => 's', 'scope' => 'sc', 'limitations' => 'l', 'sdm' => 'Agile',
        ]);

        $studentB = Student::create(['email' => 'unrelated@my.cspc.edu.ph', 'f_name' => 'Unrelated', 'l_name' => 'Student']);
        $teamB = Team::create(['team_name' => 'Unrelated Team', 'leader_id' => $studentB->student_id]);
        StudentSchoolYear::create(['student_id' => $studentB->student_id, 'team_id' => $teamB->team_id]);

        $this->actingAs($studentB, 'web');
        $response = $this->get(route('student.duplication'));

        $response->assertOk();
        $response->assertDontSee('CONFIDENTIAL TEAM A ONLY');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    // The duplication checker and pre-proposal submission are shared team
    // tools, not leader-exclusive - any member sees the checker and can run
    // checks / submit proposals. (Choosing the final title between multiple
    // approved proposals is the one action that stays leader-only - see
    // test_edit_page_hides_the_choose_title_button_from_non_leader_members.)
    public function test_edit_page_shows_the_checker_to_every_team_member(): void
    {
        [$leader, $team] = $this->makeLeaderWithTeam();
        $member = $this->addMember($team);

        $this->actingAs($member, 'web');
        $response = $this->get(route('student.duplication'));

        $response->assertOk();
        $response->assertSee('id="upload-zone"', false);
        $response->assertSee('id="pp-form-modal-overlay"', false);
    }

    public function test_a_non_leader_member_can_run_a_check_and_submit_a_proposal(): void
    {
        [$leader, $team] = $this->makeLeaderWithTeam();
        $member = $this->addMember($team);
        $this->mockSimilarityPipeline();

        $this->actingAs($member, 'web');

        $checkId = $this->post(route('student.pre-proposal.check'), [
            'document' => UploadedFile::fake()->create('member-upload.pdf', 10, 'application/pdf'),
        ])->json('duplication_check_id');

        $this->assertNotNull($checkId);

        $response = $this->post(route('student.pre-proposal.store'), $this->payload($checkId));

        $response->assertSessionHas('status');
        $this->assertDatabaseHas('capstone_pre_proposals', ['team_id' => $team->team_id, 'slot' => 1, 'title' => self::TITLE]);
    }

    public function test_edit_page_hides_the_choose_title_button_from_non_leader_members(): void
    {
        [$leader, $team] = $this->makeLeaderWithTeam();
        $member = $this->addMember($team);
        $sy = StudentSchoolYear::where('student_id', $leader->student_id)->first();

        foreach ([1, 2] as $slot) {
            $check = DuplicationCheck::create([
                'student_s_y_id' => $sy->s_y_id, 'document_path' => "x{$slot}.pdf", 'concepts' => [],
                'top_score' => 20, 'verdict' => 'Passed', 'matches' => [], 'instructors_suggested' => [],
            ]);
            CapstonePreProposal::create([
                'team_id' => $team->team_id, 'duplication_check_id' => $check->id, 'slot' => $slot,
                'title' => self::TITLE, 'sdg_goals' => 'SDG 4', 'research_agenda' => ['x'],
                'problem_statement' => 'p', 'proposed_solution' => 's', 'scope' => 'sc', 'limitations' => 'l',
                'sdm' => 'Agile', 'approved_status' => true,
            ]);
        }

        $this->actingAs($member, 'web');
        $response = $this->get(route('student.duplication'));

        $response->assertOk();
        $response->assertDontSee('Choose This One');
        $response->assertSee('your team leader needs to pick');

        $this->actingAs($leader, 'web');
        $response = $this->get(route('student.duplication'));

        $response->assertOk();
        $response->assertSee('Choose This One');
    }

    public function test_a_pending_proposal_can_be_removed_and_frees_its_slot(): void
    {
        [$student, $team] = $this->makeLeaderWithTeam();
        $sy = StudentSchoolYear::where('student_id', $student->student_id)->first();

        $check = DuplicationCheck::create([
            'student_s_y_id' => $sy->s_y_id, 'document_path' => 'x.pdf', 'concepts' => [],
            'top_score' => 20, 'verdict' => 'Passed', 'matches' => [], 'instructors_suggested' => [],
        ]);
        $proposal = CapstonePreProposal::create([
            'team_id' => $team->team_id, 'duplication_check_id' => $check->id, 'slot' => 1,
            'title' => self::TITLE, 'sdg_goals' => 'SDG 4', 'research_agenda' => ['x'],
            'problem_statement' => 'p', 'proposed_solution' => 's', 'scope' => 'sc', 'limitations' => 'l',
            'sdm' => 'Agile', 'approved_status' => false,
        ]);
        $team->update(['team_status' => 'complete']);

        $this->actingAs($student, 'web');
        $response = $this->delete(route('student.pre-proposal.destroy', $proposal->id));

        $response->assertSessionHas('status');
        $this->assertDatabaseMissing('capstone_pre_proposals', ['id' => $proposal->id]);
        $this->assertSame('on-going', $team->fresh()->team_status);
    }

    public function test_an_approved_proposal_cannot_be_removed(): void
    {
        [$student, $team] = $this->makeLeaderWithTeam();
        $sy = StudentSchoolYear::where('student_id', $student->student_id)->first();

        $check = DuplicationCheck::create([
            'student_s_y_id' => $sy->s_y_id, 'document_path' => 'x.pdf', 'concepts' => [],
            'top_score' => 20, 'verdict' => 'Passed', 'matches' => [], 'instructors_suggested' => [],
        ]);
        $proposal = CapstonePreProposal::create([
            'team_id' => $team->team_id, 'duplication_check_id' => $check->id, 'slot' => 1,
            'title' => self::TITLE, 'sdg_goals' => 'SDG 4', 'research_agenda' => ['x'],
            'problem_statement' => 'p', 'proposed_solution' => 's', 'scope' => 'sc', 'limitations' => 'l',
            'sdm' => 'Agile', 'approved_status' => true,
        ]);

        $this->actingAs($student, 'web');
        $response = $this->delete(route('student.pre-proposal.destroy', $proposal->id));

        $response->assertSessionHas('error');
        $this->assertDatabaseHas('capstone_pre_proposals', ['id' => $proposal->id]);
    }

    public function test_a_non_member_cannot_remove_another_teams_proposal(): void
    {
        [$student, $team] = $this->makeLeaderWithTeam();
        $sy = StudentSchoolYear::where('student_id', $student->student_id)->first();

        $check = DuplicationCheck::create([
            'student_s_y_id' => $sy->s_y_id, 'document_path' => 'x.pdf', 'concepts' => [],
            'top_score' => 20, 'verdict' => 'Passed', 'matches' => [], 'instructors_suggested' => [],
        ]);
        $proposal = CapstonePreProposal::create([
            'team_id' => $team->team_id, 'duplication_check_id' => $check->id, 'slot' => 1,
            'title' => self::TITLE, 'sdg_goals' => 'SDG 4', 'research_agenda' => ['x'],
            'problem_statement' => 'p', 'proposed_solution' => 's', 'scope' => 'sc', 'limitations' => 'l',
            'sdm' => 'Agile', 'approved_status' => false,
        ]);

        $outsider = Student::create(['email' => 'outsider@my.cspc.edu.ph', 'f_name' => 'Out', 'l_name' => 'Sider']);
        $outsiderTeam = Team::create(['team_name' => 'Outsider Team', 'leader_id' => $outsider->student_id]);
        StudentSchoolYear::create(['student_id' => $outsider->student_id, 'team_id' => $outsiderTeam->team_id]);

        $this->actingAs($outsider, 'web');
        $response = $this->delete(route('student.pre-proposal.destroy', $proposal->id));

        $response->assertForbidden();
        $this->assertDatabaseHas('capstone_pre_proposals', ['id' => $proposal->id]);
    }

    public function test_choose_requires_the_proposal_to_be_approved(): void
    {
        [$student, $team] = $this->makeLeaderWithTeam();
        $sy = StudentSchoolYear::where('student_id', $student->student_id)->first();

        $check = DuplicationCheck::create([
            'student_s_y_id' => $sy->s_y_id, 'document_path' => 'x.pdf', 'concepts' => [],
            'top_score' => 20, 'verdict' => 'Passed', 'matches' => [], 'instructors_suggested' => [],
        ]);
        $proposal = CapstonePreProposal::create([
            'team_id' => $team->team_id, 'duplication_check_id' => $check->id, 'slot' => 1,
            'title' => self::TITLE, 'sdg_goals' => 'SDG 4', 'research_agenda' => ['x'],
            'problem_statement' => 'p', 'proposed_solution' => 's', 'scope' => 'sc', 'limitations' => 'l',
            'sdm' => 'Agile', 'approved_status' => false,
        ]);

        $this->actingAs($student, 'web');
        $response = $this->post(route('student.pre-proposal.choose', $proposal->id));

        $response->assertSessionHas('error');
        $this->assertNull($team->fresh()->selected_pre_proposal_id);
    }

    // Regression: the duplication report only ever showed transiently in JS
    // right after the check ran - a reload (after saving, or just revisiting
    // the page) lost it entirely, leaving only the CAPSA approval status
    // visible with no way to see the actual duplication result.
    public function test_edit_page_shows_the_persisted_duplication_report_for_a_saved_proposal(): void
    {
        [$student, $team] = $this->makeLeaderWithTeam();
        $sy = StudentSchoolYear::where('student_id', $student->student_id)->first();

        $check = DuplicationCheck::create([
            'student_s_y_id' => $sy->s_y_id, 'document_path' => 'x.pdf', 'concepts' => [],
            'top_score' => 42, 'verdict' => 'Moderate Similarity',
            'matches' => [['title' => 'Some Archived Thing', 'score' => 42]], 'instructors_suggested' => [],
        ]);
        CapstonePreProposal::create([
            'team_id' => $team->team_id, 'duplication_check_id' => $check->id, 'slot' => 1,
            'title' => self::TITLE, 'sdg_goals' => 'SDG 4', 'research_agenda' => ['x'],
            'problem_statement' => 'p', 'proposed_solution' => 's', 'scope' => 'sc', 'limitations' => 'l',
            'sdm' => 'Agile', 'approved_status' => false,
        ]);

        $this->actingAs($student, 'web');
        $response = $this->get(route('student.duplication'));

        $response->assertOk();
        $response->assertSee('Moderate Similarity');
        $response->assertSee('42%');
    }

    // Regression: consolidating the standalone duplication checker into the
    // pre-proposal page dropped the original per-match breakdown (title,
    // score, AI reason, abstract) down to a bare summary count. The full
    // breakdown must survive the merge, not just a verdict badge.
    public function test_edit_page_shows_full_match_breakdown_not_just_a_summary(): void
    {
        [$student, $team] = $this->makeLeaderWithTeam();
        $sy = StudentSchoolYear::where('student_id', $student->student_id)->first();

        $check = DuplicationCheck::create([
            'student_s_y_id' => $sy->s_y_id, 'document_path' => 'x.pdf', 'concepts' => [],
            'top_score' => 68, 'verdict' => 'High Similarity',
            'matches' => [
                ['title' => 'Flood Monitoring System for Rural Barangays', 'abstract' => 'An IoT-based early warning study.', 'academic_year' => '2023-2024', 'program' => 'BSIT', 'score' => 68, 'reason' => 'Both target flood detection using IoT sensors.', 'is_relevant' => true],
            ],
            'instructors_suggested' => [
                ['id' => 1, 'name' => 'Ana Reyes, MIT', 'expertise' => 'IoT, Embedded Systems'],
            ],
        ]);
        CapstonePreProposal::create([
            'team_id' => $team->team_id, 'duplication_check_id' => $check->id, 'slot' => 1,
            'title' => self::TITLE, 'sdg_goals' => 'SDG 4', 'research_agenda' => ['x'],
            'problem_statement' => 'p', 'proposed_solution' => 's', 'scope' => 'sc', 'limitations' => 'l',
            'sdm' => 'Agile', 'approved_status' => false,
        ]);

        $this->actingAs($student, 'web');
        $response = $this->get(route('student.duplication'));

        $response->assertOk();
        $response->assertSee('Flood Monitoring System for Rural Barangays');
        $response->assertSee('Both target flood detection using IoT sensors.');
        $response->assertSee('2023-2024');
        $response->assertSee('Ana Reyes, MIT');
    }
}
