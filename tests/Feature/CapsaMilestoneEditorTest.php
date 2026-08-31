<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\Milestone;
use App\Models\MilestoneItem;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Support\MilestoneTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Creating and editing milestones on purpose.
 *
 * The feature this replaces could only make a milestone as a side effect of
 * posting an announcement, gave it no team, and offered no way to change or
 * delete it afterwards. These tests hold that line.
 */
class CapsaMilestoneEditorTest extends TestCase
{
    use RefreshDatabase;

    private const SECTION = 'BSIT-3A';

    private function section(): string
    {
        return self::SECTION;
    }

    private function makeCapsaWithSections(array $sections = [self::SECTION]): CapstoneExpert
    {
        $capsa = CapstoneExpert::factory()->create(['approved_status' => true, 'sections' => $sections]);
        $this->makeCapsa($capsa);
        $this->actingAs($capsa, 'expert');

        return $capsa;
    }

    /** @return array{0: Team, 1: Student} */
    private function makeTeamInSection(string $name): array
    {
        return $this->makeTeam($name, 'Bachelor of Science in Information Technology', '3rd · A');
    }

    private function makeTeamOutsideSection(string $name): Team
    {
        return $this->makeTeam($name, 'Bachelor of Science in Information Systems', '4th · B')[0];
    }

    /** @return array{0: Team, 1: Student} */
    private function makeTeam(string $name, string $course, string $yearSection): array
    {
        $leader = Student::factory()->withCompleteProfile()->create([
            'email' => strtolower(str_replace(' ', '', $name)).'@my.cspc.edu.ph',
            'course' => $course,
            'year_section' => $yearSection,
        ]);

        $team = Team::create([
            'team_name' => $name,
            'team_status' => 'Active',
            'leader_id' => $leader->student_id,
            'is_confirmed' => true,
            'member_count' => 5,
        ]);

        StudentSchoolYear::create(['student_id' => $leader->student_id, 'team_id' => $team->team_id]);

        return [$team, $leader];
    }

    public function test_a_section_gets_its_checklist(): void
    {
        $this->makeCapsaWithSections();
        [$team] = $this->makeTeamInSection('Team One');

        $response = $this->postJson(route('admin.milestones.store'), [
            'section' => $this->section(),
            'template' => MilestoneTemplate::CAPSTONE_1,
            'due_date' => now()->addMonth()->toDateString(),
        ]);

        $response->assertOk();

        $milestone = Milestone::where('team_id', $team->team_id)->first();
        $this->assertNotNull($milestone, 'it belongs to a team, unlike the old one');
        $this->assertSame('Capstone 1', $milestone->title);
        $this->assertSame(19, $milestone->items()->count());
        $response->assertJsonPath('data.0.total', 19);
        $response->assertJsonPath('data.0.completed', 0);
    }

    /** Every team in the section gets it, not just one. */
    public function test_a_checklist_can_be_given_to_a_whole_section_at_once(): void
    {
        $this->makeCapsaWithSections();
        [$a] = $this->makeTeamInSection('Team A');
        [$b] = $this->makeTeamInSection('Team B');

        $this->postJson(route('admin.milestones.store'), [
            'section' => $this->section(),
            'template' => MilestoneTemplate::CAPSTONE_1,
        ])->assertOk();

        foreach ([$a, $b] as $team) {
            $this->assertSame(19, Milestone::where('team_id', $team->team_id)->first()?->items()->count());
        }
    }

    /** Running the same section-wide create twice must not double everything. */
    public function test_creating_the_same_checklist_twice_does_not_duplicate_it(): void
    {
        $this->makeCapsaWithSections();
        [$team] = $this->makeTeamInSection('Team One');

        $payload = [
            'section' => $this->section(),
            'template' => MilestoneTemplate::CAPSTONE_1,
        ];

        $this->postJson(route('admin.milestones.store'), $payload)->assertOk();
        $this->postJson(route('admin.milestones.store'), $payload)->assertOk();

        $this->assertSame(1, Milestone::where('team_id', $team->team_id)->count());
        $this->assertSame(19, Milestone::where('team_id', $team->team_id)->first()->items()->count());
    }

    /** Capstone 2 has no template yet, so a CaPSA builds one by hand. */
    public function test_a_milestone_can_be_built_from_nothing(): void
    {
        $this->makeCapsaWithSections();
        [$team] = $this->makeTeamInSection('Team One');

        $this->postJson(route('admin.milestones.store'), [
            'section' => $this->section(),
            'title' => 'Capstone 2',
        ])->assertOk();

        $milestone = Milestone::where('team_id', $team->team_id)->first();
        $this->assertSame(0, $milestone->items()->count());

        $this->postJson(route('admin.milestones.items.store', $milestone), [
            'title' => 'Signed Something Form',
        ])->assertOk();

        $item = $milestone->fresh()->items()->first();
        $this->assertSame(MilestoneItem::CUSTOM, $item->kind);
    }

    public function test_a_milestone_can_be_renamed_re_dated_and_deleted(): void
    {
        $this->makeCapsaWithSections();
        [$team] = $this->makeTeamInSection('Team One');
        $milestone = Milestone::create(['team_id' => $team->team_id, 'title' => 'Draft', 'status' => 'todo']);

        $this->putJson(route('admin.milestones.update', $milestone), [
            'title' => 'Capstone 1',
            'due_date' => now()->addWeeks(2)->toDateString(),
        ])->assertOk();

        $this->assertSame('Capstone 1', $milestone->fresh()->title);
        $this->assertNotNull($milestone->fresh()->due_date);

        $this->deleteJson(route('admin.milestones.destroy', $milestone))->assertOk();
        $this->assertNull(Milestone::find($milestone->id));
    }

    public function test_ticking_a_form_carries_the_date_it_was_accomplished(): void
    {
        $this->makeCapsaWithSections();
        [$team] = $this->makeTeamInSection('Team One');
        $milestone = Milestone::create(['team_id' => $team->team_id, 'title' => 'Capstone 1', 'status' => 'todo']);
        $item = $milestone->items()->create(['title' => 'Signed Form', 'kind' => MilestoneItem::FORM]);

        $this->postJson(route('admin.milestones.items.toggle', $item), [
            'done' => true,
            'completed_at' => '2026-08-14',
        ])->assertOk()->assertJsonPath('data.completed', 1);

        $this->assertSame('2026-08-14', $item->fresh()->completed_at->toDateString());

        $this->postJson(route('admin.milestones.items.toggle', $item), ['done' => false])->assertOk();
        $this->assertNull($item->fresh()->completed_at);
    }

    /**
     * A draft, a CLF and a defense are answered by the upload, the verdict
     * and the panel. Letting a CaPSA tick them would put a second answer next
     * to the real one, and the two would disagree.
     */
    public function test_the_kinds_that_close_themselves_cannot_be_ticked_by_hand(): void
    {
        $this->makeCapsaWithSections();
        [$team] = $this->makeTeamInSection('Team One');
        $milestone = Milestone::create(['team_id' => $team->team_id, 'title' => 'Capstone 1', 'status' => 'todo']);

        foreach ([MilestoneItem::DOCUMENT, MilestoneItem::APPROVAL, MilestoneItem::DEFENSE] as $kind) {
            $item = $milestone->items()->create(['title' => "A {$kind}", 'kind' => $kind]);

            $this->postJson(route('admin.milestones.items.toggle', $item), ['done' => true])
                ->assertStatus(422);

            $this->assertNull($item->fresh()->completed_at);
        }
    }

    public function test_items_can_be_reordered_and_removed(): void
    {
        $this->makeCapsaWithSections();
        [$team] = $this->makeTeamInSection('Team One');
        $milestone = Milestone::create(['team_id' => $team->team_id, 'title' => 'Capstone 1', 'status' => 'todo']);

        $ids = collect(['First', 'Second', 'Third'])
            ->map(fn ($t, $i) => $milestone->items()->create(['title' => $t, 'kind' => MilestoneItem::FORM, 'position' => $i])->id)
            ->all();

        $this->postJson(route('admin.milestones.items.reorder', $milestone), [
            'item_ids' => [$ids[2], $ids[0], $ids[1]],
        ])->assertOk();

        $this->assertSame(
            ['Third', 'First', 'Second'],
            $milestone->fresh()->items()->orderBy('position')->pluck('title')->all(),
        );

        $this->deleteJson(route('admin.milestones.items.destroy', $ids[0]))->assertOk();
        $this->assertSame(2, $milestone->fresh()->items()->count());
    }

    /**
     * The tab renders as a grid: deliverables across, teams down.
     */
    public function test_the_milestones_tab_renders_the_grid(): void
    {
        $this->makeCapsaWithSections();
        [$team] = $this->makeTeamInSection('Team One');

        $this->postJson(route('admin.milestones.store'), [
            'section' => $this->section(),
            'template' => MilestoneTemplate::CAPSTONE_1,
        ])->assertOk();

        $response = $this->get(route('admin.team_management', ['tab' => 'milestones']));
        $response->assertOk();

        // The team is a row, with its score.
        $response->assertSee('Team One');
        $response->assertSee('0/19');

        // Columns carry the short label, with the paper's full wording kept
        // on the cell so the exact name is never lost.
        $response->assertSee('Team Assignment');
        $response->assertSee('Approved CLF');
        $response->assertSee('Signed CLF Approving Chapter 1 (CaPA/Adviser)', false);

        // Grouped by phase, and the narrow-screen tabs are rendered for them.
        $response->assertSee('Getting started');
        $response->assertSee('Title Defense');
        $response->assertSee('ms-ptab', false);
    }

    /**
     * Only the eight forms are pressable. A draft, a CLF and a defense are
     * answered elsewhere, so they must not look like something to click.
     */
    public function test_only_the_hand_ticked_lines_are_buttons_in_the_grid(): void
    {
        $this->makeCapsaWithSections();
        [$team] = $this->makeTeamInSection('Team One');

        $this->postJson(route('admin.milestones.store'), [
            'section' => $this->section(),
            'template' => MilestoneTemplate::CAPSTONE_1,
        ])->assertOk();

        $html = $this->get(route('admin.team_management', ['tab' => 'milestones']))->getContent();

        // Counted inside the table body only - the key at the foot draws one
        // of each mark to explain them, and those are not cells.
        $body = substr($html, strpos($html, '<tbody>'), strpos($html, '</tbody>') - strpos($html, '<tbody>'));

        $this->assertSame(8, substr_count($body, '<button'), 'the eight forms are pressable');
        $this->assertSame(11, substr_count($body, 'ms-mk auto'), 'the other eleven are not');
    }

    /** A CaPSA has no business touching a team outside her own sections. */
    public function test_a_capsa_cannot_touch_another_sections_milestone(): void
    {
        $this->makeCapsaWithSections();
        $otherTeam = $this->makeTeamOutsideSection('Not Mine');
        $milestone = Milestone::create(['team_id' => $otherTeam->team_id, 'title' => 'Capstone 1', 'status' => 'todo']);

        $this->putJson(route('admin.milestones.update', $milestone), ['title' => 'Hijacked'])->assertStatus(403);
        $this->deleteJson(route('admin.milestones.destroy', $milestone))->assertStatus(403);

        $this->assertSame('Capstone 1', $milestone->fresh()->title);
    }
}
