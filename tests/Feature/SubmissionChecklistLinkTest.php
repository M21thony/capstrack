<?php

namespace Tests\Feature;

use App\Models\CapstoneDocument;
use App\Models\CapstoneDocumentReview;
use App\Models\Milestone;
use App\Models\MilestoneItem;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Support\MilestoneProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Document Submission is the one place a file goes in. A student sending one
 * for a checklist line says which, and that is what moves the checklist.
 */
class SubmissionChecklistLinkTest extends TestCase
{
    use RefreshDatabase;

    private Student $student;

    private Team $team;

    private Milestone $milestone;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->team = Team::create(['team_name' => 'Team Alpha', 'project_title' => 'Alpha', 'team_status' => 'Active']);
        $this->student = Student::factory()->withCompleteProfile()->create();
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

        $this->milestone = Milestone::create(['team_id' => $this->team->team_id, 'title' => 'Capstone 1', 'status' => 'todo']);
    }

    private function send(array $data)
    {
        return $this->actingAs($this->student)->post(route('student.submission.store'), $data);
    }

    public function test_a_file_sent_for_a_line_is_recorded_against_it_and_closes_it(): void
    {
        $item = $this->milestone->items()->create(['title' => 'Business Process Analysis', 'kind' => MilestoneItem::DOCUMENT]);

        $this->send([
            'file' => UploadedFile::fake()->create('final_na_final.pdf', 50, 'application/pdf'),
            'recipients' => ['adviser'],
            'milestone_item_id' => $item->id,
        ])->assertOk()->assertJson(['success' => true]);

        $this->assertSame($item->id, (int) CapstoneDocument::first()->milestone_item_id);
        $this->assertSame(MilestoneProgress::DONE, $this->milestone->progress()->items->first()->state);
    }

    public function test_another_teams_line_is_refused(): void
    {
        $other = Team::create(['team_name' => 'Team Other', 'project_title' => 'Other', 'team_status' => 'Active']);
        $theirs = Milestone::create(['team_id' => $other->team_id, 'title' => 'Capstone 1', 'status' => 'todo'])
            ->items()->create(['title' => 'Chapter 1 Draft', 'kind' => MilestoneItem::DOCUMENT]);

        $this->send([
            'file' => UploadedFile::fake()->create('ch1.pdf', 50, 'application/pdf'),
            'recipients' => ['adviser'],
            'milestone_item_id' => $theirs->id,
        ])->assertStatus(422);

        $this->assertSame(0, CapstoneDocument::count());
    }

    /** A signed paper goes to the CaPSA to check, not to a reviewer - and a phone photo is fine. */
    public function test_a_photo_of_a_signed_form_needs_no_reviewer(): void
    {
        $form = $this->milestone->items()->create(['title' => 'Signed Title Defense Hearing Notice', 'kind' => MilestoneItem::FORM]);

        $this->send([
            'file' => UploadedFile::fake()->image('notice.jpg'),
            'milestone_item_id' => $form->id,
        ])->assertOk()->assertJson(['success' => true]);

        $this->assertSame(0, CapstoneDocumentReview::count());

        $state = $this->milestone->progress()->items->first();
        $this->assertSame(MilestoneProgress::HANDED_IN, $state->state);
        $this->assertSame(MilestoneProgress::WAITING_ON_CAPSA, $state->waiting_on);
    }

    public function test_a_photo_is_only_accepted_for_a_signed_form(): void
    {
        $draft = $this->milestone->items()->create(['title' => 'Chapter 1 Draft', 'kind' => MilestoneItem::DOCUMENT]);

        $this->send([
            'file' => UploadedFile::fake()->image('draft.jpg'),
            'recipients' => ['adviser'],
            'milestone_item_id' => $draft->id,
        ])->assertStatus(422);
    }

    public function test_anything_but_a_signed_form_still_needs_someone_to_send_it_to(): void
    {
        $draft = $this->milestone->items()->create(['title' => 'Chapter 1 Draft', 'kind' => MilestoneItem::DOCUMENT]);

        $this->send([
            'file' => UploadedFile::fake()->create('ch1.pdf', 50, 'application/pdf'),
            'milestone_item_id' => $draft->id,
        ])->assertStatus(422);

        $this->assertSame(0, CapstoneDocument::count());
    }

    /** A revision of the same line continues that line's version count, whatever the file is called. */
    public function test_a_resubmission_for_the_same_line_is_the_next_version(): void
    {
        $item = $this->milestone->items()->create(['title' => 'Business Process Analysis', 'kind' => MilestoneItem::DOCUMENT]);

        foreach (['bpa.pdf', 'bpa_revised_final.pdf'] as $name) {
            $this->send([
                'file' => UploadedFile::fake()->create($name, 50, 'application/pdf'),
                'recipients' => ['adviser'],
                'milestone_item_id' => $item->id,
            ])->assertOk();
        }

        $this->assertSame([1, 2], CapstoneDocument::orderBy('file_id')->pluck('file_version')->all());
    }

    /** Opened from the checklist, the form arrives with the line already picked. */
    public function test_the_form_opens_with_the_line_already_picked(): void
    {
        $item = $this->milestone->items()->create(['title' => 'Chapter 1 Draft', 'kind' => MilestoneItem::DOCUMENT]);

        $this->actingAs($this->student)
            ->get(route('student.submission', ['item' => $item->id]))
            ->assertOk()
            ->assertSee('Chapter 1 Draft')
            ->assertSee('value="'.$item->id.'" selected', false);
    }
}
