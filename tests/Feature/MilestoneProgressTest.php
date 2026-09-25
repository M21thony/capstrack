<?php

namespace Tests\Feature;

use App\Models\CapstoneDocument;
use App\Models\CapstoneDocumentReview;
use App\Models\CapstoneExpert;
use App\Models\Deliverable;
use App\Models\DeliverableCheck;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Support\DefenseOutcome;
use App\Support\MilestoneProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsChecklists;
use Tests\TestCase;

/**
 * Progress is worked out from the records that hold the answer, never stored:
 * the upload, the verdict, the panel's outcome, the CaPSA's check.
 */
class MilestoneProgressTest extends TestCase
{
    use BuildsChecklists;
    use RefreshDatabase;

    private CapstoneExpert $capsa;

    private Team $team;

    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->capsa = $this->capsaFor();
        [$this->team, $this->student] = $this->teamInSection('Checklist Team');
    }

    private function check(Deliverable $deliverable, string $status = DeliverableCheck::COMPLETE, ?string $at = null, ?string $note = null): void
    {
        DeliverableCheck::updateOrCreate(
            ['deliverable_id' => $deliverable->id, 'team_id' => $this->team->team_id],
            ['status' => $status, 'note' => $note, 'checked_at' => $at ?? now()],
        );
    }

    public function test_a_signed_form_is_closed_by_the_capsas_check(): void
    {
        $checklist = $this->emptyChecklist($this->capsa);
        $form = $this->addDeliverable($checklist, ['title' => 'Signed Project Team Assignment Form', 'kind' => Deliverable::FORM]);

        $this->assertSame(MilestoneProgress::NOT_STARTED, $this->progressOf($checklist, $this->team, $form)->state);

        $this->check($form);

        $progress = MilestoneProgress::forTeam($checklist, $this->team->team_id);
        $this->assertSame(MilestoneProgress::DONE, $progress->items->first()->state);
        $this->assertSame(100, $progress->percent);
    }

    public function test_a_chapter_draft_counts_by_chapter_and_is_only_handed_in_on_upload(): void
    {
        $checklist = $this->emptyChecklist($this->capsa);
        $draft = $this->addDeliverable($checklist, ['title' => 'Chapter 2 Draft', 'kind' => Deliverable::DOCUMENT, 'chapter' => '2']);

        // An old upload naming no line still counts by chapter - but only its own chapter.
        $this->upload($this->student, null, chapter: 'Chapter 1');
        $this->assertSame(MilestoneProgress::NOT_STARTED, $this->progressOf($checklist, $this->team, $draft)->state);

        $this->upload($this->student, null, chapter: 'Chapter 2');
        $state = $this->progressOf($checklist, $this->team, $draft);
        $this->assertSame(MilestoneProgress::HANDED_IN, $state->state);
        $this->assertSame('adviser', $state->waiting_on);
    }

    /**
     * Handed in, reviewed, revised, approved by both - only then done. Sent
     * to the consultant as well, so their verdict is waited for; a draft that
     * never went to them closes on the adviser's (TeamReachesTitleDefenseTest).
     */
    public function test_a_chapter_draft_is_done_only_when_adviser_and_consultant_approve(): void
    {
        $checklist = $this->emptyChecklist($this->capsa);
        $draft = $this->addDeliverable($checklist, ['title' => 'Chapter 1 Draft', 'kind' => Deliverable::DOCUMENT, 'chapter' => '1']);
        $expert = CapstoneExpert::factory()->create(['approved_status' => true])->expert_id;
        $review = fn (int $file, string $role, string $verdict, $at) => CapstoneDocumentReview::create([
            'file_id' => $file, 'expert_id' => $expert, 'role' => $role,
            'review_status' => CapstoneDocumentReview::REVIEWED, 'verdict' => $verdict, 'verdict_at' => $at,
        ]);

        $first = $this->upload($this->student, $draft, now()->subDays(5)->toDateTimeString());
        $review($first, 'adviser', CapstoneDocumentReview::NEEDS_REVISION, now()->subDays(4));

        $state = $this->progressOf($checklist, $this->team, $draft);
        $this->assertSame(MilestoneProgress::WAITING_ON_TEAM, $state->waiting_on);
        $this->assertTrue($state->changes_requested);

        $second = $this->upload($this->student, $draft, now()->subDays(3)->toDateTimeString());
        CapstoneDocumentReview::create(['file_id' => $second, 'expert_id' => $expert, 'role' => 'consultant', 'review_status' => 'To Review']);
        $review($second, 'adviser', CapstoneDocumentReview::APPROVED, now()->subDays(2));

        $state = $this->progressOf($checklist, $this->team, $draft);
        $this->assertSame(MilestoneProgress::HANDED_IN, $state->state);
        $this->assertSame('consultant', $state->waiting_on);

        CapstoneDocumentReview::where('file_id', $second)->where('role', 'consultant')
            ->update(['review_status' => CapstoneDocumentReview::REVIEWED, 'verdict' => CapstoneDocumentReview::APPROVED, 'verdict_at' => now()->subDay()]);
        $this->assertSame(MilestoneProgress::DONE, $this->progressOf($checklist, $this->team, $draft)->state);
    }

    /**
     * There is no e-signing, so a CLF is paper: the adviser approving the
     * draft only means the signed copy can follow. The CaPSA's check closes it.
     */
    public function test_a_signed_clf_waits_for_the_signed_copy_and_then_the_capsa(): void
    {
        $checklist = $this->emptyChecklist($this->capsa);
        $draft = $this->addDeliverable($checklist, ['title' => 'Chapter 1 Draft', 'kind' => Deliverable::DOCUMENT, 'chapter' => '1']);
        $clf = $this->addDeliverable($checklist, ['title' => 'Signed CLF Approving Chapter 1', 'kind' => Deliverable::APPROVAL, 'role' => 'adviser', 'chapter' => '1']);

        CapstoneDocumentReview::create([
            'file_id' => $this->upload($this->student, $draft, now()->subDays(3)->toDateTimeString()),
            'expert_id' => CapstoneExpert::factory()->create(['approved_status' => true])->expert_id,
            'role' => 'adviser',
            'review_status' => CapstoneDocumentReview::REVIEWED,
            'verdict' => CapstoneDocumentReview::APPROVED,
            'verdict_at' => now()->subDays(2),
        ]);

        $state = $this->progressOf($checklist, $this->team, $clf);
        $this->assertSame(MilestoneProgress::HANDED_IN, $state->state);
        $this->assertSame(MilestoneProgress::WAITING_ON_SIGNED_COPY, $state->waiting_on);
        $this->assertNotNull($state->approved_at);

        $scan = $this->upload($this->student, $clf, now()->subDay()->toDateTimeString(), path: 'submissions/clf.jpg');
        $state = $this->progressOf($checklist, $this->team, $clf);
        $this->assertSame(MilestoneProgress::WAITING_ON_CAPSA, $state->waiting_on);
        $this->assertSame($scan, (int) $state->file_id);

        $this->check($clf);
        $this->assertSame(MilestoneProgress::DONE, $this->progressOf($checklist, $this->team, $clf)->state);
    }

    /** The checklist asks for the adviser's CLF and the consultant's separately. */
    public function test_the_consultants_approval_does_not_move_the_advisers_clf(): void
    {
        $checklist = $this->emptyChecklist($this->capsa);
        $draft = $this->addDeliverable($checklist, ['title' => 'Chapter 1 Draft', 'kind' => Deliverable::DOCUMENT, 'chapter' => '1']);
        $clf = $this->addDeliverable($checklist, ['title' => 'Signed CLF Approving Chapter 1', 'kind' => Deliverable::APPROVAL, 'role' => 'adviser', 'chapter' => '1']);

        CapstoneDocumentReview::create([
            'file_id' => $this->upload($this->student, $draft),
            'expert_id' => CapstoneExpert::factory()->create(['approved_status' => true])->expert_id,
            'role' => 'consultant',
            'review_status' => CapstoneDocumentReview::REVIEWED,
            'verdict' => CapstoneDocumentReview::APPROVED,
            'verdict_at' => now(),
        ]);

        $this->assertSame(MilestoneProgress::NOT_STARTED, $this->progressOf($checklist, $this->team, $clf)->state);
    }

    public function test_a_defense_closes_on_the_recorded_outcome(): void
    {
        $checklist = $this->emptyChecklist($this->capsa);
        $defense = $this->addDeliverable($checklist, ['title' => 'Title Defense', 'kind' => Deliverable::DEFENSE, 'defense_type' => 'Title Defense']);

        $defenseId = DB::table('defense_schedule')->insertGetId(['team_id' => $this->team->team_id, 'defense_type' => 'Title Defense']);
        $this->assertSame(MilestoneProgress::HANDED_IN, $this->progressOf($checklist, $this->team, $defense)->state);

        DB::table('defense_schedule')->where('defense_id', $defenseId)->update([
            'outcome' => DefenseOutcome::PASSED_WITH_REVISION,
            'outcome_recorded_at' => now(),
        ]);

        $this->assertSame(MilestoneProgress::DONE, $this->progressOf($checklist, $this->team, $defense)->state);
    }

    /** A panel asking for a re-defense leaves the defense open, and the attempts are counted. */
    public function test_a_redefense_keeps_the_defense_open_until_a_later_attempt_passes(): void
    {
        $checklist = $this->emptyChecklist($this->capsa);
        $defense = $this->addDeliverable($checklist, ['title' => 'Title Defense', 'kind' => Deliverable::DEFENSE, 'defense_type' => 'Title Defense']);

        DB::table('defense_schedule')->insert([
            'team_id' => $this->team->team_id, 'defense_type' => 'Title Defense', 'attempt' => 1,
            'outcome' => DefenseOutcome::REDEFENSE, 'outcome_recorded_at' => now()->subWeek(),
        ]);

        $state = $this->progressOf($checklist, $this->team, $defense);
        $this->assertSame(MilestoneProgress::HANDED_IN, $state->state);
        $this->assertSame(MilestoneProgress::WAITING_ON_REDEFENSE, $state->waiting_on);
        $this->assertSame(1, $state->revision);

        $second = DB::table('defense_schedule')->insertGetId([
            'team_id' => $this->team->team_id, 'defense_type' => 'Title Defense', 'attempt' => 2,
        ]);

        $state = $this->progressOf($checklist, $this->team, $defense);
        $this->assertNull($state->waiting_on, 'the re-defense is on the calendar now');
        $this->assertSame(1, $state->revision);

        DB::table('defense_schedule')->where('defense_id', $second)->update([
            'outcome' => DefenseOutcome::PASSED, 'outcome_recorded_at' => now(),
        ]);

        $this->assertSame(MilestoneProgress::DONE, $this->progressOf($checklist, $this->team, $defense)->state);
    }

    public function test_late_and_overdue_are_worked_out_from_the_dates(): void
    {
        $checklist = $this->emptyChecklist($this->capsa);
        $early = $this->addDeliverable($checklist, ['title' => 'Early form', 'due_date' => now()->subDays(10)->toDateString()]);
        $this->addDeliverable($checklist, ['title' => 'Nothing yet', 'due_date' => now()->subDays(10)->toDateString()]);

        $this->assertSame(2, MilestoneProgress::forTeam($checklist, $this->team->team_id)->overdue);

        $this->check($early, at: now()->subDays(4)->toDateTimeString());

        $progress = MilestoneProgress::forTeam($checklist, $this->team->team_id);
        $this->assertSame(1, $progress->late);
        $this->assertSame(1, $progress->overdue);
        $this->assertSame(6, $progress->items->firstWhere('state', MilestoneProgress::DONE_LATE)->days_late);

        // Moving the deadline out un-flags both, with nothing to correct.
        $checklist->deliverables()->update(['deliverables.due_date' => now()->addWeek()->toDateString()]);
        $after = MilestoneProgress::forTeam($checklist, $this->team->team_id);
        $this->assertSame(0, $after->late);
        $this->assertSame(0, $after->overdue);
    }

    public function test_a_paper_sent_back_waits_on_the_team_until_they_upload_again(): void
    {
        $checklist = $this->emptyChecklist($this->capsa);
        $form = $this->addDeliverable($checklist, ['title' => 'Signed Title Defense Hearing Notice']);
        $this->upload($this->student, $form, now()->subDays(2)->toDateTimeString());

        $this->check($form, DeliverableCheck::SENT_BACK, now()->subDay()->toDateTimeString(), 'The adviser has not signed yet.');

        $state = $this->progressOf($checklist, $this->team, $form);
        $this->assertSame(MilestoneProgress::WAITING_ON_TEAM, $state->waiting_on);
        $this->assertSame('The adviser has not signed yet.', $state->note);

        $this->upload($this->student, $form, now()->toDateTimeString());

        $state = $this->progressOf($checklist, $this->team, $form);
        $this->assertSame(MilestoneProgress::WAITING_ON_CAPSA, $state->waiting_on);
        $this->assertNull($state->note);
    }

    public function test_archived_deliverables_and_milestones_are_not_counted(): void
    {
        $checklist = $this->departmentChecklist($this->capsa);
        $this->assertSame(38, MilestoneProgress::forTeam($checklist, $this->team->team_id)->total);

        $this->line($checklist, 'td_rsc')->update(['archived_at' => now()]);
        $checklist->milestones()->where('name', 'Getting started')->update(['archived_at' => now()]);

        $this->assertSame(38 - 1 - 5, MilestoneProgress::forTeam($checklist, $this->team->team_id)->total);
    }

    public function test_the_department_checklist_has_the_papers_kinds(): void
    {
        $checklist = $this->departmentChecklist($this->capsa);
        $byKind = $checklist->deliverables()->get()->groupBy('kind')->map->count();

        // Capstone 1's paper, then Capstone 2 below it.
        $this->assertSame(8 + 7, $byKind[Deliverable::FORM]);
        $this->assertSame(5 + 6, $byKind[Deliverable::APPROVAL]);
        $this->assertSame(4 + 3, $byKind[Deliverable::DOCUMENT]);
        $this->assertSame(2 + 2, $byKind[Deliverable::DEFENSE]);
        $this->assertSame(1, $byKind[Deliverable::CUSTOM]);
    }

    public function test_many_teams_are_resolved_together(): void
    {
        $checklist = $this->emptyChecklist($this->capsa);
        $form = $this->addDeliverable($checklist, ['title' => 'Signed form']);
        [$other] = $this->teamInSection('Other Team');

        DeliverableCheck::create(['deliverable_id' => $form->id, 'team_id' => $other->team_id, 'status' => DeliverableCheck::COMPLETE, 'checked_at' => now()]);

        $all = MilestoneProgress::forTeams($checklist, [$this->team->team_id, $other->team_id]);

        $this->assertSame(0, $all[$this->team->team_id]->completed);
        $this->assertSame(1, $all[$other->team_id]->completed);
    }

    /** A member who moves teams leaves their papers with the team they were sent for. */
    public function test_a_members_uploads_from_a_team_they_left_do_not_open_the_new_teams_lines(): void
    {
        $checklist = $this->emptyChecklist($this->capsa);
        $form = $this->addDeliverable($checklist, ['title' => 'Signed Project Team Assignment Form', 'kind' => Deliverable::FORM]);
        $this->upload($this->student, $form, now()->subDays(10)->toDateTimeString());

        $newTeam = Team::create(['team_name' => 'New Team', 'team_status' => 'Active', 'leader_id' => $this->student->student_id, 'is_confirmed' => true, 'member_count' => 5]);
        StudentSchoolYear::where('student_id', $this->student->student_id)->update(['team_id' => $newTeam->team_id]);

        $this->assertSame(MilestoneProgress::NOT_STARTED, $this->progressOf($checklist, $newTeam, $form)->state);
        $this->assertSame(MilestoneProgress::WAITING_ON_CAPSA, $this->progressOf($checklist, $this->team, $form)->waiting_on);
    }

    /** Files from before uploads recorded their team: one sent before the team existed was for another. */
    public function test_an_upload_with_no_team_counts_only_when_it_went_up_after_the_team_was_made(): void
    {
        $checklist = $this->emptyChecklist($this->capsa);
        $form = $this->addDeliverable($checklist, ['title' => 'Signed Project Team Assignment Form', 'kind' => Deliverable::FORM]);
        DB::table('team')->where('team_id', $this->team->team_id)->update(['created_at' => now()->subDays(3)]);

        $before = $this->upload($this->student, $form, now()->subDays(5)->toDateTimeString());
        DB::table('capstone_documents')->where('file_id', $before)->update(['team_id' => null]);
        $this->assertSame(MilestoneProgress::NOT_STARTED, $this->progressOf($checklist, $this->team, $form)->state);

        $after = $this->upload($this->student, $form, now()->subDay()->toDateTimeString());
        DB::table('capstone_documents')->where('file_id', $after)->update(['team_id' => null]);
        $this->assertSame(MilestoneProgress::WAITING_ON_CAPSA, $this->progressOf($checklist, $this->team, $form)->waiting_on);
    }

    public function test_a_new_upload_records_the_uploaders_team(): void
    {
        $document = CapstoneDocument::create([
            'student_id' => $this->student->student_id, 'file_name' => 'Chapter 1', 'file_path' => 'submissions/c1.pdf',
            'file_version' => 1, 'document_status' => 'Pending',
        ]);

        $this->assertSame($this->team->team_id, (int) $document->team_id);
    }

    public function test_existing_uploads_are_given_the_team_that_existed_when_they_went_up(): void
    {
        DB::table('team')->where('team_id', $this->team->team_id)->update(['created_at' => now()->subDays(3)]);
        $before = $this->upload($this->student, null, now()->subDays(5)->toDateTimeString());
        $after = $this->upload($this->student, null, now()->subDay()->toDateTimeString());
        DB::table('capstone_documents')->update(['team_id' => null]);

        $migration = require base_path('database/migrations/2026_09_24_120618_add_team_id_to_capstone_documents_table.php');
        $migration->down();
        $migration->up();

        $this->assertNull(DB::table('capstone_documents')->where('file_id', $before)->value('team_id'));
        $this->assertSame($this->team->team_id, (int) DB::table('capstone_documents')->where('file_id', $after)->value('team_id'));
    }
}
