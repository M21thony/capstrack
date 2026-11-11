<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\ExpertRole;
use App\Models\Notification;
use App\Models\Role;
use App\Models\Team;
use App\Models\TeamExpertAssignment;
use App\Support\ExpertCapacity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsChecklists;
use Tests\TestCase;

/**
 * The panel's "cross-check teams and experts": per program and role, teams
 * still needing an expert against the slots experts have left - on the CaPSA/TSA
 * dashboard, and as a notice when a program runs short.
 */
class ExpertCapacityTest extends TestCase
{
    use BuildsChecklists;
    use RefreshDatabase;

    private function expert(string $roleName, string $programSlots, int $maxTeams, array $extra = []): CapstoneExpert
    {
        $expert = CapstoneExpert::factory()->create([
            'approved_status' => true, 'program_slots' => $programSlots, 'max_teams' => $maxTeams, ...$extra,
        ]);
        $expert->roles()->syncWithoutDetaching([Role::firstOrCreate(['role_name' => $roleName])->role_id]);

        return $expert;
    }

    private function assign(Team $team, CapstoneExpert $expert, string $roleName, string $status = 'approved'): void
    {
        TeamExpertAssignment::create([
            'team_id' => $team->team_id, 'expert_id' => $expert->expert_id, 'status' => $status,
            'expert_role_id' => ExpertRole::where('expert_id', $expert->expert_id)
                ->where('role_id', Role::where('role_name', $roleName)->value('role_id'))->value('expert_role_id'),
        ]);
    }

    /** @return array<string, array{role: string, label: string, need: int, free: int|null, short: int, status: string}> */
    private function bsit(): array
    {
        return collect(app(ExpertCapacity::class)->forPrograms(['BSIT'])['BSIT'])->keyBy('role')->all();
    }

    /** @return list<Team> */
    private function threeTeams(): array
    {
        return [$this->teamInSection('Alpha')[0], $this->teamInSection('Bravo')[0], $this->teamInSection('Charlie')[0]];
    }

    public function test_each_role_reads_ok_just_enough_or_short(): void
    {
        $this->threeTeams();
        $this->expert('adviser', '3 - BSIT', 3);
        $this->expert('adviser', '5 - BSIT', 5);
        $this->expert('consultant', '3 - BSIT', 3);
        $this->expert('consultant', '1 - BSIT', 1);
        $this->expert('grammarian', '2 - BSIS', 2);

        $rows = $this->bsit();

        $this->assertSame([3, 8, 0, ExpertCapacity::OK], [$rows['adviser']['need'], $rows['adviser']['free'], $rows['adviser']['short'], $rows['adviser']['status']]);
        $this->assertSame([3, 4, ExpertCapacity::OK], [$rows['consultant']['need'], $rows['consultant']['free'], $rows['consultant']['status']]);
        // The only grammarian takes BSIS teams, so BSIT has none.
        $this->assertSame([3, 0, 3, ExpertCapacity::SHORT], [$rows['grammarian']['need'], $rows['grammarian']['free'], $rows['grammarian']['short'], $rows['grammarian']['status']]);
        $this->assertSame(['BSIT needs 3 more grammarian slots.'], ExpertCapacity::shortages(['BSIT' => array_values($rows)]));
    }

    public function test_an_approved_expert_fills_the_need_and_a_pending_one_does_not(): void
    {
        [$alpha, $bravo] = $this->threeTeams();
        $adviser = $this->expert('adviser', '3 - BSIT', 3);
        $consultant = $this->expert('consultant', '3 - BSIT', 3);
        $this->assign($alpha, $adviser, 'adviser');
        $this->assign($bravo, $consultant, 'consultant', 'pending');

        $rows = $this->bsit();

        // Two teams still need an adviser and the adviser has two slots left.
        $this->assertSame([2, 2, ExpertCapacity::JUST_ENOUGH], [$rows['adviser']['need'], $rows['adviser']['free'], $rows['adviser']['status']]);
        $this->assertSame([3, 3], [$rows['consultant']['need'], $rows['consultant']['free']]);
    }

    public function test_experts_on_leave_or_without_slots_do_not_count_and_no_cap_means_no_limit(): void
    {
        $this->threeTeams();
        $this->expert('adviser', '9 - BSIT', 9, ['on_study_leave' => true]);
        $this->expert('consultant', '', 9);
        $this->expert('grammarian', 'any', 0);

        $rows = $this->bsit();

        $this->assertSame(0, $rows['adviser']['free']);
        $this->assertSame(3, $rows['adviser']['short']);
        $this->assertSame(0, $rows['consultant']['free']);
        $this->assertNull($rows['grammarian']['free']);
        $this->assertSame(ExpertCapacity::OK, $rows['grammarian']['status']);
    }

    public function test_the_all_roles_row_catches_an_expert_counted_under_two_roles(): void
    {
        $this->teamInSection('Alpha');
        $both = $this->expert('adviser', '1 - BSIT', 1);
        $both->roles()->syncWithoutDetaching([Role::firstOrCreate(['role_name' => 'consultant'])->role_id]);
        $this->expert('grammarian', '1 - BSIT', 1);

        $rows = $this->bsit();

        // Adviser and consultant each look covered by the same single slot.
        $this->assertSame(ExpertCapacity::JUST_ENOUGH, $rows['adviser']['status']);
        $this->assertSame(ExpertCapacity::JUST_ENOUGH, $rows['consultant']['status']);
        $this->assertSame([3, 2, 1], [$rows['all']['need'], $rows['all']['free'], $rows['all']['short']]);
    }

    public function test_the_capsa_is_told_once_and_again_only_when_the_shortage_changes(): void
    {
        $capsa = $this->capsaFor();
        $this->threeTeams();
        $adviser = $this->expert('adviser', '5 - BSIT', 5);
        $this->expert('consultant', '5 - BSIT', 5);
        $this->expert('grammarian', '1 - BSIT', 1);

        $this->artisan('experts:check-capacity')->assertSuccessful();
        $this->artisan('experts:check-capacity')->assertSuccessful();

        $notices = Notification::where('expert_id', $capsa->expert_id)->where('type', 'expert_slots_short')->pluck('message');
        $this->assertSame(['BSIT needs 2 more grammarian slots.'], $notices->all());

        $this->expert('grammarian', '1 - BSIT', 1);
        $this->artisan('experts:check-capacity')->assertSuccessful();

        $this->assertSame(
            'BSIT needs 1 more grammarian slot.',
            Notification::where('expert_id', $capsa->expert_id)->where('type', 'expert_slots_short')->latest('id')->value('message'),
        );
        $this->assertSame(0, Notification::where('expert_id', $adviser->expert_id)->where('type', 'expert_slots_short')->count());
    }

    public function test_no_notice_when_every_role_has_room(): void
    {
        $capsa = $this->capsaFor();
        $this->teamInSection('Alpha');
        $this->expert('adviser', '2 - BSIT', 2);
        $this->expert('consultant', '2 - BSIT', 2);
        $this->expert('grammarian', '2 - BSIT', 2);

        $this->artisan('experts:check-capacity')->assertSuccessful();

        $this->assertSame(0, Notification::where('expert_id', $capsa->expert_id)->where('type', 'expert_slots_short')->count());
    }

    public function test_the_capsa_dashboard_says_which_experts_are_short(): void
    {
        $capsa = $this->capsaFor();
        $this->threeTeams();
        $this->expert('adviser', '5 - BSIT', 5);
        $this->expert('consultant', '1 - BSIT', 1);
        $this->expert('grammarian', 'any', 0);

        $this->actingAs($capsa, 'expert')->get(route('capsa.dashboard'))
            ->assertOk()
            ->assertSeeInOrder(['Team &amp; Expert Coverage', 'BSIT · 2 consultants', 'Insufficient Experts', 'BSIT · Short by 2 consultants'], false);
    }

    public function test_the_coverage_tab_lists_what_each_team_still_needs_and_the_dashboard_links_to_it(): void
    {
        $capsa = $this->capsaFor();
        [$alpha, $bravo] = $this->threeTeams();
        $adviser = $this->expert('adviser', '5 - BSIT', 5);
        $this->assign($alpha, $adviser, 'adviser');

        $missing = app(ExpertCapacity::class)->teamsMissing(['BSIT']);
        $this->assertSame(['consultant', 'grammarian'], $missing[$alpha->team_id]['missing']);
        $this->assertSame(['adviser', 'consultant', 'grammarian'], $missing[$bravo->team_id]['missing']);

        $this->actingAs($capsa, 'expert')->get(route('capsa.experts', ['tab' => 'coverage']))
            ->assertOk()
            ->assertSee('aria-selected="true">Expert coverage', false)
            ->assertSee('1 of 9 assignments filled');

        $this->actingAs($capsa, 'expert')->get(route('capsa.dashboard'))
            ->assertSee('<a class="db-tile tile-green" href="'.route('capsa.experts', ['tab' => 'coverage']).'">', false);
    }
}
