<?php

namespace Tests\Feature;

use App\Models\CapstoneDocument;
use App\Models\CapstoneDocumentReview;
use App\Models\CapstoneExpert;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * A CaPSA cannot switch off their own account; a degree is chosen as it is
 * written (MIT, PhD) and sets the level the Panel Chair rule reads; panel
 * suggestions show that degree and nothing when there is none; the team page
 * knows the Team Rules before anyone fills in a form; and a reviewer can open
 * the team's earlier version of the same requirement.
 */
class ExpertAndTeamChangesTest extends TestCase
{
    use RefreshDatabase;

    private function capsa(): CapstoneExpert
    {
        return $this->makeCapsa(CapstoneExpert::factory()->create(['approved_status' => true]));
    }

    public function test_a_capsa_cannot_deactivate_their_own_account(): void
    {
        $capsa = $this->capsa();
        $other = CapstoneExpert::factory()->create(['approved_status' => true]);

        $this->actingAs($capsa, 'expert')->deleteJson(route('capsa.experts.destroy', $capsa))->assertStatus(422);
        $this->assertNull($capsa->fresh()->deactivated_at);

        $this->actingAs($capsa, 'expert')->deleteJson(route('capsa.experts.destroy', $other))->assertOk();
    }

    public function test_a_degree_title_sets_the_level_the_chair_rule_reads(): void
    {
        $capsa = $this->capsa();
        $expert = CapstoneExpert::factory()->create(['approved_status' => true]);

        $this->actingAs($capsa, 'expert')
            ->putJson(route('capsa.experts.degree.update', $expert), ['degree' => 'MIT'])
            ->assertOk()
            ->assertJson(['degree' => 'MIT', 'highest_degree' => 'master', 'can_chair' => true]);

        $this->actingAs($capsa, 'expert')
            ->putJson(route('capsa.experts.degree.update', $expert), ['degree' => 'BSIT'])
            ->assertOk()
            ->assertJson(['can_chair' => false]);

        $this->actingAs($capsa, 'expert')
            ->putJson(route('capsa.experts.degree.update', $expert), ['degree' => 'Master of Stuff'])
            ->assertStatus(422);
    }

    public function test_a_degree_written_after_the_name_is_read_when_adding_an_expert(): void
    {
        $email = 'new.panelist@'.ltrim(CapstoneExpert::EMAIL_DOMAINS[0], '@');

        $this->actingAs($this->capsa(), 'expert')
            ->postJson(route('capsa.experts.store'), ['email' => $email, 'name' => 'Rosa Villanueva, MIT, Ph.D.'])
            ->assertOk();

        $expert = CapstoneExpert::where('email', $email)->first();
        $this->assertSame(['Rosa', 'Villanueva', 'MIT, Ph.D.'], [$expert->first_name, $expert->last_name, $expert->credentials]);
        $this->assertSame(['PhD', 'doctorate'], [$expert->degree_title, $expert->highest_degree]);

        // Editing the credentials later changes the degree with them.
        $expert->update(['credentials' => 'LPT']);
        $this->assertNull($expert->fresh()->highest_degree);
    }

    public function test_panel_suggestions_show_the_degree_and_nothing_when_there_is_none(): void
    {
        $role = Role::firstOrCreate(['role_name' => 'adviser']);
        $withDegree = CapstoneExpert::factory()->create(['approved_status' => true, 'degree_title' => 'MIT', 'highest_degree' => 'master', 'credentials' => 'MIT']);
        $without = CapstoneExpert::factory()->create(['approved_status' => true, 'highest_degree' => null]);
        $withDegree->roles()->attach($role->role_id);
        $without->roles()->attach($role->role_id);

        $this->actingAs($this->makeCapsa(CapstoneExpert::factory()->create(['approved_status' => true, 'is_admin' => true])), 'expert')
            ->get(route('capsa.panel_assignment'))
            ->assertOk()
            ->assertViewHas('state', function ($state) use ($withDegree, $without) {
                $experts = collect($state['experts'])->keyBy('id');

                return $experts[$withDegree->expert_id]['degree'] === 'MIT'
                    && ! str_contains($experts[$withDegree->expert_id]['name'], ',')
                    && $experts[$without->expert_id]['degree'] === null
                    && $experts[$without->expert_id]['chair'] === false;
            });
    }

    public function test_the_team_page_knows_the_team_rules_before_any_form_is_filled_in(): void
    {
        Setting::set('allow_team_creation', '0');
        Setting::set('allow_team_joining', '0');
        // Capstone 1: a 4th-year student is told team forming is over for another reason.
        $student = Student::factory()->withCompleteProfile()->create(['year_section' => '3rd · A']);

        $this->actingAs($student)
            ->get(route('student.team'))
            ->assertOk()
            ->assertViewHas('creationAllowed', false)
            ->assertViewHas('joiningAllowed', false)
            ->assertSee('Creating and joining teams are closed');
    }

    public function test_a_reviewer_can_open_the_teams_earlier_version_of_the_same_requirement(): void
    {
        Storage::fake('local');
        $team = Team::create(['team_name' => 'Alpha', 'team_status' => 'Active']);
        $other = Team::create(['team_name' => 'Bravo', 'team_status' => 'Active']);
        [$a, $b, $outsider] = Student::factory()->withCompleteProfile()->count(3)->create();
        StudentSchoolYear::create(['student_id' => $a->student_id, 'team_id' => $team->team_id]);
        StudentSchoolYear::create(['student_id' => $b->student_id, 'team_id' => $team->team_id]);
        StudentSchoolYear::create(['student_id' => $outsider->student_id, 'team_id' => $other->team_id]);

        $doc = fn ($student, int $version) => tap(CapstoneDocument::create([
            'student_id' => $student->student_id, 'file_name' => 'Chapter 1', 'chapter' => 'Chapter 1',
            'file_path' => "docs/ch1-{$student->student_id}-v{$version}.pdf", 'file_version' => $version, 'document_status' => 'Pending',
        ]), fn ($d) => Storage::disk('local')->put($d->file_path, '%PDF-1.4'));

        $first = $doc($a, 1);
        $current = $doc($b, 2);
        $elsewhere = $doc($outsider, 1);

        $expert = CapstoneExpert::factory()->create(['approved_status' => true]);
        $review = CapstoneDocumentReview::create(['file_id' => $current->file_id, 'expert_id' => $expert->expert_id, 'role' => 'adviser', 'review_status' => 'To Review', 'version' => '2']);

        $this->assertSame([$first->file_id], $current->earlierVersions()->pluck('file_id')->all());

        $this->actingAs($expert, 'expert')->get(route('reviews.versions.file', [$review->review_id, $first->file_id]))->assertOk();
        $this->actingAs($expert, 'expert')->getJson(route('reviews.versions.remarks', [$review->review_id, $first->file_id]))->assertOk()->assertExactJson([]);
        $this->actingAs($expert, 'expert')->get(route('reviews.versions.file', [$review->review_id, $elsewhere->file_id]))->assertNotFound();

        $stranger = CapstoneExpert::factory()->create(['approved_status' => true]);
        $this->actingAs($stranger, 'expert')->get(route('reviews.versions.file', [$review->review_id, $first->file_id]))->assertForbidden();
    }
}
