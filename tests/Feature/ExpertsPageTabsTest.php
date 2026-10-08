<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\ExpertExpertise;
use App\Models\ExpertRole;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Models\TeamExpertAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Js;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Experts and Consultation are one page with two tabs. A team with nobody to
 * book lands on finding experts; once someone has accepted them, on booking.
 */
class ExpertsPageTabsTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: Student, 1: Team} */
    private function leader(): array
    {
        $student = Student::create([
            'email' => 'leader@my.cspc.edu.ph',
            'f_name' => 'Team',
            'l_name' => 'Leader',
        ]);

        $team = Team::create([
            'team_name' => 'Tabbed Team',
            'team_status' => 'Active',
            'leader_id' => $student->student_id,
            'is_confirmed' => true,
            'member_count' => 5,
        ]);

        StudentSchoolYear::create(['student_id' => $student->student_id, 'team_id' => $team->team_id]);

        return [$student, $team];
    }

    private function expert(string $first, string $role, int $maxTeams = 4): CapstoneExpert
    {
        $expert = CapstoneExpert::factory()->create([
            'first_name' => $first,
            'approved_status' => true,
            'max_teams' => $maxTeams,
            'program_slots' => $maxTeams.' - BSIT',
        ]);

        $expert->roles()->attach(Role::firstOrCreate(['role_name' => $role])->role_id);

        return $expert;
    }

    private function accept(Team $team, CapstoneExpert $expert): void
    {
        TeamExpertAssignment::create([
            'team_id' => $team->team_id,
            'expert_id' => $expert->expert_id,
            'expert_role_id' => ExpertRole::where('expert_id', $expert->expert_id)->value('expert_role_id'),
            'status' => 'approved',
        ]);
    }

    public function test_a_team_with_no_expert_opens_on_find_experts(): void
    {
        [$student] = $this->leader();
        $this->expert('Neil', 'adviser');

        $response = $this->actingAs($student, 'web')->get(route('student.experts'))->assertOk();

        $this->assertSame('find', $response->viewData('tab'));
        $response->assertSee('id="xh-panel-book" role="tabpanel" aria-labelledby="xh-tab-book"  hidden', false);
    }

    public function test_a_team_with_an_expert_opens_on_consultations(): void
    {
        [$student, $team] = $this->leader();
        $this->accept($team, $this->expert('Neil', 'adviser'));

        $response = $this->actingAs($student, 'web')->get(route('student.experts'))->assertOk();

        $this->assertSame('consultations', $response->viewData('tab'));
        $this->assertCount(1, $response->viewData('consultExperts'));
    }

    /** A search or a role in the address is someone browsing, whatever the team has. */
    public function test_a_search_opens_on_find_experts_even_with_an_expert(): void
    {
        [$student, $team] = $this->leader();
        $this->accept($team, $this->expert('Neil', 'adviser'));

        $this->assertSame('find', $this->actingAs($student, 'web')
            ->get(route('student.experts', ['q' => 'neil']))->viewData('tab'));
        $this->assertSame('find', $this->actingAs($student, 'web')
            ->get(route('student.experts', ['role' => 'grammarian']))->viewData('tab'));
    }

    /** The old Consultation link still works, and opens the booking tab. */
    public function test_the_consultation_address_opens_the_consultations_tab(): void
    {
        [$student] = $this->leader();

        $response = $this->actingAs($student, 'web')->get(route('student.consultation'))->assertOk();

        $this->assertSame('consultations', $response->viewData('tab'));
        $response->assertSee('<title>Experts - CapsTrack</title>', false);
    }

    /** One sidebar link for the one page, lit on either address. */
    public function test_the_sidebar_has_one_link_for_experts_and_consultations(): void
    {
        [$student] = $this->leader();

        foreach (['student.experts', 'student.consultation'] as $route) {
            $html = $this->actingAs($student, 'web')->get(route($route))->assertOk()->getContent();

            $this->assertSame(1, substr_count($html, 'Experts &amp; Consultations'), $route);
            $this->assertStringContainsString('data-tour-target="student.experts" class="nav-item active"', $html);
            $this->assertStringNotContainsString('data-tour-target="student.consultation"', $html);
        }
    }

    /** The top bar already says "Experts", so the page does not repeat it. */
    public function test_the_page_does_not_repeat_the_top_bar_title(): void
    {
        [$student] = $this->leader();

        $this->actingAs($student, 'web')->get(route('student.experts'))
            ->assertOk()
            ->assertDontSee('<h1>Experts</h1>', false);
    }

    public function test_an_unknown_tab_falls_back_rather_than_breaking(): void
    {
        [$student] = $this->leader();

        $this->assertSame('find', $this->actingAs($student, 'web')
            ->get(route('student.experts', ['tab' => 'wizard']))->assertOk()->viewData('tab'));
    }

    public function test_has_open_slots_leaves_out_full_experts_and_those_who_set_no_capacity(): void
    {
        [$student, $team] = $this->leader();

        $open = $this->expert('Opal', 'adviser', 3);
        $full = $this->expert('Fuller', 'adviser', 1);
        $unset = $this->expert('Unsetta', 'adviser', 0);

        // Another team has taken Fuller's only slot.
        $other = Team::create(['team_name' => 'Other Team', 'team_status' => 'Active', 'is_confirmed' => true]);
        $this->accept($other, $full);

        $html = $this->actingAs($student, 'web')
            ->get(route('student.experts', ['role' => 'adviser', 'open' => 1]))->assertOk()->getContent();

        $this->assertStringContainsString('data-expert-id="'.$open->expert_id.'"', $html);
        $this->assertStringNotContainsString('data-expert-id="'.$full->expert_id.'"', $html);
        $this->assertStringNotContainsString('data-expert-id="'.$unset->expert_id.'"', $html);

        // Without the filter, all three are listed.
        $all = $this->actingAs($student, 'web')
            ->get(route('student.experts', ['role' => 'adviser']))->getContent();

        foreach ([$open, $full, $unset] as $expert) {
            $this->assertStringContainsString('data-expert-id="'.$expert->expert_id.'"', $all);
        }
    }

    /**
     * Find experts puts the experts whose skills cover the team's title tags
     * first, then the ones with room for a team, then goes by name.
     *
     * @return array{0: Student, 1: array<string, CapstoneExpert>}
     */
    private function iotTeamAndExperts(): array
    {
        [$student, $team] = $this->leader();
        $team->update(['project_title' => 'Smart Farm Monitor', 'tags' => ['IoT']]);

        $experts = [
            'zed' => $this->expert('Zed', 'adviser'),
            'abe' => $this->expert('Abe', 'adviser', 1),
            'bea' => $this->expert('Bea', 'adviser'),
            'carl' => $this->expert('Carl', 'adviser'),
            'ann' => $this->expert('Ann', 'adviser', 0),
        ];

        ExpertExpertise::create(['expert_id' => $experts['zed']->expert_id, 'expertise_field' => 'IoT Systems']);
        ExpertExpertise::create(['expert_id' => $experts['abe']->expert_id, 'expertise_field' => 'IoT Systems']);
        ExpertExpertise::create(['expert_id' => $experts['bea']->expert_id, 'expertise_field' => 'Database Design']);

        $other = Team::create(['team_name' => 'Other Team', 'team_status' => 'Active', 'is_confirmed' => true]);
        $this->accept($other, $experts['abe']);

        return [$student, $experts];
    }

    /** The card markup of one expert, from its opening tag to its closing one. */
    private function card(string $html, CapstoneExpert $expert): string
    {
        $head = strrpos(substr($html, 0, strpos($html, 'data-expert-id="'.$expert->expert_id.'"')), '<article');

        return substr($html, $head, strpos($html, '</article>', $head) - $head);
    }

    public function test_find_experts_lists_tag_matches_first_then_open_slots_then_names(): void
    {
        [$student, $experts] = $this->iotTeamAndExperts();

        $html = $this->actingAs($student, 'web')
            ->get(route('student.experts', ['role' => 'adviser']))->assertOk()->getContent();

        $positions = array_map(fn ($expert) => strpos($html, 'data-expert-id="'.$expert->expert_id.'"'), $experts);
        $this->assertNotContains(false, $positions);

        asort($positions);
        $this->assertSame(['zed', 'abe', 'bea', 'carl', 'ann'], array_keys($positions));
    }

    public function test_only_an_open_expert_who_covers_a_tag_is_marked_as_a_fit(): void
    {
        [$student, $experts] = $this->iotTeamAndExperts();

        $html = $this->actingAs($student, 'web')
            ->get(route('student.experts', ['role' => 'adviser']))->getContent();

        $zed = $this->card($html, $experts['zed']);
        $this->assertStringContainsString('xh-fit', $zed);
        $this->assertStringContainsString('Fits your team', $zed);
        $this->assertStringContainsString('xh-tag is-match">IoT Systems', $zed);
        $this->assertStringContainsString('data-side="open"', $zed);

        // Abe covers the tag too, but his only slot is taken.
        $abe = $this->card($html, $experts['abe']);
        $this->assertStringNotContainsString('xh-fit', $abe);
        $this->assertStringNotContainsString('is-match', $abe);
        $this->assertStringContainsString('data-side="closed"', $abe);
        $this->assertStringContainsString('data-side="closed"', $this->card($html, $experts['ann']));

        $this->assertStringContainsString('<b>3</b> with open slots · <b>1</b> full', $html);
        $this->assertStringContainsString('<b>1</b> not taking teams', $html);
        $this->assertStringContainsString('<b class="is-fit">1</b>', $html);
    }

    public function test_a_team_without_title_tags_sees_no_fit_marks(): void
    {
        [$student] = $this->leader();
        $expert = $this->expert('Zed', 'adviser');
        ExpertExpertise::create(['expert_id' => $expert->expert_id, 'expertise_field' => 'IoT Systems']);

        $html = $this->actingAs($student, 'web')
            ->get(route('student.experts', ['role' => 'adviser']))->getContent();

        $this->assertStringContainsString('data-expert-id="'.$expert->expert_id.'"', $html);
        $this->assertStringNotContainsString('Fits your team', $html);
        $this->assertStringNotContainsString('is-match', $html);
        $this->assertStringNotContainsString('fit your team', $html);
    }

    /** View profile is a small link, not a full button, so two rows of cards fit on screen. */
    public function test_view_profile_is_a_link_on_each_card(): void
    {
        [$student] = $this->leader();
        $expert = $this->expert('Neil', 'adviser');

        $card = $this->card($this->actingAs($student, 'web')
            ->get(route('student.experts', ['role' => 'adviser']))->getContent(), $expert);

        $this->assertStringContainsString('class="xh-viewlink" data-xh="profile"', $card);
        $this->assertStringContainsString('View profile <span aria-hidden="true">→</span>', $card);
        $this->assertStringNotContainsString('class="xh-btn xh-ghost" data-xh="profile"', $card);
        $this->assertStringContainsString('.xh-viewlink{', file_get_contents(resource_path('student-ui/Styles/experts.css')));
    }

    /** View profile is the card the expert sees as "What students see", with Apply under it. */
    public function test_view_profile_shows_the_card_the_expert_previews(): void
    {
        [$student] = $this->leader();
        $expert = $this->expert('Neil', 'adviser');
        $expert->update(['degree_title' => 'MIT', 'credentials' => 'MIT']);

        $html = $this->actingAs($student, 'web')
            ->get(route('student.experts', ['role' => 'adviser']))->assertOk()->getContent();

        // The payload is written by Js::from, which escapes its quotes.
        $field = fn (string $key, string $value) => Str::between((string) Js::from([$key => $value]), '{', '}');
        $this->assertStringContainsString($field('profileName', $expert->first_name.' '.$expert->last_name.', MIT'), $html);
        $this->assertStringContainsString($field('email', $expert->email), $html);
        $this->assertStringContainsString('class="xh-prof-head"', $html);
        $this->assertStringContainsString('No ratings from team leaders yet', $html);
        $this->assertStringContainsString('data-xh="apply"', $html);

        $css = file_get_contents(resource_path('student-ui/Styles/experts.css'));
        foreach (['.xh-prof-head{', '.xh-prof-meter span{', '.xh-prof-meter span.is-taken{', '.xh-prof-rating{'] as $rule) {
            $this->assertStringContainsString($rule, $css);
        }
    }

    /** A merge once kept main's stylesheet and left these as bare browser buttons. */
    public function test_the_find_experts_controls_keep_their_styles(): void
    {
        $css = file_get_contents(resource_path('student-ui/Styles/experts.css'));

        foreach (['.xh-bar{', '.xh-side{', '.xh-side button{', '.xh-pgnav{', '.xh-pg{', '.xh-dots{', '.xh-xcard.xh-fit{', '.xh-fitmark{', '.xh-xcard.is-closed{'] as $rule) {
            $this->assertStringContainsString($rule, $css);
        }
    }
}
