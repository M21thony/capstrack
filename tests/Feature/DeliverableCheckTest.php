<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\Checklist;
use App\Models\Deliverable;
use App\Models\DeliverableCheck;
use App\Models\Student;
use App\Models\Team;
use App\Support\MilestoneProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsChecklists;
use Tests\TestCase;

/**
 * The CaPSA/TSA's check of a team's signed paper: complete, or sent back with a
 * note. A paper goes upload, sent back, upload again, complete.
 */
class DeliverableCheckTest extends TestCase
{
    use BuildsChecklists;
    use RefreshDatabase;

    private CapstoneExpert $capsa;

    private Team $team;

    private Student $student;

    private Checklist $checklist;

    protected function setUp(): void
    {
        parent::setUp();

        $this->capsa = $this->capsaFor();
        [$this->team, $this->student] = $this->teamInSection('CapsTrack');
        $this->checklist = $this->emptyChecklist($this->capsa);
        $this->actingAs($this->capsa, 'expert');
    }

    private function url(Deliverable $deliverable, ?Team $team = null): string
    {
        return route('capsa.deliverables.checks.update', [$deliverable->id, ($team ?? $this->team)->team_id]);
    }

    public function test_a_paper_goes_from_sent_back_to_complete(): void
    {
        $form = $this->addDeliverable($this->checklist, ['title' => 'Signed Title Defense Hearing Notice']);
        $this->upload($this->student, $form, now()->subHours(3)->toDateTimeString());

        $this->putJson($this->url($form), ['status' => DeliverableCheck::SENT_BACK])->assertStatus(422);

        $this->putJson($this->url($form), ['status' => DeliverableCheck::SENT_BACK, 'note' => 'The adviser has not signed yet.'])
            ->assertOk()
            ->assertJsonPath('waiting_on', MilestoneProgress::WAITING_ON_TEAM)
            ->assertJsonPath('note', 'The adviser has not signed yet.')
            ->assertJsonPath('to_check', false);

        $this->travel(1)->minutes();
        $this->upload($this->student, $form);
        $this->assertTrue($this->progressOf($this->checklist, $this->team, $form)->waiting_on === MilestoneProgress::WAITING_ON_CAPSA);

        $this->putJson($this->url($form), ['status' => DeliverableCheck::COMPLETE])
            ->assertOk()
            ->assertJsonPath('state', MilestoneProgress::DONE);

        $this->assertSame(1, DeliverableCheck::count());
    }

    /** A paper checked under last year's list is not asked for again under the list the team follows now. */
    public function test_work_done_under_another_list_stays_done(): void
    {
        $old = $this->addDeliverable($this->checklist, ['title' => 'Signed form']);
        $this->upload($this->student, $old);
        $this->putJson($this->url($old), ['status' => DeliverableCheck::COMPLETE])->assertOk();

        $now = $this->emptyChecklist($this->capsa);
        $same = $this->addDeliverable($now, ['title' => 'signed form ']);
        $new = $this->addDeliverable($now, ['title' => 'Something new']);

        $this->assertSame(MilestoneProgress::DONE, $this->progressOf($now, $this->team, $same)->state);
        $this->assertNotSame(MilestoneProgress::DONE, $this->progressOf($now, $this->team, $new)->state);
    }

    public function test_undo_takes_the_check_away(): void
    {
        $clf = $this->addDeliverable($this->checklist, ['title' => 'Signed CLF Approving Chapter 1', 'kind' => Deliverable::APPROVAL, 'role' => 'adviser']);
        $this->upload($this->student, $clf);

        $this->putJson($this->url($clf), ['status' => DeliverableCheck::COMPLETE])->assertOk();
        $this->deleteJson(route('capsa.deliverables.checks.destroy', [$clf->id, $this->team->team_id]))
            ->assertOk()
            ->assertJsonPath('waiting_on', MilestoneProgress::WAITING_ON_CAPSA);
    }

    public function test_a_defense_cannot_be_checked(): void
    {
        $defense = $this->addDeliverable($this->checklist, ['title' => 'Title Defense', 'kind' => Deliverable::DEFENSE, 'defense_type' => 'Title Defense']);

        $this->putJson($this->url($defense), ['status' => DeliverableCheck::COMPLETE])->assertStatus(422);
    }

    public function test_a_team_outside_her_sections_cannot_be_checked(): void
    {
        $form = $this->addDeliverable($this->checklist, ['title' => 'Signed form']);
        [$elsewhere] = $this->teamInSection('Elsewhere', 'Bachelor of Science in Information Technology', '3rd · B');

        $this->putJson($this->url($form, $elsewhere), ['status' => DeliverableCheck::COMPLETE])->assertForbidden();
    }

    public function test_a_section_loads_for_the_tracker_only_if_it_is_hers(): void
    {
        $form = $this->addDeliverable($this->checklist, ['title' => 'Signed form']);
        $this->upload($this->student, $form, path: 'submissions/form.png');

        $this->getJson(route('capsa.sections.progress', 'BSIT-3A'))
            ->assertOk()
            ->assertJsonPath('teams.0.name', 'CapsTrack')
            ->assertJsonPath('teams.0.rows.0.to_check', true)
            ->assertJsonPath('teams.0.rows.0.file_is_image', true);

        $this->getJson(route('capsa.sections.progress', 'BSIT-3B'))->assertForbidden();
    }
}
