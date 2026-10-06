<?php

namespace Tests\Feature;

use App\Models\ArchivedDocument;
use App\Models\CapstoneDocument;
use App\Models\CapstoneDocumentReview;
use App\Models\CapstoneExpert;
use App\Models\Checklist;
use App\Models\DefenseSchedule;
use App\Models\DefenseWindow;
use App\Models\Deliverable;
use App\Models\ExpensePost;
use App\Models\ExpertRole;
use App\Models\FinancialRecord;
use App\Models\GradingRubric;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Models\TeamExpertAssignment;
use App\Services\OllamaSSHClient;
use App\Services\SimilarityService;
use App\Support\DefenseOutcome;
use App\Support\ExpertEvaluations;
use App\Support\MilestoneProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsChecklists;
use Tests\TestCase;

/**
 * One team from Getting started to a passed Title Defense, every step taken
 * through the pages the people involved use: the students upload, the
 * adviser and consultant review, the CaPSA checks papers, recommends a
 * title, verifies fees and schedules, and the panel grades and decides.
 * Only the AI behind the duplication check is stood in for.
 */
class TeamReachesTitleDefenseTest extends TestCase
{
    use BuildsChecklists;
    use RefreshDatabase;

    private CapstoneExpert $capsa;

    private Checklist $checklist;

    private Team $team;

    /** @var list<Student> */
    private array $members = [];

    /** @var array<string, CapstoneExpert> adviser, consultant, grammarian */
    private array $experts = [];

    /** @var array<string, CapstoneExpert> chair, one, two */
    private array $panel = [];

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2027-01-11 09:00:00');
        Mail::fake();
        Queue::fake();
        Storage::fake('local');
        Storage::fake('public');

        $this->capsa = $this->capsaFor(['BSIT-4A']);
        $this->checklist = $this->departmentChecklist($this->capsa);

        foreach (range(1, 3) as $n) {
            $this->members[] = Student::factory()->withCompleteProfile()->withTourCompleted()->create([
                'email' => "member{$n}@my.cspc.edu.ph",
                'course' => 'Bachelor of Science in Information Technology',
                'year_section' => '4th · A',
            ]);
        }
        $this->team = Team::create([
            'team_name' => 'Bulbasaur', 'team_status' => 'Active', 'is_confirmed' => true,
            'member_count' => 3, 'leader_id' => $this->members[0]->student_id,
        ]);
        foreach ($this->members as $member) {
            StudentSchoolYear::create(['student_id' => $member->student_id, 'team_id' => $this->team->team_id, 'program' => 'BSIT', 'student_status' => 'Active']);
        }

        foreach (['adviser', 'consultant', 'grammarian'] as $role) {
            $this->experts[$role] = $this->expertHolding($role);
            TeamExpertAssignment::create([
                'team_id' => $this->team->team_id, 'expert_id' => $this->experts[$role]->expert_id, 'status' => 'approved',
                'expert_role_id' => ExpertRole::where('expert_id', $this->experts[$role]->expert_id)->value('expert_role_id'),
            ]);
        }
        foreach (['chair', 'one', 'two'] as $seat) {
            $this->panel[$seat] = $this->expertHolding($seat === 'two' ? 'consultant' : 'adviser');
        }

        foreach ([DefenseOutcome::PRE_PROPOSAL, DefenseOutcome::TITLE_DEFENSE, 'Pre Oral Defense', 'Final Oral Defense'] as $type) {
            $rubric = GradingRubric::create(['rubric_name' => $type.' Rubric', 'rubric_type' => $type, 'total_points' => 100, 'is_active' => true]);
            $rubric->criteria()->create([
                'criterion_name' => 'Overall', 'category' => 'All', 'category_max_points' => 100, 'max_points' => 100,
                'score_levels' => json_encode([['label' => 'Excellent', 'score' => 100, 'desc' => ''], ['label' => 'Fair', 'score' => 70, 'desc' => '']]),
                'sort_order' => 0,
            ]);
        }

        $this->standInForTheAi();
    }

    private function expertHolding(string $role): CapstoneExpert
    {
        $expert = CapstoneExpert::factory()->create(['approved_status' => true]);
        $expert->roles()->attach(Role::firstOrCreate(['role_name' => $role])->role_id);

        return $expert;
    }

    /** The duplication check's AI runs on another machine; everything around it is real. */
    private function standInForTheAi(): void
    {
        $archived = ArchivedDocument::create([
            'title' => 'An Older Capstone', 'abstract' => 'Something else.', 'academic_year' => 2022,
            'program' => 'BSIT', 'file_path' => '',
        ]);
        $this->mock(OllamaSSHClient::class, fn ($mock) => $mock->shouldReceive('warmup')->andReturn(null));
        $this->mock(SimilarityService::class, function ($mock) use ($archived) {
            $mock->shouldReceive('extractPdfText')->andReturn('A capstone pre-proposal.');
            $mock->shouldReceive('isPreProposal')->andReturn(['is_pre_proposal' => true, 'reason' => '']);
            $mock->shouldReceive('extractKeyConcepts')->andReturn(['title' => 'CapsTrack', 'main_topic' => 'Capstone management', 'domain' => 'Web', 'keywords' => ['capstone']]);
            $mock->shouldReceive('findSimilarDocuments')->andReturn([
                ['document' => $archived, 'score' => 20, 'reason' => 'Different topic.', 'is_relevant' => true, 'matched_on' => []],
            ]);
            $mock->shouldReceive('suggestInstructors')->andReturn([]);
            $mock->shouldReceive('extractHighlightWords')->andReturn([]);
        });
    }

    // ------------------------------------------------------------------
    // What each person does
    // ------------------------------------------------------------------

    private function leader(): Student
    {
        return $this->members[0];
    }

    private function asStudent(Student $student): static
    {
        $this->evaluateFinishedPhases();

        return $this->actingAs($student, 'web');
    }

    private function asExpert(CapstoneExpert $expert): static
    {
        $this->evaluateFinishedPhases();

        return $this->actingAs($expert, 'expert');
    }

    /**
     * What a leader does after each phase: rate every expert for it. The next
     * phase stays locked until they do (see ExpertEvaluations).
     */
    private function evaluateFinishedPhases(): void
    {
        foreach (app(ExpertEvaluations::class)->due($this->team) as $owed) {
            $this->actingAs($this->leader(), 'web')
                ->post(route('student.evaluations.store'), [
                    'milestone_id' => $owed->milestone_id,
                    'expert_id' => $owed->expert_id,
                    'role' => $owed->role,
                    'rating' => 5,
                    'comment' => 'Guided us well through '.$owed->milestone.'.',
                ])
                ->assertSessionHas('evaluation_saved');
        }
    }

    private function line(string $key)
    {
        return $this->checklist->deliverables()->where('deliverables.template_key', $key)->firstOrFail();
    }

    private function state(string $key): object
    {
        return MilestoneProgress::forTeam($this->checklist->fresh(), $this->team->team_id)
            ->items->first(fn ($row) => $row->item->template_key === $key);
    }

    private function assertDone(string $key): void
    {
        $row = $this->state($key);
        $this->assertContains($row->state, MilestoneProgress::COMPLETE_STATES, "{$key} should be done, is {$row->state} (waiting on ".($row->waiting_on ?? 'nothing').')');
    }

    private int $uploads = 0;

    /** The team hands in a file for a checklist line, as the upload pop-up sends it. */
    private function handIn(string $key, array $recipients = [], string $ext = 'pdf'): void
    {
        $file = UploadedFile::fake()->createWithContent("{$key}.{$ext}", "%PDF-1.4 {$key} ".(++$this->uploads));

        $this->asStudent($this->leader())
            ->post(route('student.submission.store'), array_filter([
                'deliverable_id' => $this->line($key)->id,
                'recipients' => $recipients ?: null,
                'file' => $file,
            ]), ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJson(['success' => true]);
    }

    /** The CaPSA ticks a signed paper as checked. */
    private function capsaChecks(string $key): void
    {
        $this->asExpert($this->capsa)
            ->putJson(route('capsa.deliverables.checks.update', [$this->line($key)->id, $this->team->team_id]), ['status' => 'complete'])
            ->assertOk();
    }

    /** A paper the team signs, uploads, and the CaPSA checks. */
    private function paper(string $key): void
    {
        $this->handIn($key);
        $this->capsaChecks($key);
        $this->assertDone($key);
    }

    /**
     * A document sent to everyone the upload pop-up offers for it; every
     * reviewer on it approves.
     */
    private function reviewed(string $key): void
    {
        $this->handIn($key, ['adviser', 'consultant', 'subject_adviser', 'grammarian', 'panel']);
        $file = CapstoneDocument::where('deliverable_id', $this->line($key)->id)->latest('file_id')->firstOrFail();
        $reviews = CapstoneDocumentReview::where('file_id', $file->file_id)->get();
        $this->assertContains('adviser', $reviews->pluck('role')->all());

        foreach ($reviews as $review) {
            $this->asExpert(CapstoneExpert::findOrFail($review->expert_id))
                ->postJson(route('reviews.status.update', $review->review_id), ['review_status' => CapstoneDocumentReview::REVIEWED, 'verdict' => CapstoneDocumentReview::APPROVED])
                ->assertOk();
        }
        $this->assertDone($key);
    }

    /** A chapter draft its reviewers approve, then the adviser's signed CLF for it. */
    private function chapter(int $n): void
    {
        $this->reviewed("chapter_{$n}_draft");
        $this->paper("clf_chapter_{$n}");
    }

    /**
     * Every member hands their receipt for the defense's fee to the leader,
     * the leader sends the team's to the CaPSA at once, and the CaPSA
     * verifies each.
     */
    private function feesPaid(string $type): void
    {
        $fee = ExpensePost::create([
            'admin_id' => $this->capsa->expert_id, 'title' => $type.' Fee', 'course' => 'BSIT',
            'defense_type' => $type, 'amount' => 300, 'is_active' => true,
        ]);

        foreach ($this->members as $i => $member) {
            $this->asStudent($member)
                ->post(route('student.financial.submit'), [
                    'expense_post_id' => $fee->id,
                    'receipt_image' => UploadedFile::fake()->image("receipt{$i}.jpg"),
                    'receipt_number' => 'OR '.$fee->id.'00'.$i,
                ])->assertSessionHasNoErrors();
        }

        $this->asStudent($this->leader())
            ->postJson(route('api.student.financial.submit-team'), ['expense_post_id' => $fee->id])
            ->assertOk();

        foreach ($this->members as $member) {
            $record = FinancialRecord::where('student_id', $member->student_id)->where('expense_post_id', $fee->id)->firstOrFail();
            $this->asExpert($this->capsa)
                ->patchJson(route('api.admin.financial.update', $record->id), ['status' => 'approved', 'or_confirmed' => true])
                ->assertOk();
        }
    }

    /** The two CLFs recommending the team for a defense, and its signed hearing notice. */
    private function readyFor(string $prefix): void
    {
        $this->paper("clf_recommend_{$prefix}_adviser");
        $this->paper("clf_recommend_{$prefix}_consultant");
        $this->paper("{$prefix}_hearing_notice");
    }

    /** The CaPSA gives the team its panel, sets the date inside the window, and the panel grades it on the day. */
    private function defense(string $type, string $date): void
    {
        DefenseWindow::updateOrCreate(['defense_type' => $type], ['start_date' => $date, 'end_date' => $date]);

        $this->asExpert($this->capsa)
            ->postJson(route('api.admin.panel_assignment.store', $this->team->team_id), [
                'defense_type' => $type,
                'chair_expert_id' => $this->panel['chair']->expert_id,
                'member1_expert_id' => $this->panel['one']->expert_id,
                'member2_expert_id' => $this->panel['two']->expert_id,
            ])
            ->assertOk()->assertJson(['success' => true]);

        $defense = DefenseSchedule::where('team_id', $this->team->team_id)->where('defense_type', $type)->firstOrFail();

        $this->asExpert($this->capsa)
            ->putJson(route('api.admin.panel_assignment.schedule.update', $defense->defense_id), ['schedule_date' => $date, 'start_time' => '08:00', 'venue' => 'Mac Lab'])
            ->assertOk()->assertJson(['success' => true]);

        Carbon::setTestNow($date.' 10:30:00');
        $criterion = GradingRubric::where('rubric_type', $type)->firstOrFail()->criteria()->first();

        foreach ($this->panel as $expert) {
            $this->asExpert($expert)
                ->patchJson(route('instructor.grading.submit', $defense->defense_id), ['scores' => [$criterion->criterion_id => 100]])
                ->assertOk();
        }

        $this->asExpert($this->panel['chair'])
            ->postJson(route('instructor.grading.outcome', $defense->defense_id), ['outcome' => DefenseOutcome::PASSED])
            ->assertOk();

        Carbon::setTestNow(Carbon::parse($date)->addDay()->setTime(9, 0));
    }

    // ------------------------------------------------------------------

    public function test_a_team_goes_from_getting_started_to_a_passed_title_defense(): void
    {
        $this->reachTitleDefense();

        // Past it, the next line is open to hand in.
        $this->assertFalse($this->state('revised_manuscript')->locked, 'the Revised Proposal Manuscript opens after the Title Defense');
    }

    public function test_the_team_carries_on_to_the_final_manuscript(): void
    {
        $this->reachTitleDefense();

        // The rest of Capstone 1.
        $this->reviewed('revised_manuscript');
        $this->paper('td_rsc');

        // Capstone 2: building the system, then Chapter 4 and the Pre Oral Defense.
        $this->paper('c2_development_plan');
        $this->capsaChecks('c2_system_demo');
        $this->assertDone('c2_system_demo');
        $this->chapter(4);
        $this->readyFor('pod');
        $this->feesPaid('Pre Oral Defense');
        $this->defense('Pre Oral Defense', '2027-08-16');
        $this->assertDone('pre_oral_defense');
        $this->paper('pod_rsc');

        // Chapter 5 and the Final Oral Defense.
        $this->chapter(5);
        $this->readyFor('fod');
        $this->feesPaid('Final Oral Defense');
        $this->defense('Final Oral Defense', '2027-10-18');
        $this->assertDone('final_oral_defense');
        $this->paper('fod_rsc');

        // The Final Manuscript, edited and signed.
        $this->reviewed('final_manuscript');
        $this->paper('editing_certificate');
        $this->paper('approval_sheet');

        $progress = MilestoneProgress::forTeam($this->checklist->fresh(), $this->team->team_id);
        $open = $progress->items->reject(fn ($row) => in_array($row->state, MilestoneProgress::COMPLETE_STATES, true))
            ->map(fn ($row) => $row->item->template_key)->values()->all();
        $this->assertSame([], $open, 'every line on the checklist is closed');
    }

    /** From Getting started to a passed Title Defense, as the people involved do it. */
    private function reachTitleDefense(): void
    {
        // Getting started: the signed forms.
        foreach (['team_assignment_form', 'role_acceptance_adviser', 'role_acceptance_consultant', 'role_acceptance_grammarian'] as $key) {
            $this->paper($key);
        }

        // Pre-proposal: titles checked for duplicates, sent in, one recommended.
        $this->asStudent($this->leader());
        foreach (range(1, 4) as $n) {
            $checkId = $this->post(route('student.pre-proposal.check'), ['document' => UploadedFile::fake()->create("proposal{$n}.pdf", 20, 'application/pdf')])
                ->assertOk()->json('duplication_check_id');

            $this->post(route('student.pre-proposal.store'), [
                'title' => ['CapsTrack: A Capstone Tracker', 'Smart Parking Finder', 'Barangay Clearance Tracker', 'Canteen Queue System'][$n - 1],
                'sdg_goals' => ['SDG 4 – Quality Education'],
                'research_agenda' => ['Applications and Systems Development'],
                'problem_statement' => 'A problem.', 'proposed_solution' => 'A solution.', 'scope' => 'A scope.', 'limitations' => 'A limitation.',
                'sdm' => 'Agile Development Methodology', 'clients' => 'CSPC', 'client_address' => 'Nabua', 'client_representative' => 'Dean', 'client_position' => 'Dean',
                'tags' => ['Web-based', 'Management System'],
                'duplication_check_id' => $checkId,
            ])->assertSessionHasNoErrors();
        }
        $this->patch(route('student.pre-proposal.submit'), ['status' => 'submitted'])->assertSessionMissing('error');
        $first = $this->team->preProposals()->orderBy('slot')->firstOrFail();
        $this->asExpert($this->capsa)
            ->patchJson(route('capsa.verify_teams.pre_proposals.update', $first->id), ['status' => 'approved'])
            ->assertOk();
        $this->assertDone('pre_proposal_title_form');

        // Pre-Proposal Oral Presentation, then the final title form signed on the title that passed.
        $this->defense(DefenseOutcome::PRE_PROPOSAL, '2027-02-15');
        $this->assertDone('pre_proposal_defense');
        $this->paper('final_title_form');

        // Finished, but the leader has not rated the experts for it yet.
        $this->assertSame('the expert evaluation for Pre-proposal', $this->state('chapter_1_draft')->waits_for);
        $this->evaluateFinishedPhases();

        // Past the Pre-proposal, Chapters 1 to 3 open together: none waits for another.
        foreach ([1, 2, 3] as $n) {
            $this->assertFalse($this->state("chapter_{$n}_draft")->locked, "Chapter {$n} Draft opens after the Pre-proposal");
        }

        // Chapters 1 to 3, each approved by both reviewers and signed off by the adviser.
        foreach ([1, 2, 3] as $n) {
            $this->chapter($n);
        }

        // Title Defense: recommended by the adviser and consultant, the hearing
        // notice signed, and every member's fee receipt verified.
        $this->readyFor('td');
        $this->feesPaid(DefenseOutcome::TITLE_DEFENSE);
        $this->defense(DefenseOutcome::TITLE_DEFENSE, '2027-03-15');
        $this->assertDone('title_defense');
    }

    // ------------------------------------------------------------------
    // Who a draft waits for
    // ------------------------------------------------------------------

    private function review(int $fileId, string $role, ?string $verdict): void
    {
        CapstoneDocumentReview::create([
            'file_id' => $fileId, 'expert_id' => $this->experts[$role]->expert_id, 'role' => $role, 'version' => '1',
            'review_status' => $verdict ? CapstoneDocumentReview::REVIEWED : 'To Review',
            'verdict' => $verdict, 'verdict_at' => $verdict ? now() : null,
        ]);
    }

    /** Chapters 1, 2 and 5 go to the adviser and the CaPSA: the adviser's approval closes them. */
    public function test_a_draft_not_sent_to_the_consultant_closes_on_the_advisers_approval(): void
    {
        $fileId = $this->onRecord($this->line('chapter_1_draft'));
        $this->review($fileId, 'adviser', CapstoneDocumentReview::APPROVED);

        $this->assertDone('chapter_1_draft');
    }

    /** Chapter 3 goes to the consultant too, and waits for them. */
    public function test_a_draft_sent_to_the_consultant_waits_for_their_approval(): void
    {
        $fileId = $this->onRecord($this->line('chapter_3_draft'));
        $this->review($fileId, 'adviser', CapstoneDocumentReview::APPROVED);
        $this->review($fileId, 'consultant', null);

        $row = $this->state('chapter_3_draft');
        $this->assertSame(MilestoneProgress::HANDED_IN, $row->state);
        $this->assertSame('consultant', $row->waiting_on);

        CapstoneDocumentReview::where('role', 'consultant')->update([
            'review_status' => CapstoneDocumentReview::REVIEWED, 'verdict' => CapstoneDocumentReview::APPROVED, 'verdict_at' => now(),
        ]);
        $this->assertDone('chapter_3_draft');
    }

    /** A draft on record for the line, straight in - the gates in front of it are not what these test. */
    private function onRecord(Deliverable $line): int
    {
        return $this->upload($this->leader(), $line, null, $line->chapter, 'submissions/'.$line->template_key.'.pdf');
    }
}
