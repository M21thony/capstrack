<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\CapstonePreProposal;
use App\Models\DefenseSchedule;
use App\Models\DeliverableCheck;
use App\Models\DuplicationCheck;
use App\Models\Notification;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Models\TeamExpertAssignment;
use App\Models\TitleChangeRequest;
use App\Support\DefenseOutcome;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * A team whose title has passed asks its adviser to change part of it. The
 * leader asks with a reason; the title changes only once the adviser approves.
 */
class TitleChangeRequestTest extends TestCase
{
    use RefreshDatabase;

    private const TITLE = 'CapsTrack Centralized Capstone Project Management System';

    private const CHANGED = 'CapsTrack Centralized Capstone Project Management System for CSPC';

    private Team $team;

    private Student $leader;

    private Student $member;

    private CapstoneExpert $adviser;

    private CapstonePreProposal $proposal;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();

        $this->leader = Student::factory()->withCompleteProfile()->withTourCompleted()->create();
        $this->member = Student::factory()->withCompleteProfile()->withTourCompleted()->create();
        $this->team = Team::create(['team_name' => 'Bulbasaur', 'team_status' => 'Active', 'is_confirmed' => true, 'leader_id' => $this->leader->student_id, 'project_title' => self::TITLE]);
        $sy = StudentSchoolYear::create(['student_id' => $this->leader->student_id, 'team_id' => $this->team->team_id]);
        StudentSchoolYear::create(['student_id' => $this->member->student_id, 'team_id' => $this->team->team_id]);

        $check = DuplicationCheck::create([
            'student_s_y_id' => $sy->s_y_id, 'document_path' => 'checks/1.pdf', 'concepts' => [],
            'top_score' => 10, 'verdict' => 'Passed', 'matches' => [], 'instructors_suggested' => [],
        ]);
        $this->proposal = CapstonePreProposal::create([
            'team_id' => $this->team->team_id, 'duplication_check_id' => $check->id, 'slot' => 1, 'title' => self::TITLE,
            'sdg_goals' => 'SDG 4', 'research_agenda' => ['Applications and Systems Development'],
            'problem_statement' => 'p', 'proposed_solution' => 's', 'scope' => 'sc', 'limitations' => 'l', 'sdm' => 'Agile',
            'approved_status' => true, 'submitted_at' => now(),
        ]);
        $this->team->update(['selected_pre_proposal_id' => $this->proposal->id]);

        $this->adviser = $this->expert('adviser');
        TeamExpertAssignment::create([
            'team_id' => $this->team->team_id, 'expert_id' => $this->adviser->expert_id, 'status' => 'approved',
            'expert_role_id' => DB::table('expert_roles')->where('expert_id', $this->adviser->expert_id)->value('expert_role_id'),
        ]);
    }

    private function expert(string $role): CapstoneExpert
    {
        $expert = CapstoneExpert::factory()->create(['approved_status' => true, 'first_name' => 'Ada', 'last_name' => 'Adviser']);
        $expert->roles()->attach(Role::firstOrCreate(['role_name' => $role])->role_id);

        return $expert;
    }

    private function passed(string $outcome = DefenseOutcome::PASSED): void
    {
        DefenseSchedule::create([
            'team_id' => $this->team->team_id, 'defense_type' => DefenseOutcome::PRE_PROPOSAL, 'attempt' => 1,
            'outcome' => $outcome, 'outcome_recorded_at' => now()->subHour(),
        ]);
    }

    private function ask(?Student $who = null, array $data = [])
    {
        return $this->actingAs($who ?? $this->leader, 'web')
            ->post(route('student.pre-proposal.title-change.store'), $data + ['new_title' => self::CHANGED, 'reason' => 'The panel asked us to name the client.']);
    }

    private function decide(TitleChangeRequest $change, string $decision, ?string $note = null, ?CapstoneExpert $who = null)
    {
        return $this->actingAs($who ?? $this->adviser, 'expert')
            ->patch(route('instructor.title-changes.update', $change), array_filter(['decision' => $decision, 'note' => $note]));
    }

    public function test_the_leader_of_a_passed_team_asks_and_the_title_waits_for_the_adviser(): void
    {
        $this->passed();

        $this->ask()->assertRedirect(route('student.duplication'))->assertSessionHas('status');

        $change = TitleChangeRequest::sole();
        $this->assertSame(TitleChangeRequest::PENDING, $change->status);
        $this->assertSame(self::TITLE, $change->current_title);
        $this->assertSame(self::CHANGED, $change->new_title);
        $this->assertSame(self::TITLE, $this->team->fresh()->project_title, 'nothing changes before the adviser approves');
        $this->assertTrue(Notification::where('expert_id', $this->adviser->expert_id)->where('type', 'title_change_requested')->exists());
    }

    public function test_the_adviser_approving_changes_the_teams_title(): void
    {
        $this->passed();
        $this->ask();

        $this->decide(TitleChangeRequest::sole(), 'approve')->assertRedirect(route('instructor.teams', ['tab' => 'todo']));

        $this->assertSame(self::CHANGED, $this->team->fresh()->project_title);
        $this->assertSame(TitleChangeRequest::APPROVED, TitleChangeRequest::sole()->status);
        $this->assertSame(self::TITLE, $this->proposal->fresh()->title, 'the pre-proposal keeps the title the panel passed');
        $this->assertSame(2, Notification::where('type', 'title_confirmation')->where('title', 'Title change approved')->count(), 'both members are told');
    }

    public function test_declining_needs_a_note_and_keeps_the_title(): void
    {
        $this->passed();
        $this->ask();
        $change = TitleChangeRequest::sole();

        $this->decide($change, 'decline')->assertSessionHasErrors('note');
        $this->assertSame(TitleChangeRequest::PENDING, $change->fresh()->status);

        $this->decide($change, 'decline', 'Keep it; the client is named in the scope.')->assertRedirect(route('instructor.teams', ['tab' => 'todo']));
        $this->assertSame(TitleChangeRequest::DECLINED, $change->fresh()->status);
        $this->assertSame(self::TITLE, $this->team->fresh()->project_title);
    }

    public function test_only_the_teams_adviser_decides(): void
    {
        $this->passed();
        $this->ask();

        $this->decide(TitleChangeRequest::sole(), 'approve', null, $this->expert('adviser'))->assertNotFound();
        $this->assertSame(self::TITLE, $this->team->fresh()->project_title);
    }

    public function test_only_the_leader_asks(): void
    {
        $this->passed();

        $this->ask($this->member)->assertSessionHas('error', 'Only the team leader can ask to change the title.');
        $this->assertSame(0, TitleChangeRequest::count());
    }

    public function test_a_title_not_yet_passed_cannot_be_changed_this_way(): void
    {
        $this->ask()->assertSessionHas('error', 'You can ask to change your title once your panel has passed it.');

        $this->passed(DefenseOutcome::REDEFENSE);
        $this->ask()->assertSessionHas('error', 'You can ask to change your title once your panel has passed it.');
        $this->assertSame(0, TitleChangeRequest::count());
    }

    public function test_passed_with_revision_is_still_reworded_by_the_leader_directly(): void
    {
        $this->passed(DefenseOutcome::PASSED_WITH_REVISION);

        $this->ask()->assertSessionHas('error', 'You can still reword your title yourself until the Signed Final Project Title Form is in.');
        $this->assertSame(0, TitleChangeRequest::count());
    }

    public function test_one_request_at_a_time(): void
    {
        $this->passed();
        $this->ask();

        $this->ask(null, ['new_title' => self::CHANGED.' Students'])->assertSessionHas('error', 'Your adviser is still deciding on the change you asked for.');
        $this->assertSame(1, TitleChangeRequest::count());
    }

    public function test_the_new_title_must_differ_and_follow_the_title_rules(): void
    {
        $this->passed();

        $this->ask(null, ['new_title' => '  capstrack centralized  capstone project management system '])->assertSessionHasErrors('new_title');
        $this->ask(null, ['new_title' => implode(' ', array_fill(0, 16, 'Tracking'))])->assertSessionHasErrors('new_title');
        $this->ask(null, ['reason' => 'short'])->assertSessionHasErrors('reason');
        $this->assertSame(0, TitleChangeRequest::count());
    }

    public function test_the_adviser_sees_a_request_in_to_do(): void
    {
        $this->passed();


        $this->ask();


        // In the adviser's To do, with the title as it is and as it would be.
        $this->actingAs($this->adviser, 'expert')->get(route('instructor.teams', ['tab' => 'todo']))
            ->assertOk()->assertSee('Approve a title change')->assertSee(self::CHANGED)->assertSee('The panel asked us to name the client.')
            ->assertSee('<dl class="tc-diff">', false);
    }

    private function capsaVerifiesFinalTitleForm(): void
    {
        $capsa = CapstoneExpert::factory()->create(['approved_status' => true]);
        $checklist = DB::table('checklists')->insertGetId(['capsa_id' => $capsa->expert_id, 'program' => 'BSIT', 'title' => 'Capstone 1', 'created_at' => now(), 'updated_at' => now()]);
        $milestone = DB::table('checklist_milestones')->insertGetId(['checklist_id' => $checklist, 'name' => 'Pre-proposal', 'position' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $form = DB::table('deliverables')->insertGetId(['checklist_milestone_id' => $milestone, 'template_key' => 'final_title_form', 'title' => 'Signed Final Project Title Form', 'kind' => 'form', 'position' => 1, 'created_at' => now(), 'updated_at' => now()]);
        DeliverableCheck::create(['deliverable_id' => $form, 'team_id' => $this->team->team_id, 'status' => DeliverableCheck::COMPLETE, 'checked_at' => now()]);
    }

    public function test_once_the_capsa_verifies_the_final_title_form_the_title_is_final(): void
    {
        $this->passed();
        $this->capsaVerifiesFinalTitleForm();

        $this->ask()->assertSessionHas('error', 'Your CaPSA has verified your Signed Final Project Title Form, so the title is final.');
        $this->assertSame(0, TitleChangeRequest::count());
    }

    public function test_a_request_still_waiting_when_the_form_is_verified_cannot_be_approved(): void
    {
        $this->passed();
        $this->ask();
        $this->capsaVerifiesFinalTitleForm();

        $this->decide(TitleChangeRequest::sole(), 'approve')->assertSessionHasErrors('note');

        $this->assertSame(self::TITLE, $this->team->fresh()->project_title);
        $this->assertSame(TitleChangeRequest::PENDING, TitleChangeRequest::sole()->status, 'the adviser can still decline it with a reason');
    }
}
