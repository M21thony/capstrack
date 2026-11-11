<?php

namespace Tests\Feature;

use App\Models\CapstoneDocumentReview;
use App\Models\CapstoneExpert;
use App\Models\Checklist;
use App\Models\DeliverableCheck;
use App\Models\Student;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsChecklists;
use Tests\TestCase;

/** What a team sees of its own milestones. */
class StudentChecklistTest extends TestCase
{
    use BuildsChecklists;
    use RefreshDatabase;

    private Student $student;

    private Team $team;

    private CapstoneExpert $capsa;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->team, $this->student] = $this->teamInSection('Checklist Team');
        $this->capsa = $this->capsaFor();
        $this->actingAs($this->student, 'web');
    }

    private function checklist(): Checklist
    {
        return $this->departmentChecklist($this->capsa);
    }

    public function test_a_team_sees_its_own_milestones(): void
    {
        $this->checklist();

        $this->get('/student/progress')
            ->assertOk()
            ->assertSee('Checklist Team')
            ->assertSee('0 of 48 closed')
            ->assertSee('Pre-proposal Defense')
            ->assertSee('Signed Project Team Assignment Form')
            ->assertSee('Chapter 1 Draft');
    }

    /** The CaPSA/TSA's description shows under the milestone. */
    public function test_a_milestone_shows_its_due_date_past_due_and_description(): void
    {
        $milestone = $this->checklist()->milestones()->where('name', 'Pre-proposal Defense')->first();
        $milestone->update([
            'description' => 'Form your team and send the signed assignment form.',
            'due_on' => now()->subDays(2)->toDateString(),
        ]);

        $this->get('/student/progress')
            ->assertOk()
            ->assertSee('Form your team and send the signed assignment form.')
            ->assertSee('Due '.now()->subDays(2)->format('M j, Y'))
            ->assertSee('Past due');

        $milestone->update(['closed_at' => now()]);

        $this->get('/student/progress')->assertOk()->assertDontSee('Past due');
    }

    /** The CaPSA/TSA checking a signed form is what the team sees change. */
    public function test_a_form_the_capsa_checked_shows_as_closed(): void
    {
        $form = $this->line($this->checklist(), 'team_assignment_form');
        DeliverableCheck::create(['deliverable_id' => $form->id, 'team_id' => $this->team->team_id, 'status' => DeliverableCheck::COMPLETE, 'checked_at' => now()->subDays(3)]);

        $this->get('/student/progress')->assertOk()->assertSee('1 of 48 closed');
    }

    /** The question a team has is who is holding a thing up. */
    public function test_a_clf_names_who_is_holding_it(): void
    {
        $checklist = $this->checklist();

        CapstoneDocumentReview::create([
            'file_id' => $this->upload($this->student, $this->line($checklist, 'chapter_1_draft')),
            'expert_id' => CapstoneExpert::factory()->create(['approved_status' => true])->expert_id,
            'role' => 'adviser',
            'review_status' => CapstoneDocumentReview::REVIEWED,
        ]);

        $this->get('/student/progress')
            ->assertOk()
            ->assertSee('With your adviser')
            // and the draft is not closed until adviser and consultant approve it
            ->assertSee('0 of 48 closed');
    }

    public function test_a_paper_sent_back_shows_the_capsas_note(): void
    {
        $form = $this->line($this->checklist(), 'team_assignment_form');
        $this->upload($this->student, $form, now()->subDays(2)->toDateTimeString());
        DeliverableCheck::create([
            'deliverable_id' => $form->id, 'team_id' => $this->team->team_id, 'status' => DeliverableCheck::SENT_BACK,
            'note' => 'Two members have not signed.', 'checked_at' => now()->subDay(),
        ]);

        $this->get('/student/progress')
            ->assertOk()
            ->assertSee('Sent back by your CaPSA/TSA')
            ->assertSee('Two members have not signed.')
            ->assertSee('Replace file');
    }

    public function test_archived_deliverables_are_not_shown(): void
    {
        $this->line($this->checklist(), 'td_rsc')->update(['archived_at' => now()]);

        $this->get('/student/progress')
            ->assertOk()
            ->assertDontSee('Signed (Noted) TD RSC')
            ->assertSee('0 of 47 closed');
    }

    public function test_a_team_with_no_checklist_is_told_so(): void
    {
        $this->get('/student/progress')
            ->assertOk()
            ->assertSee('Your CaPSA/TSA has not set up a checklist for your team yet.');
    }

    /** Nothing on the tracker is the team's to close, so there is nothing here to mark done. */
    public function test_the_tracker_is_read_only(): void
    {
        $this->checklist();

        $this->get('/student/progress')
            ->assertOk()
            ->assertDontSee('Mark as Done')
            ->assertDontSee('openUploadModal');
    }
}
