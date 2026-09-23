<?php

namespace Tests\Feature;

use App\Models\ArchivedDocument;
use App\Models\MemberLeaveRequest;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Team Torchic had no members left, so a one-off migration removes it,
 * and only it.
 */
class RemoveMemberlessTeamTorchicTest extends TestCase
{
    use RefreshDatabase;

    private function runMigration(): void
    {
        (require base_path('database/migrations/2026_09_24_075503_remove_memberless_team_torchic.php'))->up();
    }

    private function team(string $name, bool $withMember): Team
    {
        $leader = Student::factory()->withCompleteProfile()->create();
        $team = Team::create(['team_name' => $name, 'team_status' => 'Active', 'leader_id' => $leader->student_id]);

        if ($withMember) {
            StudentSchoolYear::create(['student_id' => $leader->student_id, 'team_id' => $team->team_id]);
        }

        return $team;
    }

    public function test_team_torchic_with_no_members_is_removed_with_what_waits_on_it(): void
    {
        $torchic = $this->team('Torchic', withMember: false);
        $request = MemberLeaveRequest::create([
            'team_id' => $torchic->team_id, 'student_id' => $torchic->leader_id, 'requested_by' => $torchic->leader_id,
            'type' => 'leave', 'reason' => 'Moving out', 'status' => MemberLeaveRequest::PENDING,
        ]);
        $archive = ArchivedDocument::create(['team_id' => $torchic->team_id, 'title' => 'Torchic Study', 'academic_year' => '2025', 'program' => 'BSIT']);

        $this->runMigration();

        $this->assertModelMissing($torchic);
        $this->assertModelMissing($request);
        $this->assertNull($archive->fresh()->team_id);
    }

    public function test_the_name_is_matched_whatever_its_case_or_prefix(): void
    {
        $torchic = $this->team(' team TORCHIC ', withMember: false);

        $this->runMigration();

        $this->assertModelMissing($torchic);
    }

    public function test_a_torchic_that_has_members_again_is_kept(): void
    {
        $torchic = $this->team('Torchic', withMember: true);

        $this->runMigration();

        $this->assertModelExists($torchic);
    }

    public function test_other_teams_are_left_alone_even_when_empty(): void
    {
        $empty = $this->team('Mudkip', withMember: false);
        $full = $this->team('Treecko', withMember: true);

        $this->runMigration();

        $this->assertModelExists($empty);
        $this->assertModelExists($full);
    }
}
