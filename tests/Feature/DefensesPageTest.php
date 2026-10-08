<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\ExpertAwayDate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsChecklists;
use Tests\TestCase;

/**
 * Panel Assignment and Defense Scheduling are one Defenses page. Its two old
 * addresses still open it, each on its own step, and the sidebar has one link.
 */
class DefensesPageTest extends TestCase
{
    use BuildsChecklists;
    use RefreshDatabase;

    private CapstoneExpert $capsa;

    protected function setUp(): void
    {
        parent::setUp();
        $this->capsa = $this->capsaFor(['BSIT-3A']);
    }

    public function test_the_page_opens_on_the_teams_waiting_for_a_date(): void
    {
        $this->actingAs($this->capsa, 'expert')->get(route('capsa.defenses'))
            ->assertOk()
            ->assertViewIs('capsa.defenses')
            ->assertSee('<title>Defenses - CapsTRACK</title>', false)
            ->assertViewHas('state', fn ($state) => $state['step'] === 'ready' && $state['today'] === today()->toDateString());
    }

    public function test_the_old_addresses_open_their_own_step(): void
    {
        $this->actingAs($this->capsa, 'expert')->get(route('capsa.panel_assignment'))
            ->assertOk()->assertViewIs('capsa.defenses')->assertViewHas('state', fn ($state) => $state['step'] === 'panel');

        $this->actingAs($this->capsa, 'expert')->get(route('capsa.defense_scheduling'))
            ->assertOk()->assertViewIs('capsa.defenses')->assertViewHas('state', fn ($state) => $state['step'] === 'ready');
    }

    public function test_a_step_in_the_address_is_kept_and_a_made_up_one_is_not(): void
    {
        $this->actingAs($this->capsa, 'expert')->get(route('capsa.defenses', ['step' => 'scheduled']))
            ->assertViewHas('state', fn ($state) => $state['step'] === 'scheduled');

        $this->actingAs($this->capsa, 'expert')->get(route('capsa.defenses', ['step' => 'wizard']))
            ->assertViewHas('state', fn ($state) => $state['step'] === 'ready');

        // There is no Not ready step any more: an old link to it opens on Ready.
        $this->actingAs($this->capsa, 'expert')->get(route('capsa.defenses', ['step' => 'blocked']))
            ->assertViewHas('state', fn ($state) => $state['step'] === 'ready');
    }

    public function test_the_page_carries_the_days_panelists_are_away(): void
    {
        $away = ExpertAwayDate::factory()->create();
        ExpertAwayDate::factory()->create(['starts_on' => now()->subWeek(), 'ends_on' => now()->subDays(5)]);

        $this->actingAs($this->capsa, 'expert')->get(route('capsa.defenses'))
            ->assertViewHas('state', fn ($state) => array_column($state['away'], 'id') === [$away->id]);
    }

    public function test_one_sidebar_link_lit_on_every_address(): void
    {
        foreach (['capsa.defenses', 'capsa.panel_assignment', 'capsa.defense_scheduling'] as $route) {
            $html = $this->actingAs($this->capsa, 'expert')->get(route($route))->getContent();

            $this->assertSame(1, substr_count($html, 'href="'.route('capsa.defenses').'" class="nav-item active"'), $route);
            $this->assertStringNotContainsString('Panel Assignment', $html, $route);
        }
    }
}
