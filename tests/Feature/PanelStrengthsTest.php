<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\Role;
use App\Support\DefenseBoard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsChecklists;
use Tests\TestCase;

/**
 * Panel comment 15: an expert ticks what they are strongest in as a panelist
 * - manuscript, system design, software development - on their profile, and
 * the CaPSA/TSA's Assign panel shows it so a panel can cover all three.
 */
class PanelStrengthsTest extends TestCase
{
    use BuildsChecklists;
    use RefreshDatabase;

    private function expert(array $strengths = []): CapstoneExpert
    {
        $expert = CapstoneExpert::factory()->create([
            'approved_status' => true, 'max_teams' => 4, 'program_slots' => '4 - BSIT',
            'first_login_at' => now(), 'panel_strengths' => $strengths ?: null,
        ]);
        $expert->roles()->attach(Role::firstOrCreate(['role_name' => 'adviser'])->role_id);

        return $expert;
    }

    private function save(CapstoneExpert $expert, array $extra = [])
    {
        return $this->actingAs($expert, 'expert')->putJson(route('instructor.profile.update'), [
            'program_slots' => ['BSIT' => 4],
            'expertise_by_side' => ['adviser' => ['Databases']],
        ] + $extra);
    }

    public function test_an_expert_ticks_their_strengths_and_they_are_kept_in_order(): void
    {
        $expert = $this->expert();

        $this->save($expert, ['panel_strengths' => ['software', 'manuscript']])
            ->assertOk()->assertJson(['success' => true]);

        $this->assertSame(['manuscript', 'software'], $expert->fresh()->panel_strengths);
    }

    public function test_a_page_that_does_not_send_them_leaves_them_and_an_empty_list_clears_them(): void
    {
        $expert = $this->expert(['design']);

        $this->save($expert)->assertOk();
        $this->assertSame(['design'], $expert->fresh()->panel_strengths);

        $this->save($expert, ['panel_strengths' => []])->assertOk();
        $this->assertSame([], $expert->fresh()->panel_strengths);
    }

    public function test_a_made_up_strength_is_refused_and_nothing_changes(): void
    {
        $expert = $this->expert(['manuscript']);

        $this->save($expert, ['panel_strengths' => ['design', 'cooking']])
            ->assertStatus(422)->assertJson(['success' => false]);

        $this->assertSame(['manuscript'], $expert->fresh()->panel_strengths);
    }

    public function test_the_profile_offers_the_three_with_the_saved_ones_ticked(): void
    {
        $expert = $this->expert(['design']);

        $this->actingAs($expert, 'expert')->get(route('instructor.profile'))->assertOk()
            ->assertSee('Review strengths', false)
            ->assertSee('data-strength="manuscript"', false)
            ->assertSee('data-strength="design"', false)
            ->assertSee('data-strength="software"', false)
            ->assertSee('Diagrams, database, architecture')
            ->assertSee('strengths: ["design"]', false);
    }

    public function test_an_expert_sets_how_many_teams_panels_they_sit_on_and_blank_is_no_limit(): void
    {
        $expert = $this->expert();

        $this->save($expert, ['max_panels' => 3])->assertOk();
        $this->assertSame(3, $expert->fresh()->max_panels);

        $this->save($expert)->assertOk();
        $this->assertSame(3, $expert->fresh()->max_panels, 'A page that does not send it leaves it.');

        $this->save($expert, ['max_panels' => 0])->assertStatus(422);
        $this->save($expert, ['max_panels' => 31])->assertStatus(422);
        $this->assertSame(3, $expert->fresh()->max_panels);

        $this->save($expert, ['max_panels' => null])->assertOk();
        $this->assertNull($expert->fresh()->max_panels);

        $this->actingAs($expert, 'expert')->get(route('instructor.profile'))->assertOk()
            ->assertSee('Panel preferences')->assertSee('id="pp-max-panels"', false);
    }

    public function test_the_assign_panel_gets_each_experts_strengths_and_the_three_names(): void
    {
        $strong = $this->expert(['software', 'design']);
        $blank = $this->expert();

        $panelists = app(DefenseBoard::class)->panelists()->keyBy('id');
        $this->assertSame(['design', 'software'], $panelists[$strong->expert_id]['strengths']);
        $this->assertSame([], $panelists[$blank->expert_id]['strengths']);

        $this->actingAs($this->capsaFor(['BSIT-3A']), 'expert')->get(route('capsa.defenses'))->assertOk()
            ->assertViewHas('state', fn ($state) => $state['strengths'] === CapstoneExpert::STRENGTHS
                && collect($state['experts'])->firstWhere('id', $strong->expert_id)['strengths'] === ['design', 'software']);
    }
}
