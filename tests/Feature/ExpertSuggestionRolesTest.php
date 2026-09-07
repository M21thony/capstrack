<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\Role;
use App\Services\OllamaSSHClient;
use App\Services\SimilarityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Suggesting an expert for each role, rather than three experts overall.
 *
 * A CaPSA asked how this would ever suggest an adviser whose strength is
 * research writing, and the honest answer used to be that it would not: every
 * student-facing expert was ranked against the proposal on expertise alone
 * and the top three were returned, with no mention anywhere of what role any
 * of them held. On a machine-learning proposal a machine-learning consultant
 * beats a writing-focused adviser every time, because the proposal reads as a
 * machine-learning problem.
 *
 * They are ranked inside their own role now, and these pin down the part that
 * would break silently: which candidates each call is even allowed to pick
 * from.
 */
class ExpertSuggestionRolesTest extends TestCase
{
    use RefreshDatabase;

    private function expert(string $name, string $roleName, array $expertise): CapstoneExpert
    {
        [$first, $last] = explode(' ', $name, 2);

        $expert = CapstoneExpert::factory()->create([
            'first_name' => $first,
            'last_name' => $last,
            'approved_status' => true,
            'is_admin' => false,
        ]);

        $expert->roles()->attach(Role::firstOrCreate(['role_name' => $roleName])->role_id);

        foreach ($expertise as $field) {
            $expert->expertise()->create(['expertise_field' => $field]);
        }

        return $expert;
    }

    private function concepts(): array
    {
        return [
            'domain' => 'Machine Learning',
            'keywords' => ['neural networks', 'classification'],
            'main_topic' => 'A model that grades essays',
            'problem_statement' => 'Marking is slow and inconsistent.',
        ];
    }

    /**
     * The model is asked once per role and always answers with the first
     * candidate it is shown, so what the result proves is the shortlist each
     * call was handed - not the model's taste.
     */
    private function alwaysPicksTheFirst(): void
    {
        $this->mock(OllamaSSHClient::class, function ($mock) {
            $mock->shouldReceive('warmup')->andReturn(null);
            $mock->shouldReceive('generate')->andReturnUsing(function (string $prompt) {
                preg_match('/"id":(\d+)/', $prompt, $m);

                // A one-entry shortlist: the answer is now an array, and the
                // first candidate is the only one it ever names.
                return '[{"id":'.($m[1] ?? 0).',"reason":"Because."}]';
            });
        });
    }

    /**
     * Adviser and consultant, and nobody else.
     *
     * A grammarian is engaged for how the paper reads rather than what it is
     * about, so ranking one against the proposal's subject recommends on the
     * wrong grounds entirely. Teams pick theirs from the full list, where
     * every grammarian is equally visible.
     */
    public function test_only_advisers_and_consultants_are_recommended(): void
    {
        $this->expert('Neil Inocencio', 'adviser', ['Research Methodology', 'Academic Writing']);
        $this->expert('Rosa Villanueva', 'consultant', ['Machine Learning']);
        $this->expert('Marites Bandola', 'grammarian', ['Technical Writing']);

        $this->alwaysPicksTheFirst();

        $out = app(SimilarityService::class)->suggestInstructors($this->concepts());

        $this->assertSame(['adviser', 'consultant'], array_unique(array_column($out, 'role')));
        $this->assertNotContains('grammarian', array_column($out, 'role'));
        $this->assertNotContains('Marites Bandola', array_column($out, 'name'));

        // The bare role is what is stored; "Recommended" is added where the
        // panel is drawn, so a scan that already ran reads the same way.
        $this->assertSame(['Adviser', 'Consultant'], array_unique(array_column($out, 'role_label')));
    }

    /**
     * Several names per role, not one.
     *
     * Which consultant suits a study is a judgement the team is better placed
     * to make. One name reads as an instruction; a shortlist reads as what it
     * actually is.
     */
    public function test_more_than_one_expert_is_named_for_a_role(): void
    {
        foreach (['Neil Inocencio', 'Rosa Villanueva', 'Ana Cruz', 'Rico Dizon'] as $name) {
            $this->expert($name, 'consultant', ['Machine Learning']);
        }

        $this->alwaysPicksTheFirst();

        $consultants = collect(app(SimilarityService::class)->suggestInstructors($this->concepts()))
            ->where('role', 'consultant');

        $this->assertGreaterThan(1, $consultants->count());
        // Capped, so the panel stays a shortlist rather than the whole roster.
        $this->assertLessThanOrEqual(3, $consultants->count());
        // And never the same person twice, however often the model says them.
        $this->assertSame(
            $consultants->pluck('id')->unique()->count(),
            $consultants->count()
        );
    }

    /**
     * The heart of the CaPSA's question. A machine-learning specialist cannot
     * take the adviser slot from a writing-focused adviser, because they are
     * not on the adviser shortlist at all.
     */
    public function test_a_consultant_cannot_win_the_adviser_slot(): void
    {
        $this->expert('Neil Inocencio', 'adviser', ['Research Methodology', 'Academic Writing']);
        $this->expert('Rosa Villanueva', 'consultant', ['Machine Learning', 'Neural Networks']);

        $this->alwaysPicksTheFirst();

        $out = collect(app(SimilarityService::class)->suggestInstructors($this->concepts()))
            ->keyBy('role');

        $this->assertSame('Neil Inocencio', $out['adviser']['name']);
        $this->assertSame('Rosa Villanueva', $out['consultant']['name']);
    }

    /** An expert holding two roles is a candidate for both. */
    public function test_somebody_who_holds_two_roles_is_offered_for_both(): void
    {
        $both = $this->expert('Neil Inocencio', 'adviser', ['Research Methodology']);
        $both->roles()->attach(Role::firstOrCreate(['role_name' => 'consultant'])->role_id);

        $this->alwaysPicksTheFirst();

        $out = collect(app(SimilarityService::class)->suggestInstructors($this->concepts()))
            ->keyBy('role');

        $this->assertSame('Neil Inocencio', $out['adviser']['name']);
        $this->assertSame('Neil Inocencio', $out['consultant']['name']);
    }

    /** A role nobody holds is left out rather than filled with anybody. */
    public function test_a_role_with_no_candidates_is_skipped(): void
    {
        $this->expert('Neil Inocencio', 'adviser', ['Research Methodology']);

        $this->alwaysPicksTheFirst();

        $out = app(SimilarityService::class)->suggestInstructors($this->concepts());

        $this->assertCount(1, $out);
        $this->assertSame('adviser', $out[0]['role']);
    }

    /**
     * When the model is unreachable or answers with nonsense the panel still
     * names somebody who holds the role, with a plain reason - an empty panel
     * tells a team nothing.
     */
    public function test_an_unusable_answer_still_names_somebody(): void
    {
        $this->expert('Neil Inocencio', 'adviser', ['Research Methodology']);

        $this->mock(OllamaSSHClient::class, function ($mock) {
            $mock->shouldReceive('warmup')->andReturn(null);
            $mock->shouldReceive('generate')->andReturn('the model said something unparseable');
        });

        $out = app(SimilarityService::class)->suggestInstructors($this->concepts());

        $this->assertCount(1, $out);
        $this->assertSame('Neil Inocencio', $out[0]['name']);
        $this->assertStringContainsString('adviser', $out[0]['reason']);
    }

    /** A deactivated expert is nobody's suggestion. */
    public function test_a_deactivated_expert_is_not_suggested(): void
    {
        $this->expert('Neil Inocencio', 'adviser', ['Research Methodology'])->delete();
        $this->expert('Rosa Villanueva', 'consultant', ['Machine Learning']);

        $this->alwaysPicksTheFirst();

        $out = app(SimilarityService::class)->suggestInstructors($this->concepts());

        $this->assertSame(['Rosa Villanueva'], array_column($out, 'name'));
    }
}
