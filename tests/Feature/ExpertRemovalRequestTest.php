<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\ExpertRemovalRequest;
use App\Models\Notification;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Models\TeamExpertAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** A team needs its CaPSA's permission to remove an expert who already took them on. */
class ExpertRemovalRequestTest extends TestCase
{
    use RefreshDatabase;

    private const REASON = 'Our adviser moved to another campus this semester.';

    /** The adviser the team names instead - applied to once the CaPSA approves. */
    private ?CapstoneExpert $replacement = null;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function adviserWithRoom(int $maxTeams = 4): CapstoneExpert
    {
        $expert = CapstoneExpert::factory()->create(['approved_status' => true, 'max_teams' => $maxTeams, 'program_slots' => $maxTeams.' - BSIT']);
        $expert->roles()->attach(Role::firstOrCreate(['role_name' => 'adviser'])->role_id);

        return $expert;
    }

    /** @return array{0: Student, 1: Team, 2: TeamExpertAssignment, 3: CapstoneExpert} */
    private function teamWithAdviser(string $status = 'approved'): array
    {
        $leader = Student::factory()->withCompleteProfile()->create([
            'course' => 'Bachelor of Science in Information Technology',
            'year_section' => '3rd · A',
        ]);
        $team = Team::create(['team_name' => 'Bulbasaur', 'team_status' => 'Active', 'is_confirmed' => true, 'leader_id' => $leader->student_id]);
        StudentSchoolYear::create(['student_id' => $leader->student_id, 'team_id' => $team->team_id]);

        $role = Role::firstOrCreate(['role_name' => 'adviser']);
        $adviser = CapstoneExpert::factory()->create(['approved_status' => true]);
        $adviser->roles()->attach($role->role_id);

        $assignment = TeamExpertAssignment::create([
            'team_id' => $team->team_id,
            'expert_id' => $adviser->expert_id,
            'expert_role_id' => DB::table('expert_roles')->where('expert_id', $adviser->expert_id)->value('expert_role_id'),
            'status' => $status,
        ]);

        $capsa = $this->makeCapsa(CapstoneExpert::factory()->create(['approved_status' => true, 'sections' => ['BSIT-3A']]));
        $this->replacement = $this->adviserWithRoom();

        return [$leader, $team, $assignment, $capsa];
    }

    private function askToRemove(Student $leader, TeamExpertAssignment $assignment, string $reason = self::REASON, array $overrides = [])
    {
        // A form, as the page sends it: the replacement may need a PDF.
        return $this->actingAs($leader, 'web')
            ->post(route('api.student.assignments.destroy', $assignment), $overrides + [
                '_method' => 'DELETE',
                'reason' => $reason,
                'replacement_expert_id' => $this->replacement?->expert_id,
                'replacement_message' => 'We are Bulbasaur, building a clinic queue system. Would you advise us?',
                'replacement_proposal_file' => UploadedFile::fake()->create('pre-proposal.pdf', 20, 'application/pdf'),
            ], ['Accept' => 'application/json']);
    }

    private function approve(CapstoneExpert $capsa)
    {
        return $this->actingAs($capsa, 'expert')
            ->patchJson(route('capsa.expert_removal_requests.update', ExpertRemovalRequest::first()), ['status' => 'approved']);
    }

    public function test_a_change_request_has_to_name_a_replacement_with_room(): void
    {
        [$leader, , $assignment] = $this->teamWithAdviser();

        $this->askToRemove($leader, $assignment, self::REASON, ['replacement_expert_id' => ''])
            ->assertStatus(422)->assertJson(['message' => 'Pick the adviser you want instead.']);
        $this->askToRemove($leader, $assignment, self::REASON, ['replacement_message' => ''])
            ->assertStatus(422)->assertJson(['message' => 'Write a short message to the adviser you picked.']);
        $this->askToRemove($leader, $assignment, self::REASON, ['replacement_expert_id' => $assignment->expert_id])
            ->assertStatus(422);

        // Full already: refused now rather than found out later.
        $full = $this->adviserWithRoom(1);
        $other = Team::create(['team_name' => 'Other', 'team_status' => 'Active', 'is_confirmed' => true]);
        TeamExpertAssignment::create(['team_id' => $other->team_id, 'expert_id' => $full->expert_id, 'status' => 'approved',
            'expert_role_id' => DB::table('expert_roles')->where('expert_id', $full->expert_id)->value('expert_role_id')]);
        $this->askToRemove($leader, $assignment, self::REASON, ['replacement_expert_id' => $full->expert_id])->assertStatus(422);

        // Someone who does not take teams as an adviser.
        $grammarian = CapstoneExpert::factory()->create(['approved_status' => true, 'max_teams' => 4, 'program_slots' => '4 - BSIT']);
        $grammarian->roles()->attach(Role::firstOrCreate(['role_name' => 'grammarian'])->role_id);
        $this->askToRemove($leader, $assignment, self::REASON, ['replacement_expert_id' => $grammarian->expert_id])->assertStatus(422);

        $this->assertSame(0, ExpertRemovalRequest::count());
    }

    public function test_approving_sends_the_application_to_the_replacement_and_tells_the_old_expert(): void
    {
        [$leader, $team, $assignment, $capsa] = $this->teamWithAdviser();
        $this->askToRemove($leader, $assignment)->assertOk();

        $removal = ExpertRemovalRequest::first();
        $this->assertSame($this->replacement->expert_id, $removal->replacement_expert_id);
        Storage::disk('public')->assertExists($removal->replacement_proposal_file);

        $this->approve($capsa)->assertOk()->assertJsonFragment(['success' => true]);

        $this->assertDatabaseMissing('capstone_expert_application', ['assignment_id' => $assignment->assignment_id]);
        $replaced = TeamExpertAssignment::where('team_id', $team->team_id)->where('expert_id', $this->replacement->expert_id)->first();
        $this->assertNotNull($replaced);
        $this->assertSame('pending', $replaced->status, 'the replacement still accepts or declines');
        $this->assertSame('adviser', $replaced->engaged_role_name);
        $this->assertSame($removal->replacement_proposal_file, $replaced->proposal_file);
        $removal->refresh();
        $this->assertSame(ExpertRemovalRequest::REPLACEMENT_SENT, $removal->replacement_outcome);
        $this->assertSame($replaced->assignment_id, $removal->replacement_assignment_id);

        $this->assertDatabaseHas('notifications', ['expert_id' => $this->replacement->expert_id, 'type' => 'expert_application', 'title' => 'New application']);
        $this->assertDatabaseHas('notifications', ['expert_id' => $assignment->expert_id, 'type' => 'expert_application', 'title' => 'Removed from a team']);
        $this->assertStringContainsString('Your application went to', Notification::where('student_id', $leader->student_id)->where('title', 'Expert change approved')->value('message'));
    }

    public function test_a_replacement_who_filled_up_meanwhile_blocks_the_approval(): void
    {
        [$leader, $team, $assignment, $capsa] = $this->teamWithAdviser();
        $this->replacement->update(['max_teams' => 1, 'program_slots' => '1 - BSIT']);
        $this->askToRemove($leader, $assignment)->assertOk();

        // While the CaPSA was deciding, another team took the last slot.
        $other = Team::create(['team_name' => 'Faster', 'team_status' => 'Active', 'is_confirmed' => true]);
        TeamExpertAssignment::create(['team_id' => $other->team_id, 'expert_id' => $this->replacement->expert_id, 'status' => 'approved',
            'expert_role_id' => DB::table('expert_roles')->where('expert_id', $this->replacement->expert_id)->value('expert_role_id')]);
        $file = ExpertRemovalRequest::first()->replacement_proposal_file;

        $this->approve($capsa)->assertStatus(422)->assertJsonFragment(['success' => false]);

        // Nothing moved: the team keeps its adviser and the CaPSA can decline.
        $this->assertDatabaseHas('capstone_expert_application', ['assignment_id' => $assignment->assignment_id, 'status' => 'approved']);
        $this->assertFalse(TeamExpertAssignment::where('team_id', $team->team_id)->where('expert_id', $this->replacement->expert_id)->exists());
        $this->assertTrue(ExpertRemovalRequest::first()->isPending());
        Storage::disk('public')->assertExists($file);
        $this->assertDatabaseMissing('notifications', ['expert_id' => $assignment->expert_id, 'title' => 'Removed from a team']);
    }

    public function test_declining_sends_nothing_to_the_replacement(): void
    {
        [$leader, $team, $assignment, $capsa] = $this->teamWithAdviser();
        $this->askToRemove($leader, $assignment)->assertOk();
        $file = ExpertRemovalRequest::first()->replacement_proposal_file;

        $this->actingAs($capsa, 'expert')
            ->patchJson(route('capsa.expert_removal_requests.update', ExpertRemovalRequest::first()), ['status' => 'declined', 'note' => 'Stay with them until the defense.'])
            ->assertOk();

        $this->assertFalse(TeamExpertAssignment::where('team_id', $team->team_id)->where('expert_id', $this->replacement->expert_id)->exists());
        Storage::disk('public')->assertMissing($file);
    }

    public function test_the_page_offers_only_replacements_with_room_and_names_the_pick(): void
    {
        [$leader, , $assignment, $capsa] = $this->teamWithAdviser();
        $full = $this->adviserWithRoom(1);
        $other = Team::create(['team_name' => 'Other', 'team_status' => 'Active', 'is_confirmed' => true]);
        TeamExpertAssignment::create(['team_id' => $other->team_id, 'expert_id' => $full->expert_id, 'status' => 'approved',
            'expert_role_id' => DB::table('expert_roles')->where('expert_id', $full->expert_id)->value('expert_role_id')]);

        $options = $this->actingAs($leader, 'web')->get(route('student.experts', ['tab' => 'find', 'role' => 'adviser']))
            ->assertOk()->viewData('replacementOptions');
        $this->assertSame([$this->replacement->expert_id], array_column($options['adviser'], 'id'));

        $this->askToRemove($leader, $assignment)->assertOk();

        $this->actingAs($leader, 'web')->get(route('student.experts', ['tab' => 'find', 'role' => 'adviser']))
            ->assertOk()->assertSee('If approved, your application goes to', false);
        $this->actingAs($capsa, 'expert')->get(route('capsa.team_management', ['tab' => 'leave_requests']))
            ->assertOk()->assertSee('Replacement they picked');
    }

    public function test_removing_an_approved_expert_sends_a_request_and_keeps_the_expert(): void
    {
        [$leader, $team, $assignment] = $this->teamWithAdviser();

        $this->askToRemove($leader, $assignment)->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseHas('capstone_expert_application', ['assignment_id' => $assignment->assignment_id, 'status' => 'approved']);
        $this->assertSame(1, ExpertRemovalRequest::pending()->where('team_id', $team->team_id)->count());

        // Asking twice does not stack requests.
        $this->askToRemove($leader, $assignment)->assertStatus(422);
        // A reason the CaPSA can decide on.
        ExpertRemovalRequest::query()->delete();
        $this->askToRemove($leader, $assignment, 'no')->assertStatus(422);
    }

    /** The dialog says whose CaPSA decides, and a waiting request says so on the expert. */
    public function test_the_experts_page_names_the_sections_capsa_and_flags_a_waiting_change(): void
    {
        [$leader, , $assignment] = $this->teamWithAdviser();

        // Before asking: browsing advisers offers the change, and the dialog
        // it opens names the section whose CaPSA decides.
        $this->actingAs($leader, 'web')
            ->get(route('student.experts', ['tab' => 'find', 'role' => 'adviser']))
            ->assertOk()
            ->assertSee('Request a change')
            ->assertSee("'Request a ' + esc(role) + ' change'", false)
            ->assertSee('"teamSection":"BSIT-3A"', false)
            ->assertSee('Decided by the CaPSA for ', false);

        // After asking: the adviser's card is flagged, and taking the request
        // back replaces the button.
        $this->askToRemove($leader, $assignment)->assertOk();

        $this->actingAs($leader, 'web')
            ->get(route('student.experts', ['tab' => 'find', 'role' => 'adviser']))
            ->assertOk()
            ->assertSee('Change asked')
            ->assertSee('Take back request')
            ->assertDontSee('Request a change');
    }

    public function test_the_capsa_approving_removes_the_expert(): void
    {
        [$leader, $team, $assignment, $capsa] = $this->teamWithAdviser();
        $this->askToRemove($leader, $assignment)->assertOk();
        $removal = ExpertRemovalRequest::first();

        // It waits on the CaPSA's Leave Requests tab.
        $this->actingAs($capsa, 'expert')
            ->get(route('capsa.team_management', ['tab' => 'leave_requests']))
            ->assertOk()
            ->assertSee('Expert change')
            ->assertSee(route('capsa.expert_removal_requests.update', $removal), false);

        $this->actingAs($capsa, 'expert')
            ->patchJson(route('capsa.expert_removal_requests.update', $removal), ['status' => 'approved'])
            ->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseMissing('capstone_expert_application', ['assignment_id' => $assignment->assignment_id]);
        $this->assertSame('approved', $removal->fresh()->status);
        $this->assertDatabaseHas('notifications', ['student_id' => $leader->student_id, 'title' => 'Expert change approved']);
    }

    public function test_the_capsa_declining_needs_a_reason_and_keeps_the_expert(): void
    {
        [$leader, , $assignment, $capsa] = $this->teamWithAdviser();
        $this->askToRemove($leader, $assignment)->assertOk();
        $removal = ExpertRemovalRequest::first();

        $this->actingAs($capsa, 'expert')
            ->patchJson(route('capsa.expert_removal_requests.update', $removal), ['status' => 'declined'])
            ->assertStatus(422);

        $this->actingAs($capsa, 'expert')
            ->patchJson(route('capsa.expert_removal_requests.update', $removal), ['status' => 'declined', 'note' => 'Finish Chapter 1 with them first.'])
            ->assertOk();

        $this->assertDatabaseHas('capstone_expert_application', ['assignment_id' => $assignment->assignment_id, 'status' => 'approved']);
        $this->assertSame('declined', $removal->fresh()->status);
    }

    public function test_a_capsa_of_another_section_cannot_decide(): void
    {
        [$leader, , $assignment] = $this->teamWithAdviser();
        $this->askToRemove($leader, $assignment)->assertOk();
        $other = $this->makeCapsa(CapstoneExpert::factory()->create(['approved_status' => true, 'sections' => ['BSIS-4B']]));

        $this->actingAs($other, 'expert')
            ->patchJson(route('capsa.expert_removal_requests.update', ExpertRemovalRequest::first()), ['status' => 'approved'])
            ->assertForbidden();

        $this->assertDatabaseHas('capstone_expert_application', ['assignment_id' => $assignment->assignment_id]);
    }

    public function test_an_application_the_expert_has_not_answered_is_still_withdrawn_directly(): void
    {
        [$leader, , $assignment] = $this->teamWithAdviser('pending');

        $this->askToRemove($leader, $assignment)->assertOk();

        $this->assertDatabaseMissing('capstone_expert_application', ['assignment_id' => $assignment->assignment_id]);
        $this->assertSame(0, ExpertRemovalRequest::count());
    }

    /** Undo: the team takes back a request its CaPSA has not decided on yet. */
    public function test_the_team_can_take_back_a_change_request_the_capsa_has_not_decided(): void
    {
        [$leader, , $assignment] = $this->teamWithAdviser();
        $this->askToRemove($leader, $assignment)->assertOk();
        $removal = ExpertRemovalRequest::first();

        $this->actingAs($leader, 'web')
            ->deleteJson(route('api.student.expert-removals.destroy', $removal))
            ->assertOk()
            ->assertJson(['success' => true]);

        // Back to where it started: the expert stays, and the team can apply
        // the change again rather than being locked out of it.
        $this->assertSame(ExpertRemovalRequest::WITHDRAWN, $removal->fresh()->status);
        $this->assertDatabaseHas('capstone_expert_application', [
            'assignment_id' => $assignment->assignment_id,
            'status' => 'approved',
        ]);

        $this->actingAs($leader, 'web')
            ->get(route('student.experts', ['tab' => 'find', 'role' => 'adviser']))
            ->assertOk()
            ->assertDontSee('Take back request')
            ->assertSee('Request a change');

        // A withdrawn request is not the CaPSA's to decide any more.
        $this->assertSame(0, ExpertRemovalRequest::pending()->count());
    }

    public function test_taking_a_request_back_is_the_leaders_and_only_while_it_waits(): void
    {
        [$leader, $team, $assignment, $capsa] = $this->teamWithAdviser();
        $this->askToRemove($leader, $assignment)->assertOk();
        $removal = ExpertRemovalRequest::first();

        // A member who is not the leader cannot take it back.
        $member = Student::factory()->withCompleteProfile()->create([
            'course' => 'Bachelor of Science in Information Technology',
            'year_section' => '3rd · A',
        ]);
        StudentSchoolYear::create(['student_id' => $member->student_id, 'team_id' => $team->team_id]);

        $this->actingAs($member, 'web')
            ->deleteJson(route('api.student.expert-removals.destroy', $removal))
            ->assertForbidden();
        $this->assertSame(ExpertRemovalRequest::PENDING, $removal->fresh()->status);

        // Another team's request is not theirs to see at all.
        $outsider = Student::factory()->withCompleteProfile()->create([
            'course' => 'Bachelor of Science in Information Technology',
            'year_section' => '3rd · B',
        ]);
        $otherTeam = Team::create(['team_name' => 'Charmander', 'team_status' => 'Active', 'is_confirmed' => true, 'leader_id' => $outsider->student_id]);
        StudentSchoolYear::create(['student_id' => $outsider->student_id, 'team_id' => $otherTeam->team_id]);

        $this->actingAs($outsider, 'web')
            ->deleteJson(route('api.student.expert-removals.destroy', $removal))
            ->assertNotFound();
        $this->assertSame(ExpertRemovalRequest::PENDING, $removal->fresh()->status);

        // Once the CaPSA has decided there is nothing left to take back.
        $this->actingAs($capsa, 'expert')
            ->patchJson(route('capsa.expert_removal_requests.update', $removal), ['status' => 'approved'])
            ->assertOk();

        $this->actingAs($leader, 'web')
            ->deleteJson(route('api.student.expert-removals.destroy', $removal))
            ->assertStatus(409);
    }
}
