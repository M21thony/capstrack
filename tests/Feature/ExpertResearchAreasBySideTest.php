<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\ExpertExpertise;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Support\DefenseBoard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * An expert keeps research areas per side: what they advise on, consult on,
 * check as a grammarian, and sit on panels for. Students browsing advisers
 * see - and filter by - the adviser side only.
 */
class ExpertResearchAreasBySideTest extends TestCase
{
    use RefreshDatabase;

    private function expert(array $roles): CapstoneExpert
    {
        $expert = CapstoneExpert::factory()->create(['approved_status' => true, 'max_teams' => 4, 'program_slots' => '4 - BSIT', 'first_login_at' => now()]);
        foreach ($roles as $role) {
            $expert->roles()->attach(Role::firstOrCreate(['role_name' => $role])->role_id);
        }

        return $expert;
    }

    private function save(CapstoneExpert $expert, array $bySide)
    {
        return $this->actingAs($expert, 'expert')->putJson(route('instructor.profile.update'), [
            'program_slots' => ['BSIT' => 4],
            'expertise_by_side' => $bySide,
        ]);
    }

    private function area(CapstoneExpert $expert, string $field, ?string $side): void
    {
        ExpertExpertise::create(['expert_id' => $expert->expert_id, 'expertise_field' => $field, 'side' => $side]);
    }

    public function test_an_expert_saves_a_list_for_each_side(): void
    {
        $expert = $this->expert(['adviser', 'consultant']);

        $this->save($expert, [
            'adviser' => ['Research Methods', 'Web Development'],
            'consultant' => ['Networking'],
            'panel' => ['Information Security', ' '],
        ])->assertOk()->assertJson(['success' => true]);

        $expert = $expert->fresh();
        $this->assertSame(['Research Methods', 'Web Development'], $expert->expertiseFor('adviser')->all());
        $this->assertSame(['Networking'], $expert->expertiseFor('consultant')->all());
        $this->assertSame(['Information Security'], $expert->expertiseFor('panel')->all());
        $this->assertSame(4, $expert->allExpertise()->count());
    }

    public function test_an_unknown_side_is_refused(): void
    {
        $this->save($this->expert(['adviser']), ['coach' => ['Anything']])->assertStatus(422);
    }

    public function test_an_older_page_sending_one_list_still_works(): void
    {
        $expert = $this->expert(['adviser']);

        $this->actingAs($expert, 'expert')->putJson(route('instructor.profile.update'), ['program_slots' => ['BSIT' => 2], 'expertise' => ['Databases']])
            ->assertOk();

        $this->assertSame(['Databases'], $expert->fresh()->expertiseFor('adviser')->all());
        $this->assertSame(['Databases'], $expert->fresh()->expertiseFor('panel')->all(), 'one list stands for every side');
    }

    public function test_a_side_not_filled_in_yet_falls_back_to_the_list_for_all(): void
    {
        $expert = $this->expert(['adviser', 'consultant']);
        $this->area($expert, 'Web Development', null);
        $this->area($expert, 'Networking', 'consultant');

        $this->assertSame(['Web Development'], $expert->expertiseFor('adviser')->all());
        $this->assertSame(['Networking'], $expert->expertiseFor('consultant')->all());
    }

    public function test_the_profile_offers_a_tab_for_each_role_held_and_for_panels(): void
    {
        $expert = $this->expert(['adviser', 'consultant']);

        $this->actingAs($expert, 'expert')->get(route('instructor.profile'))->assertOk()
            ->assertSee('data-side="adviser"', false)
            ->assertSee('data-side="consultant"', false)
            ->assertSee('data-side="panel"', false)
            ->assertDontSee('data-side="grammarian"', false)
            ->assertSee('Research areas');
    }

    public function test_the_picker_offers_every_area_and_accepts_ones_not_on_the_list(): void
    {
        $expert = CapstoneExpert::factory()->create(['approved_status' => true, 'max_teams' => 4, 'program_slots' => '4 - BLIS', 'first_login_at' => now()]);
        $expert->roles()->attach(Role::firstOrCreate(['role_name' => 'adviser'])->role_id);

        $this->actingAs($expert, 'expert')->get(route('instructor.profile'))->assertOk()
            ->assertSee('Embedded Systems')
            ->assertDontSee('EP_PROGRAM_SKILLS')
            ->assertSee('Search, or type your own research area');

        $this->save($expert, ['adviser' => ['Blockchain Governance']])->assertOk();

        $this->assertSame(['Blockchain Governance'], $expert->fresh()->expertiseFor('adviser')->all());
    }

    public function test_students_browsing_a_role_see_and_filter_by_that_side(): void
    {
        $leader = Student::factory()->withCompleteProfile()->create(['course' => 'Bachelor of Science in Information Technology']);
        $team = Team::create(['team_name' => 'Looking', 'team_status' => 'Active', 'is_confirmed' => true, 'leader_id' => $leader->student_id]);
        StudentSchoolYear::create(['student_id' => $leader->student_id, 'team_id' => $team->team_id]);

        $expert = $this->expert(['adviser', 'consultant']);
        $this->area($expert, 'Research Methods', 'adviser');
        $this->area($expert, 'Networking', 'consultant');

        $asAdviser = $this->actingAs($leader, 'web')->get(route('student.experts', ['tab' => 'find', 'role' => 'adviser']))->assertOk();
        $card = collect($asAdviser->viewData('experts'))->firstWhere('expert_id', $expert->expert_id);
        $this->assertNotNull($card);
        $asAdviser->assertSee('<span class="xh-tag">Research Methods</span>', false)
            ->assertDontSee('<span class="xh-tag">Networking</span>', false);

        // Networking is what they consult on, not what they advise on.
        $this->assertCount(0, $this->actingAs($leader, 'web')->get(route('student.experts', ['tab' => 'find', 'role' => 'adviser', 'skill' => 'Networking']))->viewData('experts'));
        $this->assertCount(1, $this->actingAs($leader, 'web')->get(route('student.experts', ['tab' => 'find', 'role' => 'consultant', 'skill' => 'Networking']))->viewData('experts'));
    }

    private function capsaWhoAdvises(): CapstoneExpert
    {
        $capsa = $this->makeCapsa($this->expert(['adviser']));
        $capsa->update(['sections' => ['BSIT-3A']]);

        return $capsa->fresh();
    }

    /** A CaPSA who also advises keeps the same per-side lists - on their profile page and their panel. */
    public function test_a_capsa_who_is_also_an_expert_gets_the_same_sides(): void
    {
        $capsa = $this->capsaWhoAdvises();

        $this->actingAs($capsa, 'expert')->get(route('capsa.profile'))->assertRedirect(route('instructor.profile'));
        $this->actingAs($capsa, 'expert')->get(route('instructor.profile'))->assertOk()
            ->assertSee('Research areas')
            ->assertSee('data-side="adviser"', false)
            ->assertSee('data-side="panel"', false)
            ->assertDontSee('data-side="consultant"', false);

        $this->actingAs($capsa, 'expert')->get(route('instructor.profile'))->assertOk()
            ->assertSee('data-side="adviser"', false)
            ->assertSee('data-side="panel"', false);
    }

    public function test_saving_the_capsa_profile_keeps_each_side(): void
    {
        $capsa = $this->capsaWhoAdvises();

        $this->actingAs($capsa, 'expert')->put(route('capsa.profile.update'), [
            'first_name' => 'Rosa', 'last_name' => 'Villanueva',
            'expertise_by_side' => json_encode(['adviser' => ['Research Methods'], 'panel' => ['Information Security'], 'coach' => ['Ignored']]),
        ])->assertRedirect(route('capsa.profile'));

        $capsa = $capsa->fresh();
        $this->assertSame(['Research Methods'], $capsa->expertiseFor('adviser')->all());
        $this->assertSame(['Information Security'], $capsa->expertiseFor('panel')->all());
        $this->assertSame(2, $capsa->expertise()->count(), 'an unknown side is dropped');

        // A save that sends no research areas leaves them alone.
        $this->actingAs($capsa, 'expert')->put(route('capsa.profile.update'), ['first_name' => 'Rosa', 'last_name' => 'Cruz'])->assertRedirect();
        $this->assertSame(['Research Methods'], $capsa->fresh()->expertiseFor('adviser')->all());
    }

    public function test_the_panel_picker_shows_the_panel_side(): void
    {
        $expert = $this->expert(['adviser']);
        $this->area($expert, 'Research Methods', 'adviser');
        $this->area($expert, 'Information Security', 'panel');

        $row = app(DefenseBoard::class)->panelists()->firstWhere('id', $expert->expert_id);

        $this->assertSame(['Information Security'], $row['tags']);
    }
}
