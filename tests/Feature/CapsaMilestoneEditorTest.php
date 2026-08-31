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

    public function test_a_milestone_is_created_for_a_team_with_the_checklist_on_it(): void
    {
        $this->makeCapsaWithSections();
        [$team] = $this->makeTeamInSection('Team One');

        $response = $this->postJson(route('admin.milestones.store'), [
            'scope' => 'team',
            'team_id' => $team->team_id,
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

    /**
     * The unit of work that matters: every team in a section works from the
     * same checklist, so it is made for the section in one go.
     */
    public function test_a_checklist_can_be_given_to_a_whole_section_at_once(): void
    {
        $this->makeCapsaWithSections();
        [$a] = $this->makeTeamInSection('Team A');
        [$b] = $this->makeTeamInSection('Team B');

        $this->postJson(route('admin.milestones.store'), [
            'scope' => 'section',
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
            'scope' => 'section',
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
            'scope' => 'team',
            'team_id' => $team->team_id,
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
     * The tab renders, and shows what each line is waiting on rather than a
     * bare unticked box.
     */
    public function test_the_milestones_tab_renders_the_checklist(): void
    {
        $this->makeCapsaWithSections();
        [$team] = $this->makeTeamInSection('Team One');

        $this->postJson(route('admin.milestones.store'), [
            'scope' => 'team',
            'team_id' => $team->team_id,
            'template' => MilestoneTemplate::CAPSTONE_1,
        ])->assertOk();

        $response = $this->get(route('admin.team_management', ['tab' => 'milestones']));

        $response->assertOk();
        $response->assertSee('Team One');
        $response->assertSee('Capstone 1');
        $response->assertSee('0 of 19 complete');
        $response->assertSee('Signed Project Team Assignment Form');
        $response->assertSee('Signed CLF Approving Chapter 1 (CaPA/Adviser)');
        // The kinds are labelled, so it is obvious which ones close themselves.
        $response->assertSee('approval');
        $response->assertSee('document');
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
