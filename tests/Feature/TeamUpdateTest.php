<?php

namespace Tests\Feature;

use App\Models\CapstonePreProposal;
use App\Models\DuplicationCheck;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TeamUpdateTest extends TestCase
{
    use RefreshDatabase;

    private function makeLeaderWithTeam(array $teamOverrides = []): array
    {
        $student = Student::create([
            'email' => 'leader@my.cspc.edu.ph',
            'f_name' => 'Team',
            'l_name' => 'Leader',
        ]);

        $team = Team::create(array_merge([
            'team_name' => 'Original Name',
            'team_status' => 'Active',
            'leader_id' => $student->student_id,
            'is_confirmed' => false,
            'member_count' => 5,
        ], $teamOverrides));

        return [$student, $team];
    }

    // Saves 3 pre-proposal drafts straight to the database. They are NOT
    // turned in - submitting for checking is a separate act now.
    private function fillAllThreeProposalSlots(Team $team, Student $student): void
    {
        $sy = StudentSchoolYear::firstOrCreate([
            'student_id' => $student->student_id,
            'team_id' => $team->team_id,
        ]);

        for ($slot = 1; $slot <= 3; $slot++) {
            $check = DuplicationCheck::create([
                'student_s_y_id' => $sy->s_y_id,
                'document_path' => "duplication-checks/slot{$slot}.pdf",
                'concepts' => ['title' => 'Test'],
                'top_score' => 20,
                'verdict' => 'Passed',
                'matches' => [],
                'instructors_suggested' => [],
            ]);

            CapstonePreProposal::create([
                'team_id' => $team->team_id,
                'duplication_check_id' => $check->id,
                'slot' => $slot,
                'title' => "Slot {$slot} Smart Automated Irrigation Monitoring System Development",
                'sdg_goals' => 'SDG 4 – Quality Education',
                'research_agenda' => ['Applications and Systems Development'],
                'problem_statement' => 'A problem.',
                'proposed_solution' => 'A solution.',
                'scope' => 'A scope.',
                'limitations' => 'A limitation.',
                'sdm' => 'Agile Development Methodology',
                'duplication_status' => 'free',
                'submitted_at' => now(),
            ]);
        }
    }

    // Regression test: team_status is a nullable field, but the controller
    // used to access $validated['team_status'] directly without a null
    // coalesce, throwing "Undefined array key" whenever a student updated
    // their team without touching status (e.g. renaming only).
    public function test_web_update_without_team_status_does_not_error(): void
    {
        [$student, $team] = $this->makeLeaderWithTeam();
        $this->actingAs($student, 'web');

        $response = $this->put(route('team.update', $team->team_id), [
            'team_name' => 'Renamed Team',
        ]);

        $response->assertSessionDoesntHaveErrors();
        $response->assertRedirect(route('student.team'));

        $this->assertSame('Renamed Team', $team->fresh()->team_name);
    }

    public function test_api_update_without_team_status_does_not_error(): void
    {
        [$student, $team] = $this->makeLeaderWithTeam();
        $this->actingAs($student, 'web');

        $response = $this->putJson("/api/teams/{$team->team_id}", [
            'team_name' => 'Renamed Team',
        ]);

        $response->assertStatus(200);
        $this->assertSame('Renamed Team', $team->fresh()->team_name);
    }

    // Regression test: the API controller used to reassign $validated to
    // the result of a second, narrower validate() call when marking a team
    // complete, wiping out team_name/team_status/member_count that had
    // already been validated and causing either a crash or a silently
    // dropped status change.
    // Complete is what submitting your pre-proposals for checking does
    // (PreProposalController::submit). Setting it here was a second way to
    // turn in, which let a team be complete without ever having handed
    // anything to their CAPSA - so it is refused even with the titles saved.
    public function test_api_update_refuses_complete_even_with_all_the_proposals_saved(): void
    {
        [$student, $team] = $this->makeLeaderWithTeam();
        $this->actingAs($student, 'web');
        $this->fillAllThreeProposalSlots($team, $student);

        $response = $this->putJson("/api/teams/{$team->team_id}", [
            'team_name' => 'Renamed Team',
            'team_status' => 'complete',
        ]);

        $response->assertStatus(422);
        $this->assertSame('Active', $team->fresh()->team_status);
    }

    public function test_api_update_refuses_complete_as_a_status(): void
    {
        [$student, $team] = $this->makeLeaderWithTeam();
        $this->actingAs($student, 'web');

        $response = $this->putJson("/api/teams/{$team->team_id}", [
            'team_name' => 'Renamed Team',
            'team_status' => 'complete',
        ]);

        $response->assertStatus(422);
        $this->assertSame('Active', $team->fresh()->team_status);
    }

    public function test_web_update_refuses_complete_as_a_status(): void
    {
        [$student, $team] = $this->makeLeaderWithTeam();
        $this->actingAs($student, 'web');

        $response = $this->put(route('team.update', $team->team_id), [
            'team_name' => 'Renamed Team',
            'team_status' => 'complete',
        ]);

        $response->assertSessionHasErrors('team_status');
        $this->assertSame('Active', $team->fresh()->team_status);
    }

    public function test_web_update_refuses_complete_even_with_all_the_proposals_saved(): void
    {
        [$student, $team] = $this->makeLeaderWithTeam();
        $this->actingAs($student, 'web');
        $this->fillAllThreeProposalSlots($team, $student);

        $response = $this->put(route('team.update', $team->team_id), [
            'team_name' => 'Renamed Team',
            'team_status' => 'complete',
        ]);

        $response->assertSessionHasErrors('team_status');
        $this->assertSame('Active', $team->fresh()->team_status);
    }

    // A team's title is whichever pre-proposal the CAPSA approves - and when
    // two are approved, whichever the team then picks. It used to also be
    // typed straight into the Update Team form, which meant two sources for
    // one fact that could disagree. Posting it here must now do nothing.
    public function test_the_title_cannot_be_set_through_the_update_form(): void
    {
        [$student, $team] = $this->makeLeaderWithTeam();
        $team->update(['project_title' => 'The Approved Title']);
        $this->actingAs($student, 'web');

        $this->put(route('team.update', $team->team_id), [
            'team_name' => 'Renamed Team',
            'project_title' => 'A Title The Leader Typed',
        ])->assertSessionDoesntHaveErrors();

        $this->assertSame('The Approved Title', $team->fresh()->project_title);
    }

    public function test_the_api_also_refuses_to_set_the_title(): void
    {
        [$student, $team] = $this->makeLeaderWithTeam();
        $team->update(['project_title' => 'The Approved Title']);
        $this->actingAs($student, 'web');

        $this->putJson("/api/teams/{$team->team_id}", [
            'team_name' => 'Renamed Team',
            'project_title' => 'A Title The Leader Typed',
        ])->assertStatus(200);

        $this->assertSame('The Approved Title', $team->fresh()->project_title);
    }

    // Tags used to be demanded only once the typed title ran past 11 words.
    // With no typed title there is no such trigger, so they are simply
    // optional - and saving without them must not be blocked.
    public function test_tags_are_optional_when_updating_a_team(): void
    {
        [$student, $team] = $this->makeLeaderWithTeam();
        $this->actingAs($student, 'web');

        $this->put(route('team.update', $team->team_id), [
            'team_name' => 'Renamed Team',
        ])->assertSessionDoesntHaveErrors();

        $this->assertSame('Renamed Team', $team->fresh()->team_name);
    }

    /** A team whose title is settled: one approved pre-proposal, chosen, with its own tags. */
    private function makeTeamWithTitle(array $tags = ['Web-based', 'Management System']): array
    {
        [$student, $team] = $this->makeLeaderWithTeam();
        $sy = StudentSchoolYear::firstOrCreate(['student_id' => $student->student_id, 'team_id' => $team->team_id]);
        $check = DuplicationCheck::create([
            'student_s_y_id' => $sy->s_y_id, 'document_path' => 'duplication-checks/title.pdf', 'concepts' => ['title' => 'T'],
            'top_score' => 20, 'verdict' => 'Passed', 'matches' => [], 'instructors_suggested' => [],
        ]);
        $proposal = CapstonePreProposal::create([
            'team_id' => $team->team_id, 'duplication_check_id' => $check->id, 'slot' => 1,
            'title' => 'CapsTrack: A Capstone Project Monitoring System', 'sdg_goals' => 'SDG 4 – Quality Education',
            'research_agenda' => ['Applications and Systems Development'], 'problem_statement' => 'A problem.',
            'proposed_solution' => 'A solution.', 'scope' => 'A scope.', 'limitations' => 'A limitation.',
            'sdm' => 'Agile Development Methodology', 'duplication_status' => 'free', 'approved_status' => true,
            'submitted_at' => now(), 'tags' => $tags,
        ]);
        $team->update(['selected_pre_proposal_id' => $proposal->id, 'project_title' => $proposal->title]);

        return [$student, $team->fresh(), $proposal];
    }

    public function test_the_form_starts_with_the_capstone_titles_own_tags(): void
    {
        [$student] = $this->makeTeamWithTitle(['Web-based', 'Management System']);

        $response = $this->actingAs($student, 'web')->get(route('student.team'))->assertOk();

        $response->assertSee('data-saved="'.e(json_encode(['Web-based', 'Management System'])).'"', false)
            ->assertSee('From your capstone title. Keep at least 1, up to 5.')
            // Only the fixed list is offered, the same one the pre-proposal form uses.
            ->assertSee('<option value="Blockchain">Blockchain</option>', false)
            ->assertDontSee('id="tagSearchInput"', false);
    }

    public function test_the_form_no_longer_has_team_members_or_a_status_dropdown(): void
    {
        [$student] = $this->makeTeamWithTitle();

        $html = $this->actingAs($student, 'web')->get(route('student.team'))->assertOk()->getContent();
        $modal = Str::between($html, 'id="editTeamModal"', '{{-- Open Slot Invite Modal --}}');
        $modal = Str::before($modal, 'Open Slot Invite Modal');

        $this->assertStringContainsString('Update Team', $modal);
        $this->assertStringNotContainsString('Fixed at 5 members (including the leader)', $modal);
        $this->assertStringNotContainsString('name="member_count"', $modal);
        $this->assertStringNotContainsString('name="team_status"', $modal);
        // The dropdown sent values the server never accepted, so every save
        // from it failed - the name included.
        $this->assertStringNotContainsString('id="teamStatusSelect"', $html);
    }

    public function test_saving_updates_the_titles_tags_and_the_team(): void
    {
        [$student, $team, $proposal] = $this->makeTeamWithTitle(['Web-based']);

        $this->actingAs($student, 'web')->put(route('team.update', $team->team_id), [
            'team_name' => 'Original Name',
            'tags' => json_encode(['Web-based', 'AI / Machine Learning', 'Chatbot / NLP']),
        ])->assertSessionDoesntHaveErrors();

        $this->assertSame(['Web-based', 'AI / Machine Learning', 'Chatbot / NLP'], $proposal->fresh()->tags);
        $this->assertSame(['Web-based', 'AI / Machine Learning', 'Chatbot / NLP'], (array) $team->fresh()->tags);
    }

    public function test_a_title_cannot_lose_all_its_tags(): void
    {
        [$student, $team, $proposal] = $this->makeTeamWithTitle(['Web-based', 'IoT']);

        $this->actingAs($student, 'web')->put(route('team.update', $team->team_id), [
            'team_name' => 'Renamed Team',
            'tags' => '[]',
        ])->assertSessionHasErrors(['tags' => 'Keep at least one tag on your capstone title.']);

        $this->assertSame(['Web-based', 'IoT'], $proposal->fresh()->tags);
        $this->assertSame('Original Name', $team->fresh()->team_name);
    }

    public function test_only_tags_from_the_list_and_at_most_five_are_accepted(): void
    {
        [$student, $team, $proposal] = $this->makeTeamWithTitle(['Web-based']);
        $this->actingAs($student, 'web');

        $this->put(route('team.update', $team->team_id), [
            'team_name' => 'Original Name', 'tags' => json_encode(['Web-based', 'Anything I Typed']),
        ])->assertSessionHasErrors('tags.1');

        $this->put(route('team.update', $team->team_id), [
            'team_name' => 'Original Name',
            'tags' => json_encode(['Web-based', 'IoT', 'Blockchain', 'Mobile App', 'E-learning', 'Automation']),
        ])->assertSessionHasErrors('tags');

        $this->assertSame(['Web-based'], $proposal->fresh()->tags);
    }

    /** A title set without a pre-proposal behind it keeps its tags on the team, under the same rules. */
    public function test_a_title_without_a_pre_proposal_keeps_its_tags_on_the_team(): void
    {
        [$student, $team] = $this->makeLeaderWithTeam(['project_title' => 'TalaTuro: A Web-Based E-Class Record System', 'tags' => ['Web-based']]);
        StudentSchoolYear::firstOrCreate(['student_id' => $student->student_id, 'team_id' => $team->team_id]);
        $this->actingAs($student, 'web');

        $this->get(route('student.team'))
            ->assertOk()
            ->assertSee('data-saved="'.e(json_encode(['Web-based'])).'"', false);

        $this->put(route('team.update', $team->team_id), [
            'team_name' => 'Original Name', 'tags' => json_encode(['Web-based', 'E-learning']),
        ])->assertSessionDoesntHaveErrors();
        $this->assertSame(['Web-based', 'E-learning'], (array) $team->fresh()->tags);

        $this->put(route('team.update', $team->team_id), [
            'team_name' => 'Original Name', 'tags' => '[]',
        ])->assertSessionHasErrors('tags');
        $this->assertSame(['Web-based', 'E-learning'], (array) $team->fresh()->tags);
    }

    /** Not chosen yet, but the title is one of its pre-proposals: that one's tags are the title's. */
    public function test_the_pre_proposal_carrying_the_title_is_found_without_a_choice(): void
    {
        [$student, $team, $proposal] = $this->makeTeamWithTitle(['IoT']);
        $team->update(['selected_pre_proposal_id' => null]);

        $this->actingAs($student, 'web')->put(route('team.update', $team->team_id), [
            'team_name' => 'Original Name', 'tags' => json_encode(['IoT', 'Automation']),
        ])->assertSessionDoesntHaveErrors();

        $this->assertSame(['IoT', 'Automation'], $proposal->fresh()->tags);
    }

    public function test_before_there_is_a_title_there_are_no_tags_to_edit(): void
    {
        [$student, $team] = $this->makeLeaderWithTeam(['tags' => ['Web-based']]);
        StudentSchoolYear::firstOrCreate(['student_id' => $student->student_id, 'team_id' => $team->team_id]);
        $this->actingAs($student, 'web');

        $this->get(route('student.team'))
            ->assertOk()
            ->assertSee('Tags come with your capstone title.')
            ->assertDontSee('id="tagsHiddenInput"', false);

        // Anything posted anyway is left alone.
        $this->put(route('team.update', $team->team_id), [
            'team_name' => 'Renamed Team', 'tags' => '[]',
        ])->assertSessionDoesntHaveErrors();
        $this->assertSame(['Web-based'], (array) $team->fresh()->tags);
    }

    public function test_the_update_form_shows_the_title_instead_of_asking_for_one(): void
    {
        [$student, $team] = $this->makeLeaderWithTeam();
        StudentSchoolYear::firstOrCreate(['student_id' => $student->student_id, 'team_id' => $team->team_id]);
        $team->update(['project_title' => 'The Approved Title']);

        $response = $this->actingAs($student, 'web')->get(route('student.team'));

        $response->assertStatus(200);
        $response->assertSee('The Approved Title');
        $response->assertDontSee('name="project_title"', false);
    }

    public function test_the_form_explains_where_the_title_will_come_from_when_there_is_none(): void
    {
        [$student, $team] = $this->makeLeaderWithTeam();
        StudentSchoolYear::firstOrCreate(['student_id' => $student->student_id, 'team_id' => $team->team_id]);

        $response = $this->actingAs($student, 'web')->get(route('student.team'));

        $response->assertStatus(200);
        $response->assertSee('Set once your CaPSA approves one of your pre-proposal titles.');
    }

    // Two approved titles means the team still has to pick, so the form
    // points them at where that choice is made rather than leaving the
    // field blank with no explanation.
    public function test_the_form_points_the_team_at_their_choice_when_two_titles_are_approved(): void
    {
        [$student, $team] = $this->makeLeaderWithTeam();
        StudentSchoolYear::firstOrCreate(['student_id' => $student->student_id, 'team_id' => $team->team_id]);
        $this->fillAllThreeProposalSlots($team, $student);
        $team->preProposals()->limit(2)->get()->each->update(['approved_status' => true]);

        $response = $this->actingAs($student, 'web')->get(route('student.team'));

        $response->assertStatus(200);
        $response->assertSee('2 of your titles were approved');
    }
}
