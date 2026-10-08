<?php

namespace Tests\Feature;

use App\Models\CapstoneDocumentReview;
use App\Models\CapstoneExpert;
use App\Models\ExpertRole;
use App\Models\Role;
use App\Models\Team;
use App\Models\TeamExpertAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsChecklists;
use Tests\TestCase;

class SlotSuggestionTest extends TestCase
{
    use BuildsChecklists;
    use RefreshDatabase;

    private int $fileId;

    protected function setUp(): void
    {
        parent::setUp();
        [, $leader] = $this->teamInSection('Alpha');
        $this->fileId = $this->upload($leader, null);
    }

    public function test_full_and_quick_suggests_increase(): void
    {
        $expert = $this->expert(maxTeams: 1);
        $this->review($expert, 'Reviewed', 2);

        $this->assertSame('Increase slots', $expert->slotSuggestion(1));
    }

    public function test_quick_with_slots_left_suggests_maintain(): void
    {
        $expert = $this->expert(maxTeams: 5);
        $this->review($expert, 'Reviewed', 2);

        $this->assertSame('Maintain slots', $expert->slotSuggestion(1));
    }

    public function test_a_review_waiting_over_a_week_suggests_reduce(): void
    {
        $expert = $this->expert(maxTeams: 1);
        $this->review($expert, 'Reviewed', 2);
        $this->review($expert, 'To Review', 10);

        $this->assertSame('Reduce slots', $expert->slotSuggestion(1));
    }

    public function test_slow_reviews_suggest_reduce(): void
    {
        $expert = $this->expert(maxTeams: 5);
        $this->review($expert, 'Reviewed', 12, tookDays: 9);

        $this->assertSame('Reduce slots', $expert->slotSuggestion(1));
    }

    public function test_no_suggestion_without_a_finished_review_or_a_slot_limit(): void
    {
        $this->assertNull($this->expert(maxTeams: 5)->slotSuggestion(5));

        $unlimited = $this->expert(maxTeams: null);
        $this->review($unlimited, 'Reviewed', 2);
        $this->assertNull($unlimited->slotSuggestion(5));
    }

    public function test_capsa_and_expert_both_see_it(): void
    {
        [$team] = $this->teamInSection('Bravo');
        $expert = $this->expert(maxTeams: 1, team: $team);
        $this->review($expert, 'Reviewed', 2);

        $this->actingAs($this->capsaFor(), 'expert')
            ->get(route('capsa.experts'))
            ->assertOk()
            ->assertSee('Increase slots');

        $this->actingAs($expert, 'expert')
            ->get(route('instructor.dashboard'))
            ->assertOk()
            ->assertSee('Increase slots');
    }

    private function expert(?int $maxTeams, ?Team $team = null): CapstoneExpert
    {
        $expert = CapstoneExpert::factory()->create(['approved_status' => true, 'max_teams' => $maxTeams]);
        $role = Role::firstOrCreate(['role_name' => 'adviser']);
        $expert->roles()->syncWithoutDetaching([$role->role_id]);

        if ($team) {
            TeamExpertAssignment::create([
                'team_id' => $team->team_id, 'expert_id' => $expert->expert_id, 'status' => 'approved',
                'expert_role_id' => ExpertRole::where('expert_id', $expert->expert_id)->where('role_id', $role->role_id)->value('expert_role_id'),
            ]);
        }

        return $expert;
    }

    /** A review sent some days ago; a finished one took a day unless told otherwise. */
    private function review(CapstoneExpert $expert, string $status, int $daysAgo, int $tookDays = 1): void
    {
        $review = CapstoneDocumentReview::create([
            'file_id' => $this->fileId, 'expert_id' => $expert->expert_id, 'role' => 'adviser', 'review_status' => $status, 'version' => '1',
            'reviewed_at' => $status === 'Reviewed' ? now()->subDays($daysAgo - $tookDays) : null,
        ]);
        $review->forceFill(['created_at' => now()->subDays($daysAgo)])->save();
    }
}
