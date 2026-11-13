<?php

namespace Tests\Feature;

use App\Models\DefenseSchedule;
use App\Models\DefenseWindow;
use App\Models\Team;
use App\Support\DefenseOutcome;
use App\Support\DefenseProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Failing is running out of time, not something a panel does on the day.
 *
 * There is no "rejected" outcome - a team that does not make it is one whose
 * defense window closed with no pass. It is derived rather than stored,
 * because the CAPSA extends deadlines: moving a window's end date has to
 * un-fail everyone it covers, which a stored flag would leave stale.
 */
class DefenseProgressTest extends TestCase
{
    use RefreshDatabase;

    private DefenseProgress $progress;

    protected function setUp(): void
    {
        parent::setUp();
        $this->progress = new DefenseProgress;
    }

    private function makeTeam(): Team
    {
        return Team::create([
            'team_name' => 'Progressing Team',
            'team_status' => 'Active',
            'is_confirmed' => true,
            'member_count' => 5,
        ]);
    }

    private function window(string $stage, string $endDate): void
    {
        DefenseWindow::updateOrCreate(
            ['defense_type' => $stage],
            ['start_date' => now()->subMonth()->toDateString(), 'end_date' => $endDate],
        );
    }

    private function attempt(Team $team, string $stage, ?string $outcome = null, int $attempt = 1): DefenseSchedule
    {
        return DefenseSchedule::create([
            'team_id' => $team->team_id,
            'defense_type' => $stage,
            'attempt' => $attempt,
            'outcome' => $outcome,
        ]);
    }

    public function test_a_team_that_passed_has_passed(): void
    {
        $team = $this->makeTeam();
        $this->attempt($team, 'Title Defense', DefenseOutcome::PASSED);

        $this->assertSame(DefenseProgress::PASSED, $this->progress->stageStatus($team, 'Title Defense'));
    }

    // A redefense is exactly what a second chance is for, so passing on the
    // second attempt is passing.
    public function test_passing_on_a_later_attempt_counts(): void
    {
        $team = $this->makeTeam();
        $this->attempt($team, 'Title Defense', DefenseOutcome::REDEFENSE, 1);
        $this->attempt($team, 'Title Defense', DefenseOutcome::PASSED, 2);

        $this->assertSame(DefenseProgress::PASSED, $this->progress->stageStatus($team, 'Title Defense'));
    }

    public function test_passing_with_revision_still_counts_as_passing(): void
    {
        $team = $this->makeTeam();
        $this->attempt($team, 'Pre-proposal Defense', DefenseOutcome::PASSED_WITH_REVISION);

        $this->assertSame(DefenseProgress::PASSED, $this->progress->stageStatus($team, 'Pre-proposal Defense'));
    }

    public function test_a_team_still_inside_the_window_is_in_progress(): void
    {
        $team = $this->makeTeam();
        $this->window('Title Defense', now()->addWeek()->toDateString());
        $this->attempt($team, 'Title Defense', DefenseOutcome::REDEFENSE);

        $this->assertSame(DefenseProgress::IN_PROGRESS, $this->progress->stageStatus($team, 'Title Defense'));
    }

    public function test_a_team_that_ran_out_of_window_has_failed(): void
    {
        $team = $this->makeTeam();
        $this->window('Title Defense', now()->subDay()->toDateString());
        $this->attempt($team, 'Title Defense', DefenseOutcome::REDEFENSE);

        $this->assertSame(DefenseProgress::FAILED, $this->progress->stageStatus($team, 'Title Defense'));
    }

    public function test_a_redefense_still_to_be_held_is_not_failed_by_the_window(): void
    {
        $team = $this->makeTeam();
        $this->window('Title Defense', now()->subDay()->toDateString());
        $this->attempt($team, 'Title Defense', DefenseOutcome::REDEFENSE, 1);
        $this->attempt($team, 'Title Defense', null, 2);

        $this->assertSame(DefenseProgress::IN_PROGRESS, $this->progress->stageStatus($team, 'Title Defense'));
    }

    // The reason this is derived: extending the deadline has to take effect
    // on its own, with no stored flag to go back and clear.
    public function test_extending_the_deadline_un_fails_a_team(): void
    {
        $team = $this->makeTeam();
        $this->window('Title Defense', now()->subDay()->toDateString());
        $this->attempt($team, 'Title Defense', DefenseOutcome::REDEFENSE);

        $this->assertSame(DefenseProgress::FAILED, $this->progress->stageStatus($team, 'Title Defense'));

        // The CAPSA gives them another two weeks.
        $this->window('Title Defense', now()->addWeeks(2)->toDateString());

        $this->assertSame(
            DefenseProgress::IN_PROGRESS,
            (new DefenseProgress)->stageStatus($team, 'Title Defense'),
            'extending the window has to bring a team back in, without anything to reset',
        );
    }

    // A stage with no window has no deadline. An unset date is not a date in
    // the past, and nobody can miss a deadline that was never set.
    public function test_a_stage_with_no_window_never_fails_anyone(): void
    {
        $team = $this->makeTeam();
        $this->attempt($team, 'Pre Oral Defense', DefenseOutcome::REDEFENSE);

        $this->assertSame(DefenseProgress::IN_PROGRESS, $this->progress->stageStatus($team, 'Pre Oral Defense'));
    }

    // Kept apart from "failed" deliberately: a team nobody scheduled was
    // never given the chance, and that is the CAPSA's to fix.
    public function test_a_team_that_was_never_scheduled_is_not_marked_failed(): void
    {
        $team = $this->makeTeam();
        $this->window('Final Oral Defense', now()->subDay()->toDateString());

        $this->assertSame(DefenseProgress::NOT_SCHEDULED, $this->progress->stageStatus($team, 'Final Oral Defense'));
    }

    public function test_the_current_stage_is_the_first_one_not_yet_passed(): void
    {
        $team = $this->makeTeam();
        $this->attempt($team, 'Pre-proposal Defense', DefenseOutcome::PASSED);
        $this->attempt($team, 'Title Defense', DefenseOutcome::REDEFENSE);

        $this->assertSame('Title Defense', $this->progress->currentStage($team));
    }

    public function test_a_team_past_every_stage_has_no_current_stage(): void
    {
        $team = $this->makeTeam();
        foreach (DefenseProgress::STAGES as $stage) {
            $this->attempt($team, $stage, DefenseOutcome::PASSED);
        }

        $this->assertNull($this->progress->currentStage($team));
    }
}
