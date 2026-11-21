<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\Setting;
use App\Models\StudentSchoolYear;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsChecklists;
use Tests\TestCase;

/**
 * The admin (dean) reads the CaPSA's Reports for any school year, across every
 * section, without the CaPSA-only "Open team" link.
 */
class AdminReportsTest extends TestCase
{
    use BuildsChecklists;
    use RefreshDatabase;

    public function test_the_admin_opens_a_past_year_across_every_section(): void
    {
        $admin = CapstoneExpert::factory()->create(['approved_status' => true, 'is_admin' => true]);
        [$it] = $this->teamInSection('Alpha');
        [$cs] = $this->teamInSection('Beta', 'Bachelor of Science in Computer Science', '4th · B');
        StudentSchoolYear::whereIn('team_id', [$it->team_id, $cs->team_id])->update(['school_year' => '2024-2025']);
        Setting::set('sections:2024-2025', json_encode(['BSIT-3A', 'BSCS-4B']));

        $this->actingAs($admin, 'expert')
            ->get(route('admin.settings'))
            ->assertOk()
            ->assertSee('href="'.route('capsa.reports', ['year' => '2024-2025']).'"', false);

        // A year with sections but no students left in it still opens as itself.
        Setting::set('sections:2023-2024', json_encode(['BSIT-3A']));
        $this->get(route('capsa.reports', ['year' => '2023-2024']))
            ->assertOk()
            ->assertViewHas('ctx', fn ($ctx) => $ctx['year'] === '2023-2024');

        $this->get(route('capsa.reports', ['year' => '2024-2025']))
            ->assertOk()
            ->assertSee('href="'.route('capsa.reports').'"', false);

        $this->get(route('capsa.reports.show', ['report' => 'progress', 'year' => '2024-2025']))
            ->assertOk()
            ->assertSee('Alpha')
            ->assertSee('Beta')
            ->assertViewHas('details', fn ($d) => $d[$it->team_id]['url'] === null);

        $this->get(route('capsa.reports.section', 'BSCS-4B'))->assertRedirect();
    }

    public function test_a_plain_adviser_still_cannot_open_reports(): void
    {
        $adviser = CapstoneExpert::factory()->create(['approved_status' => true]);

        $this->actingAs($adviser, 'expert')->get(route('capsa.reports'))->assertForbidden();
    }
}
