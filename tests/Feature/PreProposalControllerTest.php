<?php

namespace Tests\Feature;

use App\Http\Controllers\PreProposalController;
use App\Models\ArchivedDocument;
use App\Models\CapstoneExpert;
use App\Models\CapstonePreProposal;
use App\Models\DuplicationCheck;
use App\Models\Role;
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

    private function payload(int $checkId, ?string $title = null): array
    {
        return [
            'title' => $title ?? self::TITLE,
            'sdg_goals' => 'SDG 4 – Quality Education',
            'research_agenda' => ['Applications and Systems Development'],
            'problem_statement' => 'A problem.',
            'proposed_solution' => 'A solution.',
            'scope' => 'A scope.',
            'limitations' => 'A limitation.',
            'sdm' => 'Agile Development Methodology',
            'clients' => 'Barangay Hall',
            'client_address' => 'Naga City',
            'client_representative' => 'Ana Cruz',
            'client_position' => 'Secretary',
            'tags' => ['Web-based', 'Management System'],
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

    /**
     * Each saved proposal is a different one.
     *
     * These used to send self::TITLE every time, so a team saving four
     * proposals saved the same title four times - which the endpoint now
     * refuses, because turning in one title four times is not the choice the
     * CaPSA/TSA is being offered. The first word varies instead, which keeps the
     * 11-12 meaningful words the title rule wants.
     */
    private function checkAndStore(string $fileName): void
    {
        $checkId = $this->post(route('student.pre-proposal.check'), [
            'document' => UploadedFile::fake()->create($fileName, 10, 'application/pdf'),
        ])->json('duplication_check_id');

        $this->post(route('student.pre-proposal.store'), $this->payload($checkId, $this->distinctTitle()));
    }

    private int $titlesSaved = 0;

    private function distinctTitle(): string
    {
        $opener = ['Smart', 'Adaptive', 'Predictive', 'Integrated', 'Distributed', 'Autonomous', 'Networked'];
        $word = $opener[$this->titlesSaved++ % count($opener)];

        return $word.' '.mb_substr(self::TITLE, mb_strlen('Smart '));
    }

    /**
     * Keep exactly as many drafts as submitting currently requires.
     *
     * Written against the constant rather than a literal count: these tests
     * all seeded three, so raising the minimum to four failed seven of them
     * for no reason other than the number being spelled out in each one.
     */
    private function saveTheMinimum(): void
    {
        foreach (range(1, PreProposalController::RECOMMENDED_PROPOSALS) as $n) {
            $this->checkAndStore("p{$n}.pdf");
        }
    }

    // Saving keeps a draft. It used to stamp submitted_at and, on the third
    // one, silently flip the team to complete - so a team was in front of
    // their CAPSA before they had said they were ready.
    public function test_saving_a_proposal_keeps_it_as_a_draft(): void
    {
        [$student, $team] = $this->makeLeaderWithTeam();
        $this->mockSimilarityPipeline();
        $this->actingAs($student, 'web');

        $this->saveTheMinimum();

        $this->assertSame('Active', $team->fresh()->team_status);
        $this->assertSame(PreProposalController::RECOMMENDED_PROPOSALS, $team->preProposals()->count());
        $this->assertSame(0, $team->preProposals()->whereNotNull('submitted_at')->count());
    }

    public function test_slots_are_assigned_in_order(): void
    {
        [$student, $team] = $this->makeLeaderWithTeam();
        $this->mockSimilarityPipeline();
        $this->actingAs($student, 'web');

        $this->checkAndStore('p1.pdf');
        $this->assertDatabaseHas('capstone_pre_proposals', ['team_id' => $team->team_id, 'slot' => 1, 'duplication_status' => 'free']);

        $this->checkAndStore('p2.pdf');
        $this->assertDatabaseHas('capstone_pre_proposals', ['team_id' => $team->team_id, 'slot' => 2]);
    }

    // Three is the minimum, not a cap - a team may keep as many candidate
    // titles as they like.
    public function test_a_team_may_save_more_than_the_minimum(): void
    {
        [$student, $team] = $this->makeLeaderWithTeam();
        $this->mockSimilarityPipeline();
        $this->actingAs($student, 'web');

        $this->saveTheMinimum();
        $this->checkAndStore('one-more.pdf');

        $beyond = PreProposalController::RECOMMENDED_PROPOSALS + 1;

        $this->assertSame($beyond, $team->preProposals()->count());
        $this->assertDatabaseHas('capstone_pre_proposals', ['team_id' => $team->team_id, 'slot' => $beyond]);
    }

    // A rejected title used to be written over by the next save. It now stays
    // put as a record, and the replacement is saved alongside it.
    public function test_a_rejected_proposal_is_kept_rather_than_overwritten(): void
    {
        [$student, $team] = $this->makeLeaderWithTeam();
        $this->mockSimilarityPipeline();
        $this->actingAs($student, 'web');

        $this->saveTheMinimum();

        $team->preProposals()->where('slot', 2)->first()->update(['rejection_reason' => 'Too similar to an archived project.']);

        $this->checkAndStore('p2-revised.pdf');

        $this->assertSame(
            PreProposalController::RECOMMENDED_PROPOSALS + 1,
            $team->preProposals()->count(),
            'The rejected title should be kept and the replacement saved beside it.',
        );
        $this->assertSame(
            'Too similar to an archived project.',
            $team->preProposals()->where('slot', 2)->first()->rejection_reason,
        );
    }

    /**
     * Fewer than the suggested four goes through.
     *
     * It used to be refused, which pushed a team with two titles they believed
     * in into writing two they did not, just to unlock the button - and the
     * CaPSA/TSA then had four to read instead of two. The page asks once before
     * sending fewer; the endpoint does not stand in the way.
     */
    public function test_submitting_fewer_than_the_suggested_number_is_allowed(): void
    {
        [$student, $team] = $this->makeLeaderWithTeam();
        $this->mockSimilarityPipeline();
        $this->actingAs($student, 'web');

        $this->checkAndStore('p1.pdf');
        $this->checkAndStore('p2.pdf');

        $this->patch(route('student.pre-proposal.submit'), ['status' => 'submitted'])
            ->assertSessionMissing('error');

        $this->assertSame(2, $team->preProposals()->whereNotNull('submitted_at')->count());
    }

    /** One is enough. Nothing at all is not. */
    public function test_a_single_title_can_be_submitted(): void
    {
        [$student, $team] = $this->makeLeaderWithTeam();
        $this->mockSimilarityPipeline();
        $this->actingAs($student, 'web');

        $this->checkAndStore('p1.pdf');

        $this->patch(route('student.pre-proposal.submit'), ['status' => 'submitted'])
            ->assertSessionMissing('error');

        $this->assertSame(1, $team->preProposals()->whereNotNull('submitted_at')->count());
    }

    /**
     * A team can leave a title out without losing it.
     *
     * Turning in used to stamp every draft, so the only way to keep one back
     * was to delete it - and deleting took its duplication report with it.
     */
    public function test_only_the_chosen_titles_are_turned_in(): void
    {
        [$student, $team] = $this->makeLeaderWithTeam();
        $this->mockSimilarityPipeline();
        $this->actingAs($student, 'web');

        $this->checkAndStore('p1.pdf');
        $this->checkAndStore('p2.pdf');
        $this->checkAndStore('p3.pdf');

        $ids = $team->preProposals()->orderBy('id')->pluck('id');

        $this->patch(route('student.pre-proposal.submit'), [
            'status' => 'submitted',
            'choose' => 1,
            'ids' => [$ids[0], $ids[2]],
        ])->assertSessionMissing('error');

        $this->assertSame(2, $team->preProposals()->whereNotNull('submitted_at')->count());

        // The one left out is still there, still a draft.
        $held = CapstonePreProposal::find($ids[1]);
        $this->assertNotNull($held);
        $this->assertNull($held->submitted_at);
    }

    /** Ticking nothing is not the same as sending no selection at all. */
    public function test_choosing_no_titles_is_refused_rather_than_submitting_all(): void
    {
        [$student, $team] = $this->makeLeaderWithTeam();
        $this->mockSimilarityPipeline();
        $this->actingAs($student, 'web');

        $this->checkAndStore('p1.pdf');
        $this->checkAndStore('p2.pdf');

        $this->patch(route('student.pre-proposal.submit'), [
            'status' => 'submitted',
            'choose' => 1,
        ])->assertSessionHas('error');

        $this->assertSame(0, $team->preProposals()->whereNotNull('submitted_at')->count());
    }

    public function test_submitting_nothing_is_refused(): void
    {
        [$student, $team] = $this->makeLeaderWithTeam();
        $this->actingAs($student, 'web');

        $this->patch(route('student.pre-proposal.submit'), ['status' => 'submitted'])
            ->assertSessionHas('error');

        $this->assertSame(0, $team->preProposals()->count());
    }

    // Submitting is the one act that hands the team to their CAPSA. It is
    // recorded on the pre-proposals, NOT by moving team_status - that column
    // answers "is this team still recruiting?" and nothing else now.
    public function test_submitting_turns_every_title_in(): void
    {
        [$student, $team] = $this->makeLeaderWithTeam();
        $this->mockSimilarityPipeline();
        $this->actingAs($student, 'web');

        $this->saveTheMinimum();

        $this->patch(route('student.pre-proposal.submit'), ['status' => 'submitted'])->assertSessionHas('status');

        $this->assertSame(PreProposalController::RECOMMENDED_PROPOSALS, $team->preProposals()->whereNotNull('submitted_at')->count());
        $this->assertTrue($team->fresh()->hasSubmittedPreProposals());
        $this->assertSame('Active', $team->fresh()->team_status, 'turning in is not a recruitment change');
    }

    public function test_only_the_leader_can_submit_for_checking(): void
    {
        [, $team] = $this->makeLeaderWithTeam();
        $this->mockSimilarityPipeline();

        $member = Student::factory()->withCompleteProfile()->create(['email' => 'member.pp@my.cspc.edu.ph']);
        StudentSchoolYear::create(['student_id' => $member->student_id, 'team_id' => $team->team_id]);

        $this->actingAs($member, 'web');
        $this->saveTheMinimum();

        $this->patch(route('student.pre-proposal.submit'), ['status' => 'submitted'])->assertSessionHas('error');
        $this->assertSame(0, $team->preProposals()->whereNotNull('submitted_at')->count());
    }

    public function test_a_team_can_unsubmit_before_the_capsa_acts(): void
    {
        [$student, $team] = $this->makeLeaderWithTeam();
        $this->mockSimilarityPipeline();
        $this->actingAs($student, 'web');

        $this->saveTheMinimum();
        $this->patch(route('student.pre-proposal.submit'), ['status' => 'submitted']);

        $this->patch(route('student.pre-proposal.submit'), ['status' => 'draft'])->assertSessionHas('status');

        $this->assertSame(0, $team->preProposals()->whereNotNull('submitted_at')->count());
        $this->assertSame('Active', $team->fresh()->team_status);
    }

    // Pulling the set back mid-review would change what the CAPSA is part-way
    // through reading, so her decision stands.
    public function test_a_team_cannot_unsubmit_once_the_capsa_has_acted(): void
    {
        [$student, $team] = $this->makeLeaderWithTeam();
        $this->mockSimilarityPipeline();
        $this->actingAs($student, 'web');

        $this->saveTheMinimum();
        $this->patch(route('student.pre-proposal.submit'), ['status' => 'submitted']);

        $team->preProposals()->where('slot', 1)->first()->update(['approved_status' => true]);

        $this->patch(route('student.pre-proposal.submit'), ['status' => 'draft'])->assertSessionHas('error');
        $this->assertSame(PreProposalController::RECOMMENDED_PROPOSALS, $team->preProposals()->whereNotNull('submitted_at')->count());
    }

    public function test_a_turned_in_set_cannot_be_added_to(): void
    {
        [$student, $team] = $this->makeLeaderWithTeam();
        $this->mockSimilarityPipeline();
        $this->actingAs($student, 'web');

        $this->saveTheMinimum();
        $this->patch(route('student.pre-proposal.submit'), ['status' => 'submitted']);

        $checkId = $this->post(route('student.pre-proposal.check'), [
            'document' => UploadedFile::fake()->create('p4.pdf', 10, 'application/pdf'),
        ])->json('duplication_check_id');

        $this->post(route('student.pre-proposal.store'), $this->payload($checkId))
            ->assertSessionHas('error');

        $this->assertSame(PreProposalController::RECOMMENDED_PROPOSALS, $team->preProposals()->count());
    }

    // team_status only carries recruitment now, so "complete" is not a value
    // it accepts at all - the request fails validation rather than needing a
    // guard to catch it.
    public function test_team_update_refuses_complete_as_a_status(): void
    {
        [$student, $team] = $this->makeLeaderWithTeam();
        $this->actingAs($student, 'web');

        $response = $this->put(route('team.update', $team->team_id), [
            'team_name' => 'PP Team',
            'team_status' => 'complete',
        ]);

        $response->assertSessionHasErrors('team_status');
        $this->assertSame('Active', $team->fresh()->team_status);
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

    /** A representative is a person; the organization may carry a number ("Barangay 5"). */
    public function test_a_representatives_name_cannot_have_numbers(): void
    {
        [$student, $team] = $this->makeLeaderWithTeam();
        $this->mockSimilarityPipeline();
        $this->actingAs($student, 'web');

        $checkId = $this->post(route('student.pre-proposal.check'), [
            'document' => UploadedFile::fake()->create('proposal.pdf', 10, 'application/pdf'),
        ])->json('duplication_check_id');

        $this->post(route('student.pre-proposal.store'), ['client_representative' => 'Ana Cruz 2'] + $this->payload($checkId))
            ->assertSessionHasErrors(['client_representative' => "A representative's name has no numbers."]);
        $this->assertSame(0, $team->preProposals()->count());

        $this->post(route('student.pre-proposal.store'), ['clients' => 'Barangay 5'] + $this->payload($checkId))
            ->assertSessionHasNoErrors();
        $this->assertSame(1, $team->preProposals()->count());
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

    // A team is only ever "complete" by having submitted, and a submitted set
    // is locked, so removing a title is now purely a draft-stage action.
    public function test_a_draft_proposal_can_be_removed(): void
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
        $response = $this->delete(route('student.pre-proposal.destroy', $proposal->id));

        $response->assertSessionHas('status');
        $this->assertDatabaseMissing('capstone_pre_proposals', ['id' => $proposal->id]);
        $this->assertSame('Active', $team->fresh()->team_status);
    }

    public function test_a_turned_in_proposal_cannot_be_removed(): void
    {
        [$student, $team] = $this->makeLeaderWithTeam();
        $this->mockSimilarityPipeline();
        $this->actingAs($student, 'web');

        $this->saveTheMinimum();
        $this->patch(route('student.pre-proposal.submit'), ['status' => 'submitted']);

        $proposal = $team->preProposals()->where('slot', 1)->first();

        $this->delete(route('student.pre-proposal.destroy', $proposal->id))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('capstone_pre_proposals', ['id' => $proposal->id]);
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

    /**
     * Leaving a team and making a new one does not carry the old scan over.
     *
     * A duplication check is recorded against the student's enrolment row,
     * not the team, so it followed them. The pre-proposal it fed stayed with
     * the team they left - so the new team's page showed a scan that could
     * not be cleared, and the refusal told them to remove a pre-proposal that
     * was not on their page and could not be.
     */
    public function test_a_spent_scan_is_not_offered_to_the_next_team(): void
    {
        [$student, $old] = $this->makeLeaderWithTeam();
        $this->mockSimilarityPipeline();
        $this->actingAs($student, 'web');

        $this->checkAndStore('p1.pdf');

        $proposal = $old->preProposals()->firstOrFail();
        $check = DuplicationCheck::findOrFail($proposal->duplication_check_id);

        // They leave, and start again somewhere else.
        $new = Team::create([
            'team_name' => 'Second Team', 'team_status' => 'Active',
            'leader_id' => $student->student_id, 'is_confirmed' => false, 'member_count' => 5,
        ]);
        StudentSchoolYear::where('student_id', $student->student_id)
            ->update(['team_id' => $new->team_id]);

        // The scan is simply not there any more. Before, it was - with a
        // Clear button that refused and named a document they could not see.
        $this->get(route('student.duplication'))->assertOk()->assertViewHas('lastCheck', null);

        // Reached directly it is still refused, and that refusal is doing
        // real work: duplication_check_id cascades, so deleting the check
        // would take the old team's pre-proposal with it.
        $this->deleteJson(route('student.pre-proposal.check.destroy', $check->id))
            ->assertStatus(422);

        $this->assertNotNull($proposal->fresh(), 'the team they left keeps its pre-proposal');
        $this->assertNotNull(DuplicationCheck::find($check->id));
    }

    /** The advice still stands when the pre-proposal really is theirs. */
    public function test_a_scan_backing_this_teams_own_pre_proposal_still_cannot_be_cleared(): void
    {
        [$student, $team] = $this->makeLeaderWithTeam();
        $this->mockSimilarityPipeline();
        $this->actingAs($student, 'web');

        $this->checkAndStore('p1.pdf');
        $check = DuplicationCheck::findOrFail($team->preProposals()->value('duplication_check_id'));

        $this->deleteJson(route('student.pre-proposal.check.destroy', $check->id))
            ->assertStatus(422)
            ->assertJson(fn ($j) => $j->where('message', fn ($m) => str_contains($m, 'Remove the pre-proposal'))->etc());

        $this->assertNotNull(DuplicationCheck::find($check->id));
    }

    /**
     * The page shows the student's latest scan whichever of their enrolment
     * rows it was recorded on; Clear only accepted the one their team is on,
     * so a scan on their other row stayed on the page after every refresh.
     */
    public function test_a_scan_on_the_students_other_enrolment_row_can_be_cleared(): void
    {
        [$student, $team] = $this->makeLeaderWithTeam();
        $otherRow = StudentSchoolYear::create(['student_id' => $student->student_id, 'team_id' => null]);

        $check = DuplicationCheck::create([
            'student_s_y_id' => $otherRow->s_y_id, 'document_path' => 'x.pdf', 'concepts' => [],
            'top_score' => 20, 'verdict' => 'Passed', 'matches' => [], 'instructors_suggested' => [],
        ]);

        $this->actingAs($student, 'web');
        $this->get(route('student.duplication'))->assertViewHas('lastCheck', fn ($c) => $c?->id === $check->id);

        $this->deleteJson(route('student.pre-proposal.check.destroy', $check->id))->assertOk();

        $this->assertNull(DuplicationCheck::find($check->id));
        $this->get(route('student.duplication'))->assertViewHas('lastCheck', null);
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
        $response->assertSee('<b class="sim-some">42%</b> closest match &middot; Some overlap', false);
    }

    // Regression: consolidating the standalone duplication checker into the
    // pre-proposal page dropped the original per-match breakdown (title,
    // score, AI reason, abstract) down to a bare summary count. The breakdown
    // is a page of its own now rather than a flap on this one, so what has to
    // survive is the whole trail: the score on the list, the breakdown one
    // click in, the abstract one click further.
    public function test_the_full_match_breakdown_is_one_click_from_the_saved_title(): void
    {
        [$student, $team] = $this->makeLeaderWithTeam();
        $sy = StudentSchoolYear::where('student_id', $student->student_id)->first();

        // A real adviser: a suggestion naming somebody who is not an expert
        // is dropped on the way to the page rather than shown as a name the
        // team cannot act on.
        $adviser = CapstoneExpert::factory()->create([
            'first_name' => 'Ana', 'last_name' => 'Reyes, MIT', 'approved_status' => true,
        ]);
        $adviser->roles()->attach(
            Role::firstOrCreate(['role_name' => 'adviser'])->role_id
        );

        $check = DuplicationCheck::create([
            'student_s_y_id' => $sy->s_y_id, 'document_path' => 'x.pdf', 'concepts' => [],
            'top_score' => 68, 'verdict' => 'High Similarity',
            'matches' => [
                ['title' => 'Flood Monitoring System for Rural Barangays', 'abstract' => 'An IoT-based early warning study.', 'academic_year' => '2023-2024', 'program' => 'BSIT', 'score' => 68, 'reason' => 'Both target flood detection using IoT sensors.', 'is_relevant' => true],
            ],
            'instructors_suggested' => [
                ['id' => $adviser->expert_id, 'name' => 'Ana Reyes, MIT', 'expertise' => 'IoT, Embedded Systems'],
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
        $response->assertSee('<b class="sim-close">68%</b> closest match', false);
        $response->assertSee(route('student.duplication.report', $check->id), false);

        $report = $this->get(route('student.duplication.report', $check->id));
        $report->assertOk();
        $report->assertSee('Flood Monitoring System for Rural Barangays');
        $report->assertSee('Both target flood detection using IoT sensors.');
        $report->assertSee('2023-2024');
        $report->assertSee('Ana Reyes, MIT');

        $match = $this->get(route('student.duplication.similar', [$check->id, 1]));
        $match->assertOk();
        $match->assertSee('An IoT-based early warning study.', false);

        // A rank that is not a number is a page that does not exist, not a crash.
        $this->get('/pre-proposal/duplication-checks/'.$check->id.'/matches/abc')->assertNotFound();
    }

    // The locked Submit button has to say what unlocks it, not just sit there
    // greyed out.
    /**
     * With one title the button works, and the page carries the number it
     * suggests plus the prompt that asks before sending fewer.
     */
    public function test_one_title_leaves_the_submit_button_usable(): void
    {
        [$student] = $this->makeLeaderWithTeam();
        $this->mockSimilarityPipeline();
        $this->actingAs($student, 'web');

        $this->checkAndStore('p1.pdf');

        $response = $this->get(route('student.duplication'));

        $response->assertStatus(200);
        $response->assertDontSee('Have at least');
        $response->assertSee('data-recommended="'.PreProposalController::RECOMMENDED_PROPOSALS.'"', false);
        $response->assertSee('confirmSubmitCount', false);
    }

    /**
     * When the machine that does the checking is not answering, a student was
     * shown the raw failure - "Cannot connect to 127.0.0.1:22. Error 10061" -
     * which tells them nothing and reads like they broke something.
     */
    public function test_an_unreachable_checker_says_so_in_plain_words(): void
    {
        [$student] = $this->makeLeaderWithTeam();
        $this->actingAs($student, 'web');

        $this->mock(OllamaSSHClient::class, function ($mock) {
            $mock->shouldReceive('warmup')->andThrow(
                new \Exception('Cannot connect to 127.0.0.1:22. Error 10061. No connection could be made because the target machine actively refused it')
            );
        });

        $response = $this->post(route('student.pre-proposal.check'), [
            'document' => UploadedFile::fake()->create('p.pdf', 10, 'application/pdf'),
        ]);

        // 503, not 500: nothing here is wrong, the checker is simply not up.
        $response->assertStatus(503);
        $response->assertJsonPath('success', false);

        $message = $response->json('message');
        $this->assertStringContainsString('not responding', $message);
        $this->assertStringNotContainsString('127.0.0.1', $message, 'The raw failure reached the student.');
        $this->assertStringNotContainsString('10061', $message);
    }

    /**
     * A problem with the document itself still says what it is - those are
     * the messages that tell a student what to do about it.
     */
    public function test_a_problem_with_the_document_still_says_what_it_is(): void
    {
        [$student] = $this->makeLeaderWithTeam();
        $this->actingAs($student, 'web');

        $this->mock(OllamaSSHClient::class, function ($mock) {
            $mock->shouldReceive('warmup')->andReturnNull();
        });

        $this->mock(SimilarityService::class, function ($mock) {
            $mock->shouldReceive('extractPdfText')->andReturn('');
        });

        $response = $this->post(route('student.pre-proposal.check'), [
            'document' => UploadedFile::fake()->create('p.pdf', 10, 'application/pdf'),
        ]);

        $response->assertStatus(500);
        $this->assertStringContainsString('Could not extract text', $response->json('message'));
    }
}
