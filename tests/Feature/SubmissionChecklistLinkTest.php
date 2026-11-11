<?php

namespace Tests\Feature;

use App\Models\CapstoneDocument;
use App\Models\CapstoneDocumentReview;
use App\Models\CapstoneExpert;
use App\Models\Checklist;
use App\Models\Deliverable;
use App\Models\Notification;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Support\MilestoneProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsChecklists;
use Tests\TestCase;

/**
 * Document Submission is the one place a file goes in. A student sending one
 * for a deliverable says which, and that is what moves the tracker.
 */
class SubmissionChecklistLinkTest extends TestCase
{
    use BuildsChecklists;
    use RefreshDatabase;

    private Student $student;

    private Team $team;

    private Checklist $checklist;

    private CapstoneExpert $capsa;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->team = Team::create(['team_name' => 'Team Alpha', 'project_title' => 'Alpha', 'team_status' => 'Active']);
        $this->student = Student::factory()->withCompleteProfile()->create([
            'course' => 'Bachelor of Science in Information Technology', 'year_section' => '3rd · A',
        ]);
        StudentSchoolYear::create(['student_id' => $this->student->student_id, 'team_id' => $this->team->team_id, 'program' => 'BSIT']);

        $roleId = DB::table('roles')->insertGetId(['role_name' => 'adviser', 'created_at' => now(), 'updated_at' => now()]);
        $adviserId = DB::table('capstone_experts')->insertGetId([
            'email' => 'adviser@gmail.com', 'first_name' => 'Ana', 'last_name' => 'Reyes',
            'is_admin' => false, 'approved_status' => true, 'created_at' => now(),
        ]);
        DB::table('expert_roles')->insert(['expert_id' => $adviserId, 'role_id' => $roleId]);
        DB::table('capstone_expert_application')->insert([
            'team_id' => $this->team->team_id, 'expert_id' => $adviserId,
            'expert_role_id' => DB::table('expert_roles')->where('expert_id', $adviserId)->value('expert_role_id'),
            'status' => 'approved', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->capsa = $this->capsaFor();
        $this->checklist = $this->emptyChecklist($this->capsa);
    }

    private function send(array $data)
    {
        return $this->actingAs($this->student)->post(route('student.submission.store'), $data);
    }

    public function test_a_file_sent_for_a_deliverable_is_recorded_against_it_and_hands_it_in(): void
    {
        $bpa = $this->addDeliverable($this->checklist, ['title' => 'Business Process Analysis', 'kind' => Deliverable::DOCUMENT]);

        $this->send([
            'file' => UploadedFile::fake()->create('final_na_final.pdf', 50, 'application/pdf'),
            'recipients' => ['adviser'],
            'deliverable_id' => $bpa->id,
        ])->assertOk()->assertJson(['success' => true]);

        $this->assertSame($bpa->id, (int) CapstoneDocument::first()->deliverable_id);
        $this->assertSame(MilestoneProgress::HANDED_IN, $this->progressOf($this->checklist, $this->team, $bpa)->state);
    }

    public function test_a_deliverable_from_another_checklist_is_refused(): void
    {
        $theirs = $this->addDeliverable(
            $this->emptyChecklist(CapstoneExpert::factory()->create(['approved_status' => true, 'sections' => ['BSIT-3B']])),
            ['title' => 'Chapter 1 Draft', 'kind' => Deliverable::DOCUMENT],
        );

        $this->send([
            'file' => UploadedFile::fake()->create('ch1.pdf', 50, 'application/pdf'),
            'recipients' => ['adviser'],
            'deliverable_id' => $theirs->id,
        ])->assertStatus(422);

        $this->assertSame(0, CapstoneDocument::count());
    }

    public function test_an_archived_deliverable_is_refused(): void
    {
        $gone = $this->addDeliverable($this->checklist, ['title' => 'Old form', 'archived_at' => now()]);

        $this->send(['file' => UploadedFile::fake()->image('old.jpg'), 'deliverable_id' => $gone->id])->assertStatus(422);
    }

    /** A signed paper goes to the CaPSA/TSA to check, not to a reviewer - and a phone photo is fine. */
    public function test_a_photo_of_a_signed_form_or_clf_needs_no_reviewer(): void
    {
        $form = $this->addDeliverable($this->checklist, ['title' => 'Signed Title Defense Hearing Notice']);
        $clf = $this->addDeliverable($this->checklist, ['title' => 'Signed CLF Approving Chapter 1', 'kind' => Deliverable::APPROVAL, 'role' => 'adviser']);

        foreach ([$form, $clf] as $paper) {
            $this->send(['file' => UploadedFile::fake()->image('scan.jpg'), 'deliverable_id' => $paper->id])
                ->assertOk()->assertJson(['success' => true]);

            $this->assertSame(MilestoneProgress::WAITING_ON_CAPSA, $this->progressOf($this->checklist, $this->team, $paper)->waiting_on);
        }

        $this->assertSame(0, CapstoneDocumentReview::count());
    }

    /** Nobody reviews a paper, so if the CaPSA/TSA is not told one arrived, nobody is. */
    public function test_the_capsa_is_told_a_signed_paper_is_waiting(): void
    {
        $form = $this->addDeliverable($this->checklist, ['title' => 'Signed Project Team Assignment Form']);

        $this->send(['file' => UploadedFile::fake()->image('scan.jpg'), 'deliverable_id' => $form->id])->assertOk();

        $told = Notification::where('expert_id', $this->capsa->expert_id)->where('type', 'paper_to_check')->sole();

        $this->assertStringContainsString('Signed Project Team Assignment Form', $told->message);
        // Deep-linked: the Milestones tab, already on this team's section.
        $this->assertStringContainsString('tab=milestones', $told->link);
        $this->assertStringContainsString('section=BSIT-3A', $told->link);
    }

    public function test_a_photo_is_only_accepted_for_a_signed_paper(): void
    {
        $draft = $this->addDeliverable($this->checklist, ['title' => 'Chapter 1 Draft', 'kind' => Deliverable::DOCUMENT]);

        $this->send([
            'file' => UploadedFile::fake()->image('draft.jpg'),
            'recipients' => ['adviser'],
            'deliverable_id' => $draft->id,
        ])->assertStatus(422);
    }

    public function test_anything_but_a_signed_paper_still_needs_someone_to_send_it_to(): void
    {
        $draft = $this->addDeliverable($this->checklist, ['title' => 'Chapter 1 Draft', 'kind' => Deliverable::DOCUMENT]);

        $this->send([
            'file' => UploadedFile::fake()->create('ch1.pdf', 50, 'application/pdf'),
            'deliverable_id' => $draft->id,
        ])->assertStatus(422);

        $this->assertSame(0, CapstoneDocument::count());
    }

    /** A revision of the same deliverable continues its version count, whatever the file is called. */
    public function test_a_resubmission_for_the_same_deliverable_is_the_next_version(): void
    {
        $bpa = $this->addDeliverable($this->checklist, ['title' => 'Business Process Analysis', 'kind' => Deliverable::DOCUMENT]);

        foreach (['bpa.pdf' => 'first draft', 'bpa_revised_final.pdf' => 'revised draft'] as $name => $content) {
            $this->send([
                'file' => UploadedFile::fake()->createWithContent($name, $content),
                'recipients' => ['adviser'],
                'deliverable_id' => $bpa->id,
            ])->assertOk();
        }

        $this->assertSame([1, 2], CapstoneDocument::orderBy('file_id')->pluck('file_version')->all());
    }

    /** Opened for one line (?item=), the page opens the upload pop-up straight onto it. */
    public function test_the_form_opens_with_the_deliverable_already_picked(): void
    {
        $draft = $this->addDeliverable($this->checklist, ['title' => 'Chapter 1 Draft', 'kind' => Deliverable::DOCUMENT]);

        // An old link to Document Submission lands on Documents, still opening on that line.
        $this->actingAs($this->student)
            ->get(route('student.submission', ['item' => $draft->id]))
            ->assertRedirect(route('student.documents', ['item' => $draft->id]));

        $this->actingAs($this->student)
            ->get(route('student.documents', ['item' => $draft->id]))
            ->assertOk()
            ->assertSee('data-open-upload="'.$draft->id.'"', false);

        $this->getJson(route('student.submission.plan', ['item' => $draft->id]))
            ->assertOk()
            ->assertJsonPath('item.title', 'Chapter 1 Draft')
            ->assertJsonPath('lines', []);
    }
}
