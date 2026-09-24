<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\DuplicationCheck;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "Recommended Adviser" reaches the card, on every card.
 *
 * This took three goes, and each failure was the same shape. The label was
 * baked into the stored array, so scans that had already run kept the old
 * wording. Then it was added at render time - to two of the three places
 * that draw a suggestion, and not to the panel anybody actually looks at.
 * Then it was added there too, and old checks still showed nothing, because
 * they carry no role to label at all.
 *
 * So: the source has to carry it everywhere, and a check written before the
 * panel knew about roles has to get one from the expert.
 */
class SuggestionLabelsTest extends TestCase
{
    use RefreshDatabase;

    private function expert(string $name, string $role): CapstoneExpert
    {
        [$first, $last] = explode(' ', $name, 2);

        $expert = CapstoneExpert::factory()->create([
            'first_name' => $first, 'last_name' => $last,
            'approved_status' => true, 'is_admin' => false,
        ]);

        $expert->roles()->attach(Role::firstOrCreate(['role_name' => $role])->role_id);

        return $expert;
    }

    private function checkSuggesting(array $entries): DuplicationCheck
    {
        $student = Student::create([
            'email' => 'holder@my.cspc.edu.ph', 'f_name' => 'Mark', 'l_name' => 'Balilla',
            'course' => 'BSIT', 'year_section' => '4th · A',
        ]);

        $sy = StudentSchoolYear::create(['student_id' => $student->student_id]);

        return DuplicationCheck::create([
            'student_s_y_id' => $sy->s_y_id,
            'document_path' => 'duplication-checks/x.pdf',
            'concepts' => ['title' => 'A study'],
            'top_score' => 20,
            'verdict' => 'Passed',
            'matches' => [],
            'instructors_suggested' => $entries,
        ]);
    }

    /** Every place that draws a suggestion says "Recommended". */
    public function test_every_renderer_carries_the_label(): void
    {
        // There were three of these, all drawing the same card: the live list
        // after a check, the saved title's list, and a third copy inside the
        // report pop-up. The pop-up is a page now, so the saved title's list
        // moved there and the third copy went with the pop-up. Counted by the
        // markup they emit, not the class name, which is also a CSS rule.
        $drawn = 0;

        // The report draws its cards from one partial.
        foreach (['duplication.blade.php', 'duplication/_expert.blade.php'] as $view) {
            $drawn += substr_count(
                file_get_contents(resource_path('views/student/'.$view)),
                'class="sx-role">Recommended '
            );
        }

        $this->assertSame(2, $drawn, 'a renderer without the label is one the student sees bare');
    }

    /** A stored label is left exactly as it is. */
    public function test_a_stored_role_is_kept(): void
    {
        $expert = $this->expert('Neil Inocencio', 'adviser');

        $out = $this->checkSuggesting([[
            'id' => $expert->expert_id, 'name' => $expert->name,
            'expertise' => 'Research Methodology', 'reason' => 'Because.',
            'role' => 'adviser', 'role_label' => 'Adviser',
        ]])->suggestionsForDisplay();

        $this->assertSame('Adviser', $out[0]['role_label']);
    }

    /**
     * The case that kept the card bare: a check run before the panel knew
     * about roles. Nothing displays a field that was never written, and no
     * amount of editing the template fixes that - the role has to come from
     * the expert instead.
     */
    public function test_a_check_with_no_role_gets_one_from_the_expert(): void
    {
        $expert = $this->expert('Rosa Villanueva', 'consultant');

        $out = $this->checkSuggesting([[
            'id' => $expert->expert_id, 'name' => $expert->name,
            'expertise' => 'Machine Learning', 'reason' => 'Because.',
        ]])->suggestionsForDisplay();

        $this->assertCount(1, $out);
        $this->assertSame('consultant', $out[0]['role']);
        $this->assertSame('Consultant', $out[0]['role_label']);
    }

    /** Somebody holding both is shown as the adviser. */
    public function test_adviser_wins_when_they_hold_both(): void
    {
        $expert = $this->expert('Mark Balilla', 'adviser');
        $expert->roles()->attach(Role::firstOrCreate(['role_name' => 'consultant'])->role_id);

        $out = $this->checkSuggesting([[
            'id' => $expert->expert_id, 'name' => $expert->name, 'expertise' => 'x', 'reason' => '',
        ]])->suggestionsForDisplay();

        $this->assertSame('Adviser', $out[0]['role_label']);
    }

    /**
     * A grammarian on an old check is dropped, not relabelled. They were
     * picked on grounds this panel no longer recommends by.
     */
    public function test_an_old_grammarian_suggestion_is_dropped(): void
    {
        $expert = $this->expert('Marites Bandola', 'grammarian');

        $out = $this->checkSuggesting([[
            'id' => $expert->expert_id, 'name' => $expert->name, 'expertise' => 'x', 'reason' => '',
        ]])->suggestionsForDisplay();

        $this->assertSame([], $out);
    }

    /** An id that is nobody is dropped rather than guessed at. */
    public function test_a_suggestion_naming_nobody_is_dropped(): void
    {
        $out = $this->checkSuggesting([
            ['id' => 999999, 'name' => 'Ghost', 'expertise' => 'x', 'reason' => ''],
        ])->suggestionsForDisplay();

        $this->assertSame([], $out);
    }

    // ── clearing a scan ───────────────────────────────────────────────────

    /**
     * The result is drawn again on every load, so there has to be a way to
     * be rid of it. A panel that survives a refresh and cannot be dismissed
     * is its own kind of stuck.
     */
    public function test_a_team_can_clear_their_own_scan(): void
    {
        $student = Student::create([
            'email' => 'leader@my.cspc.edu.ph', 'f_name' => 'Mark', 'l_name' => 'Balilla',
            'course' => 'BSIT', 'year_section' => '4th · A',
        ]);
        $team = \App\Models\Team::create(['team_name' => 'Bulbasaurus', 'leader_id' => $student->student_id]);
        $sy = StudentSchoolYear::create([
            'student_id' => $student->student_id, 'team_id' => $team->team_id,
        ]);

        $check = DuplicationCheck::create([
            'student_s_y_id' => $sy->s_y_id, 'document_path' => 'duplication-checks/x.pdf',
            'concepts' => [], 'top_score' => 20, 'verdict' => 'Passed',
            'matches' => [], 'instructors_suggested' => [],
        ]);

        $this->actingAs($student, 'web')
            ->deleteJson(route('student.pre-proposal.check.destroy', $check))
            ->assertOk();

        $this->assertNull(DuplicationCheck::find($check->id));
    }

    /** Not somebody else's, though. */
    public function test_another_teams_scan_cannot_be_cleared(): void
    {
        $owner = Student::create([
            'email' => 'owner@my.cspc.edu.ph', 'f_name' => 'Ana', 'l_name' => 'Cruz',
            'course' => 'BSIT', 'year_section' => '4th · A',
        ]);
        $ownerTeam = \App\Models\Team::create(['team_name' => 'Theirs', 'leader_id' => $owner->student_id]);
        $ownerSy = StudentSchoolYear::create([
            'student_id' => $owner->student_id, 'team_id' => $ownerTeam->team_id,
        ]);

        $check = DuplicationCheck::create([
            'student_s_y_id' => $ownerSy->s_y_id, 'document_path' => 'x.pdf',
            'concepts' => [], 'top_score' => 20, 'verdict' => 'Passed',
            'matches' => [], 'instructors_suggested' => [],
        ]);

        $outsider = Student::create([
            'email' => 'outsider@my.cspc.edu.ph', 'f_name' => 'Rico', 'l_name' => 'Dizon',
            'course' => 'BSIT', 'year_section' => '4th · A',
        ]);
        $theirTeam = \App\Models\Team::create(['team_name' => 'Ours', 'leader_id' => $outsider->student_id]);
        StudentSchoolYear::create([
            'student_id' => $outsider->student_id, 'team_id' => $theirTeam->team_id,
        ]);

        $this->actingAs($outsider, 'web')
            ->deleteJson(route('student.pre-proposal.check.destroy', $check))
            ->assertForbidden();

        $this->assertNotNull(DuplicationCheck::find($check->id));
    }

    /** A scan behind a saved pre-proposal is evidence, not clutter. */
    public function test_a_scan_behind_a_saved_proposal_is_kept(): void
    {
        $student = Student::create([
            'email' => 'leader@my.cspc.edu.ph', 'f_name' => 'Mark', 'l_name' => 'Balilla',
            'course' => 'BSIT', 'year_section' => '4th · A',
        ]);
        $team = \App\Models\Team::create(['team_name' => 'Bulbasaurus', 'leader_id' => $student->student_id]);
        $sy = StudentSchoolYear::create([
            'student_id' => $student->student_id, 'team_id' => $team->team_id,
        ]);

        $check = DuplicationCheck::create([
            'student_s_y_id' => $sy->s_y_id, 'document_path' => 'x.pdf',
            'concepts' => [], 'top_score' => 20, 'verdict' => 'Passed',
            'matches' => [], 'instructors_suggested' => [],
        ]);

        \App\Models\CapstonePreProposal::create([
            'team_id' => $team->team_id, 'duplication_check_id' => $check->id, 'slot' => 1,
            'title' => 'A study of things', 'sdg_goals' => 'SDG 4', 'research_agenda' => ['x'],
            'problem_statement' => 'x', 'proposed_solution' => 'x', 'scope' => 'x',
            'limitations' => 'x', 'sdm' => 'Agile', 'duplication_status' => 'free',
        ]);

        $this->actingAs($student, 'web')
            ->deleteJson(route('student.pre-proposal.check.destroy', $check))
            ->assertStatus(422);

        $this->assertNotNull(DuplicationCheck::find($check->id));
    }
}
