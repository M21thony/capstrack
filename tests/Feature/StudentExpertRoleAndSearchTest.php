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
use Tests\TestCase;

/**
 * The expert page shows one role at a time and searches by name or
 * expertise. Both happen in the query rather than in the browser: the list
 * is paginated, so a filter applied to the rendered page would only ever
 * search the experts already on screen and report "none" for the rest.
 */
class StudentExpertRoleAndSearchTest extends TestCase
{
    use RefreshDatabase;

    private function leader(): array
    {
        $student = Student::create([
            'email' => 'leader@my.cspc.edu.ph',
            'f_name' => 'Team',
            'l_name' => 'Leader',
        ]);

        $team = Team::create([
            'team_name' => 'Applying Team',
            'team_status' => 'Active',
            'leader_id' => $student->student_id,
            'is_confirmed' => true,
            'member_count' => 5,
        ]);

        StudentSchoolYear::create(['student_id' => $student->student_id, 'team_id' => $team->team_id]);

        return [$student, $team];
    }

    private function expert(string $first, string $last, array $roles, array $skills = []): CapstoneExpert
    {
        $expert = CapstoneExpert::factory()->create([
            'first_name' => $first,
            'last_name' => $last,
            'approved_status' => true,
            'max_teams' => 4,
        ]);

        foreach ($roles as $role) {
            $expert->roles()->attach(Role::firstOrCreate(['role_name' => $role])->role_id);
        }

        foreach ($skills as $skill) {
            ExpertExpertise::create(['expert_id' => $expert->expert_id, 'expertise_field' => $skill]);
        }

        return $expert;
    }

    public function test_choosing_a_role_lists_only_the_experts_who_hold_it(): void
    {
        [$student] = $this->leader();

        $adviser = $this->expert('Neil', 'Inocencio', ['adviser']);
        $grammarian = $this->expert('Elena', 'Marquez', ['grammarian']);

        $response = $this->actingAs($student, 'web')->get('/student/experts?role=grammarian');

        $response->assertStatus(200);
        $response->assertSee('data-expert-id="'.$grammarian->expert_id.'"', false);
        $response->assertDontSee('data-expert-id="'.$adviser->expert_id.'"', false);
    }

    /**
     * The count printed on each role card has to be the number choosing that
     * role actually shows, or it is worse than no number at all.
     */
    public function test_each_role_card_counts_the_experts_that_role_would_list(): void
    {
        [$student] = $this->leader();

        $this->expert('Neil', 'Inocencio', ['adviser']);
        $this->expert('Grace', 'Villanueva', ['adviser', 'consultant']);
        $this->expert('Ramon', 'Salazar', ['consultant']);

        $html = $this->actingAs($student, 'web')->get('/student/experts')->getContent();

        $this->assertStringContainsString('2 experts', $html);   // advisers
        $this->assertStringContainsString('0 experts', $html);   // grammarians
    }

    /**
     * Searching has to reach past the page you are looking at. With the
     * filter in the browser, an expert on page two was simply invisible.
     */
    public function test_search_finds_an_expert_who_is_not_on_the_first_page(): void
    {
        [$student] = $this->leader();

        // Comfortably past the 24 a page holds.
        for ($i = 0; $i < 30; $i++) {
            $this->expert('Filler'.$i, 'Person'.$i, ['adviser']);
        }

        $needle = $this->expert('Zenaida', 'Yabut', ['adviser']);

        $first = $this->actingAs($student, 'web')->get('/student/experts?role=adviser');
        $first->assertDontSee('data-expert-id="'.$needle->expert_id.'"', false);

        $found = $this->actingAs($student, 'web')->get('/student/experts?role=adviser&q=Yabut');
        $found->assertSee('data-expert-id="'.$needle->expert_id.'"', false);
    }

    public function test_search_matches_expertise_as_well_as_names(): void
    {
        [$student] = $this->leader();

        $match = $this->expert('Ramon', 'Salazar', ['consultant'], ['Cloud Computing']);
        $other = $this->expert('Oscar', 'Refuerzo', ['consultant'], ['Proofreading']);

        $response = $this->actingAs($student, 'web')->get('/student/experts?role=consultant&q=cloud');

        $response->assertSee('data-expert-id="'.$match->expert_id.'"', false);
        $response->assertDontSee('data-expert-id="'.$other->expert_id.'"', false);
    }

    /**
     * Every word has to land somewhere, so a two-word search narrows rather
     * than widening - "ramon cloud" is one person, not everyone called Ramon
     * plus everyone doing cloud work.
     */
    public function test_every_search_word_has_to_match(): void
    {
        [$student] = $this->leader();

        $both = $this->expert('Ramon', 'Salazar', ['consultant'], ['Cloud Computing']);
        $nameOnly = $this->expert('Ramon', 'Delos Reyes', ['consultant'], ['Proofreading']);

        $response = $this->actingAs($student, 'web')->get('/student/experts?role=consultant&q=ramon+cloud');

        $response->assertSee('data-expert-id="'.$both->expert_id.'"', false);
        $response->assertDontSee('data-expert-id="'.$nameOnly->expert_id.'"', false);
    }

    /**
     * The search box has to be submittable. The filter row also holds the
     * skill picker, which has a text box of its own, and a form with two text
     * fields and no submit button does not submit on Enter at all - so the
     * search silently did nothing.
     */
    public function test_the_search_form_has_a_submit_button(): void
    {
        [$student] = $this->leader();
        $this->expert('Neil', 'Inocencio', ['adviser']);

        $html = $this->actingAs($student, 'web')->get('/student/experts')->getContent();

        $form = substr($html, strpos($html, '<form class="filters"'));
        $form = substr($form, 0, strpos($form, '</form>'));

        $this->assertStringContainsString('name="q"', $form);
        $this->assertStringContainsString('type="submit"', $form);
    }

    /** With no role asked for, open on the slot the team still has to fill. */
    public function test_the_page_opens_on_the_first_unfilled_slot(): void
    {
        [$student, $team] = $this->leader();

        $adviser = $this->expert('Neil', 'Inocencio', ['adviser']);
        $consultant = $this->expert('Ramon', 'Salazar', ['consultant']);

        TeamExpertAssignment::create([
            'team_id' => $team->team_id,
            'expert_id' => $adviser->expert_id,
            'expert_role_id' => ExpertRole::where('expert_id', $adviser->expert_id)->value('expert_role_id'),
            'status' => 'approved',
        ]);

        $response = $this->actingAs($student, 'web')->get('/student/experts');

        // The adviser slot is settled, so the page opens on consultants.
        $response->assertSee('data-expert-id="'.$consultant->expert_id.'"', false);
        $response->assertSee('role-consultant is-on', false);
    }

    /** A role nobody asked for is not a role - a junk value falls back. */
    public function test_an_unknown_role_is_ignored_rather_than_listing_nothing(): void
    {
        [$student] = $this->leader();
        $adviser = $this->expert('Neil', 'Inocencio', ['adviser']);

        $response = $this->actingAs($student, 'web')->get('/student/experts?role=wizard');

        $response->assertStatus(200);
        $response->assertSee('data-expert-id="'.$adviser->expert_id.'"', false);
    }
}
