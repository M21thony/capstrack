<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Models\TeamExpertAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A decline always carries a reason.
 *
 * Declining used to be a bare click. The team got told no and nothing else -
 * which is the wrong shape for what most declines actually mean here: an
 * expert who wants the proposal tightened before they take it on. That reads
 * as a refusal unless somebody writes the sentence, so the sentence is now
 * required on both sides.
 */
class DecliningNeedsAReasonTest extends TestCase
{
    use RefreshDatabase;

    private CapstoneExpert $expert;
    private TeamExpertAssignment $application;

    protected function setUp(): void
    {
        parent::setUp();

        $this->expert = CapstoneExpert::factory()->create([
            'approved_status' => true, 'is_admin' => false,
        ]);
        $role = Role::firstOrCreate(['role_name' => 'adviser']);
        $this->expert->roles()->attach($role->role_id);

        $leader = Student::create([
            'email' => 'leader@my.cspc.edu.ph', 'f_name' => 'Mark', 'l_name' => 'Balilla',
            'course' => 'BSIT', 'year_section' => '4th · A',
        ]);
        $team = Team::create(['team_name' => 'Bulbasaurus', 'leader_id' => $leader->student_id]);
        StudentSchoolYear::create(['student_id' => $leader->student_id, 'team_id' => $team->team_id]);

        $this->application = TeamExpertAssignment::create([
            'team_id' => $team->team_id,
            'expert_id' => $this->expert->expert_id,
            'role_id' => $role->role_id,
            'status' => 'pending',
        ]);
    }

    private function decline(array $body)
    {
        return $this->actingAs($this->expert, 'expert')
            ->patchJson('/instructor/assignments/'.$this->application->assignment_id, $body + ['status' => 'rejected']);
    }

    /** No reason, no decline. */
    public function test_a_decline_with_no_reason_is_refused(): void
    {
        $this->decline([])->assertStatus(422)->assertJsonValidationErrors('reason');

        $this->assertSame('pending', $this->application->fresh()->status);
    }

    /** Nor a one-word one, which is the same thing with extra steps. */
    public function test_a_one_word_reason_is_refused(): void
    {
        $this->decline(['reason' => 'Full.'])->assertStatus(422)->assertJsonValidationErrors('reason');

        $this->assertSame('pending', $this->application->fresh()->status);
    }

    /** A real one goes through and is kept for the team to read. */
    public function test_a_written_reason_is_stored(): void
    {
        $reason = 'Tighten the problem statement and scope, then apply again.';

        $this->decline(['reason' => $reason])->assertOk();

        $fresh = $this->application->fresh();
        $this->assertSame('rejected', $fresh->status);
        $this->assertSame($reason, $fresh->rejection_reason);
    }

    /** The dialog will not send one either. */
    public function test_the_dialog_holds_the_button_until_something_is_written(): void
    {
        $applications = file_get_contents(resource_path('views/capsa/team-management/_applications.blade.php'));
        $script = file_get_contents(resource_path('views/capsa/team-management/_hub-script.blade.php'));

        // Declining from Team Applications asks for the reason, 20 characters at least.
        $this->assertStringContainsString('data-value="rejected" data-note="reason" data-min="20"', $applications);
        $this->assertStringContainsString("note: 'reason', min: '20'", $script);
        $this->assertStringContainsString("if (needsNote(b) && note.value.trim().length < Math.max(1, Number(b.dataset.min || 1))) {", $script);
    }
}
