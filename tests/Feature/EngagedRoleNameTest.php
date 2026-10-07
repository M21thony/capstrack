<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\ExpertRole;
use App\Models\Role;
use App\Models\TeamExpertAssignment;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsChecklists;
use Tests\TestCase;

/**
 * The role a team engaged an expert as is read many times on one page - the
 * profile panel alone reads it nine times - so it is fetched once per
 * assignment and kept, not queried again on every read.
 */
class EngagedRoleNameTest extends TestCase
{
    use BuildsChecklists;
    use RefreshDatabase;

    private function assignmentAs(string $roleName, bool $withEngagedRole = true): TeamExpertAssignment
    {
        $this->capsaFor();
        [$team] = $this->teamInSection('Alpha');
        $expert = CapstoneExpert::factory()->create(['approved_status' => true]);
        $role = Role::firstOrCreate(['role_name' => $roleName]);
        $expert->roles()->attach($role->role_id);

        return TeamExpertAssignment::create([
            'team_id' => $team->team_id,
            'expert_id' => $expert->expert_id,
            'status' => 'approved',
            'expert_role_id' => $withEngagedRole
                ? ExpertRole::where('expert_id', $expert->expert_id)->where('role_id', $role->role_id)->value('expert_role_id')
                : null,
        ])->fresh();
    }

    private function expertRoleQueriesDuring(callable $reads): int
    {
        $count = 0;
        DB::listen(function (QueryExecuted $query) use (&$count): void {
            if (str_contains($query->sql, '"expert_roles"')) {
                $count++;
            }
        });

        $reads();

        return $count;
    }

    public function test_reading_the_engaged_role_again_does_not_query_again(): void
    {
        $assignment = $this->assignmentAs('adviser');

        $names = [];
        $queries = $this->expertRoleQueriesDuring(function () use ($assignment, &$names): void {
            foreach (range(1, 9) as $read) {
                $names[] = $assignment->engaged_role_name;
            }
        });

        $this->assertSame(array_fill(0, 9, 'adviser'), $names);
        $this->assertSame(1, $queries);
    }

    /** Pointed at another engagement, the name follows it rather than the kept one. */
    public function test_a_changed_engagement_is_read_afresh(): void
    {
        $assignment = $this->assignmentAs('adviser');
        $this->assertSame('adviser', $assignment->engaged_role_name);

        $consultant = Role::firstOrCreate(['role_name' => 'consultant']);
        $assignment->expert->roles()->attach($consultant->role_id);
        $assignment->expert_role_id = ExpertRole::where('expert_id', $assignment->expert_id)->where('role_id', $consultant->role_id)->value('expert_role_id');

        $this->assertSame('consultant', $assignment->engaged_role_name);
    }

    /** An older engagement with no recorded role is also looked up once and kept. */
    public function test_an_engagement_without_a_recorded_role_is_looked_up_once(): void
    {
        $assignment = $this->assignmentAs('grammarian', withEngagedRole: false);

        $names = [];
        $queries = $this->expertRoleQueriesDuring(function () use ($assignment, &$names): void {
            foreach (range(1, 3) as $read) {
                $names[] = $assignment->engaged_role_name;
            }
        });

        $this->assertCount(1, array_unique($names, SORT_REGULAR));
        $this->assertSame(1, $queries);
    }
}
