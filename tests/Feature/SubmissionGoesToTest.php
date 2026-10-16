<?php

namespace Tests\Feature;

use App\Http\Controllers\StudentReviewController;
use App\Models\CapstoneDocument;
use App\Models\CapstoneDocumentReview;
use App\Models\CapstoneExpert;
use App\Models\Checklist;
use App\Models\DefensePanel;
use App\Models\DefenseSchedule;
use App\Models\Deliverable;
use App\Models\PdfRemark;
use App\Models\Student;
use App\Models\Team;
use App\Support\SubmissionRecipients;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsChecklists;
use Tests\TestCase;

/**
 * Who a file goes to. A first upload starts from its line's rule and the team
 * may add or drop an optional reviewer; a revision goes back to exactly the
 * people who had the version before it, whatever is asked for.
 */
class SubmissionGoesToTest extends TestCase
{
    use BuildsChecklists;
    use RefreshDatabase;

    private Team $team;

    private Student $student;

    private CapstoneExpert $capsa;

    private Checklist $checklist;

    /** @var array<string, CapstoneExpert> */
    private array $experts = [];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->capsa = $this->capsaFor(['BSIT-3A']);
        [$this->team, $this->student] = $this->teamInSection('Team Alpha');
        $this->checklist = $this->emptyChecklist($this->capsa);

        foreach (['adviser', 'consultant', 'grammarian'] as $role) {
            $this->experts[$role] = $this->engage($role);
        }
    }

    private function engage(string $role): CapstoneExpert
    {
        $expert = CapstoneExpert::factory()->create(['approved_status' => true]);
        $roleId = DB::table('roles')->where('role_name', $role)->value('role_id')
            ?? DB::table('roles')->insertGetId(['role_name' => $role, 'created_at' => now(), 'updated_at' => now()]);
        $expertRoleId = DB::table('expert_roles')->insertGetId(['expert_id' => $expert->expert_id, 'role_id' => $roleId]);
        DB::table('capstone_expert_application')->insert([
            'team_id' => $this->team->team_id, 'expert_id' => $expert->expert_id, 'expert_role_id' => $expertRoleId,
            'status' => 'approved', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $expert;
    }

    private function chapter(string $chapter, string $title = 'Draft'): Deliverable
    {
        return $this->addDeliverable($this->checklist, ['title' => $title, 'kind' => Deliverable::DOCUMENT, 'chapter' => $chapter]);
    }

    /** @return array<string, array{on: bool, available: bool, note: ?string}> */
    private function plan(?Deliverable $line): array
    {
        return collect(app(SubmissionRecipients::class)->plan($line, $this->team->team_id)['people'])
            ->keyBy('role')
            ->map(fn ($p) => ['on' => $p['on'], 'available' => $p['available'], 'note' => $p['note']])
            ->all();
    }

    private function send(Deliverable $line, array $recipients, string $content = 'a draft')
    {
        return $this->actingAs($this->student)->postJson(route('student.submission.store'), [
            'file' => UploadedFile::fake()->createWithContent('draft.pdf', $content),
            'deliverable_id' => $line->id,
            'recipients' => $recipients,
        ]);
    }

    /** @return array<int, string> role:expert_id for every review on the team's latest upload */
    private function reviewsOfLatest(): array
    {
        $file = CapstoneDocument::orderByDesc('file_id')->value('file_id');

        return CapstoneDocumentReview::where('file_id', $file)->orderBy('review_id')->get()
            ->map(fn ($r) => $r->role.':'.$r->expert_id)->all();
    }

    public function test_chapters_1_and_2_go_to_the_capa_and_the_capsa(): void
    {
        foreach (['1', '2'] as $n) {
            $plan = $this->plan($this->chapter($n));

            $this->assertSame(['adviser', 'subject_adviser'], array_keys($plan));
            $this->assertTrue($plan['adviser']['on']);
            $this->assertTrue($plan['subject_adviser']['on']);
        }
    }

    public function test_chapter_3_adds_the_consultant_and_leaves_the_capsa_optional(): void
    {
        $plan = $this->plan($this->chapter('3'));

        $this->assertTrue($plan['adviser']['on']);
        $this->assertTrue($plan['consultant']['on']);
        $this->assertFalse($plan['subject_adviser']['on']);
        $this->assertTrue($plan['subject_adviser']['available']);
        $this->assertSame('review if asked', $plan['subject_adviser']['note']);
    }

    public function test_chapter_4_sends_the_consultant_the_diagrams(): void
    {
        $plan = $this->plan($this->chapter('4'));

        $this->assertTrue($plan['consultant']['on']);
        $this->assertSame('diagrams', $plan['consultant']['note']);
    }

    public function test_chapter_5_goes_to_the_capa(): void
    {
        $plan = $this->plan($this->chapter('5'));

        $this->assertSame(['adviser', 'subject_adviser'], array_keys($plan));
        $this->assertTrue($plan['adviser']['on']);
        $this->assertFalse($plan['subject_adviser']['on']);
    }

    public function test_the_final_manuscript_adds_the_grammarian(): void
    {
        $plan = $this->plan($this->chapter('final', 'Final Manuscript'));

        $this->assertSame(['panel', 'adviser', 'subject_adviser', 'grammarian'], array_keys($plan));
        $this->assertTrue($plan['grammarian']['on']);
        // No Final Oral Defense yet, so no panel to send it to.
        $this->assertFalse($plan['panel']['available']);
        $this->assertSame('no panel yet', $plan['panel']['note']);
    }

    public function test_the_revised_manuscript_goes_to_every_title_defense_panelist(): void
    {
        $panelists = CapstoneExpert::factory()->count(3)->create(['approved_status' => true]);
        $schedule = DefenseSchedule::create(['team_id' => $this->team->team_id, 'defense_type' => 'Title Defense', 'attempt' => 1]);
        foreach ($panelists as $i => $p) {
            DefensePanel::create(['defense_id' => $schedule->defense_id, 'expert_id' => $p->expert_id, 'panel_role' => ['Panel Chair', 'Panel Member 1', 'Panel Member 2'][$i], 'created_at' => now()]);
        }
        $line = $this->chapter('revised', 'Revised Proposal Manuscript');

        $this->send($line, ['panel', 'adviser', 'subject_adviser'])->assertOk();

        $reviews = $this->reviewsOfLatest();
        foreach ($panelists as $p) {
            $this->assertContains('panel:'.$p->expert_id, $reviews);
        }
        $this->assertContains('adviser:'.$this->experts['adviser']->expert_id, $reviews);
        $this->assertContains('subject_adviser:'.$this->capsa->expert_id, $reviews);
        $this->assertCount(5, $reviews);
    }

    /** A file for no line: the team picks who reads it, with nobody ticked and no CaPSA to pick. */
    public function test_a_file_for_no_line_has_nobody_ticked_and_no_capsa(): void
    {
        $plan = $this->plan(null);

        $this->assertSame(['adviser', 'consultant', 'grammarian'], array_keys($plan));
        $this->assertSame([false, false, false], array_column($plan, 'on'));
        $this->assertSame([true, true, true], array_column($plan, 'available'));
    }

    public function test_a_file_for_no_line_goes_only_to_who_the_team_picked(): void
    {
        $this->actingAs($this->student)->postJson(route('student.submission.store'), [
            'file' => UploadedFile::fake()->createWithContent('notes.pdf', 'notes'),
            'recipients' => ['consultant', 'subject_adviser'],
        ])->assertOk();

        $this->assertSame(['consultant:'.$this->experts['consultant']->expert_id], $this->reviewsOfLatest());
    }

    public function test_a_file_for_no_line_with_nobody_picked_is_not_sent(): void
    {
        $this->actingAs($this->student)->postJson(route('student.submission.store'), [
            'file' => UploadedFile::fake()->createWithContent('notes.pdf', 'notes'),
        ])->assertStatus(422)->assertJson(['message' => 'Pick at least one person to send this to.']);

        $this->assertSame(0, CapstoneDocument::count());
    }

    public function test_a_first_upload_only_goes_to_roles_its_line_offers(): void
    {
        $this->send($this->chapter('1'), ['adviser', 'grammarian'])->assertOk();

        $this->assertSame(['adviser:'.$this->experts['adviser']->expert_id], $this->reviewsOfLatest());
    }

    public function test_the_team_can_add_the_optional_capsa(): void
    {
        $this->send($this->chapter('3'), ['adviser', 'consultant', 'subject_adviser'])->assertOk();

        $this->assertContains('subject_adviser:'.$this->capsa->expert_id, $this->reviewsOfLatest());
    }

    public function test_a_revision_goes_back_to_the_same_experts_whatever_is_asked(): void
    {
        $line = $this->chapter('3');
        $this->send($line, ['adviser', 'consultant'], 'version one')->assertOk();
        $first = $this->reviewsOfLatest();

        // The team now has a different adviser, and asks for the CaPSA as well.
        DB::table('capstone_expert_application')->where('expert_id', $this->experts['adviser']->expert_id)->update(['status' => 'removed']);
        $this->engage('adviser');

        $plan = app(SubmissionRecipients::class)->plan($line, $this->team->team_id);
        $this->assertTrue($plan['locked']);

        $this->send($line, ['subject_adviser'], 'version two')->assertOk();

        $this->assertSame($first, $this->reviewsOfLatest());
        $this->assertSame([1, 2], CapstoneDocument::orderBy('file_id')->pluck('file_version')->map(fn ($v) => (int) $v)->all());
    }

    /** A file for no line, revised: back to whoever had it, not to whoever is ticked. */
    public function test_a_revision_of_a_file_for_no_line_goes_back_to_the_same_experts(): void
    {
        $this->actingAs($this->student)->postJson(route('student.submission.store'), [
            'file' => UploadedFile::fake()->createWithContent('diagram-notes.pdf', 'version one'),
            'recipients' => ['consultant'],
        ])->assertOk();
        $first = CapstoneDocument::orderByDesc('file_id')->first();

        $this->actingAs($this->student)->getJson(route('student.submission.plan', ['item' => 'none', 'revises' => $first->file_id]))
            ->assertOk()
            ->assertJson(['locked' => true, 'lines' => []])
            ->assertJsonCount(1, 'people')
            ->assertJsonPath('people.0.role', 'consultant');

        // Asking for the CaPSA as well changes nothing: the consultant had it.
        $this->actingAs($this->student)->postJson(route('student.submission.store'), [
            'file' => UploadedFile::fake()->createWithContent('diagram-notes.pdf', 'version two'),
            'revises' => $first->file_id,
            'recipients' => ['subject_adviser', 'adviser'],
        ])->assertOk();

        $this->assertSame(['consultant:'.$this->experts['consultant']->expert_id], $this->reviewsOfLatest());
    }

    public function test_the_same_file_as_last_time_is_refused(): void
    {
        $line = $this->chapter('1');
        $this->send($line, ['adviser'], 'the very same bytes')->assertOk();

        $this->send($line, ['adviser'], 'the very same bytes')
            ->assertStatus(422)
            ->assertJsonPath('message', 'This is the same file as version 1. Pick your edited copy.');

        $this->actingAs($this->student)->postJson(route('student.submission.warnings'), [
            'file' => UploadedFile::fake()->createWithContent('draft.pdf', 'the very same bytes'),
            'deliverable_id' => $line->id,
        ])->assertOk()->assertJsonPath('same', 1);
    }

    public function test_sending_to_nobody_is_refused(): void
    {
        DB::table('capstone_expert_application')->delete();
        $line = $this->addDeliverable($this->checklist, ['title' => 'Business Process Analysis', 'kind' => Deliverable::DOCUMENT]);

        $this->send($line, ['adviser'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Pick at least one person to send this to.');
        $this->assertSame(0, CapstoneDocument::count());
    }

    public function test_the_pop_up_is_told_the_line_the_version_and_the_remark_to_answer(): void
    {
        $line = $this->chapter('1', 'Chapter 1 Draft');
        $line->update(['due_date' => '2026-10-03']);
        $this->send($line, ['adviser', 'subject_adviser'])->assertOk();
        $review = CapstoneDocumentReview::where('role', 'adviser')->first();
        $review->update(['review_status' => CapstoneDocumentReview::REVIEWED, 'verdict' => CapstoneDocumentReview::NEEDS_REVISION, 'verdict_at' => now()]);
        PdfRemark::create(['review_id' => $review->review_id, 'page_number' => 1, 'comment' => 'Tighten the problem statement.']);
        PdfRemark::create(['review_id' => $review->review_id, 'page_number' => 2, 'comment' => 'Cite local studies.']);

        $this->actingAs($this->student)->getJson(route('student.submission.plan', ['item' => $line->id]))
            ->assertOk()
            ->assertJsonPath('item.title', 'Chapter 1 Draft')
            ->assertJsonPath('item.due', 'Oct 3, 2026')
            ->assertJsonPath('item.paper', false)
            ->assertJsonPath('version', 2)
            ->assertJsonPath('round', '1st revision')
            ->assertJsonPath('accept', ['pdf', 'drawio', 'vsdx'])
            ->assertJsonPath('locked', true)
            ->assertJsonPath('people.0.label', 'CaPA')
            ->assertJsonPath('people.1.label', 'CaPSA')
            ->assertJsonPath('remark.who', 'Your CaPA')
            ->assertJsonPath('remark.count', 2)
            ->assertJsonPath('remark.first', 'Tighten the problem statement.')
            ->assertJsonMissingPath('people.0.expert_ids');
    }

    public function test_a_signed_paper_goes_to_the_capsa_and_may_be_a_photo(): void
    {
        $form = $this->addDeliverable($this->checklist, ['title' => 'Team Assignment Form', 'kind' => Deliverable::FORM]);

        $this->actingAs($this->student)->getJson(route('student.submission.plan', ['item' => $form->id]))
            ->assertOk()
            ->assertJsonPath('item.paper', true)
            ->assertJsonPath('accept', ['pdf', 'jpg', 'jpeg', 'png'])
            ->assertJsonPath('people.0.label', 'CaPSA')
            ->assertJsonPath('people.0.name', $this->capsa->name);
    }

    public function test_the_team_sees_the_capsa_by_that_name(): void
    {
        $this->assertSame('CaPSA', StudentReviewController::roleLabel('subject_adviser'));
        $this->assertSame('Panelist', StudentReviewController::roleLabel('panel'));
    }
}
