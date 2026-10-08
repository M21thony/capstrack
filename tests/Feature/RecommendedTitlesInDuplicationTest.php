<?php

namespace Tests\Feature;

use App\Models\ArchivedDocument;
use App\Models\CapstonePreProposal;
use App\Models\DuplicationCheck;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Services\OllamaSSHClient;
use App\Services\SimilarityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * The archive only holds finished capstones, so a title another section had
 * recommended this year never came up. The duplication check now compares
 * against those too, labelled with the section, and shows only the title.
 */
class RecommendedTitlesInDuplicationTest extends TestCase
{
    use RefreshDatabase;

    private MockInterface $ollama;

    protected function setUp(): void
    {
        parent::setUp();

        // Below five embedded archive documents the check falls back to
        // keywords and never reaches the vector comparison.
        foreach (range(1, 5) as $n) {
            $doc = ArchivedDocument::create([
                'team_id' => 0, 'title' => "Capstone {$n}", 'abstract' => 'An abstract.',
                'academic_year' => '2025', 'program' => 'BSIT', 'file_path' => "archive/{$n}.pdf",
            ]);
            $doc->embedding = json_encode(array_fill(0, 8, 0.5));
            $doc->save();
        }

        $verdict = json_encode(['matches' => true, 'score' => 80, 'reason' => 'Same subject.']);
        $this->ollama = $this->mock(OllamaSSHClient::class);
        $this->ollama->shouldReceive('generateMany')->andReturnUsing(fn (array $prompts) => array_fill(0, count($prompts), $verdict));
        $this->ollama->shouldReceive('generate')->andReturn($verdict);
    }

    private function team(string $section): Team
    {
        $leader = Student::create([
            'email' => uniqid().'@my.cspc.edu.ph', 'f_name' => 'A', 'l_name' => 'Leader',
            'course' => 'BSIT', 'year_section' => '4th '.json_decode('"·"').' '.$section,
        ]);
        $team = Team::create([
            'team_name' => 'Team '.$section.uniqid(), 'team_status' => 'Active', 'leader_id' => $leader->student_id,
            'is_confirmed' => true, 'member_count' => 5,
        ]);
        StudentSchoolYear::create(['student_id' => $leader->student_id, 'team_id' => $team->team_id]);

        return $team;
    }

    private function proposal(Team $team, string $title, array $attributes = []): CapstonePreProposal
    {
        $check = DuplicationCheck::create([
            'student_s_y_id' => StudentSchoolYear::where('team_id', $team->team_id)->value('s_y_id'),
            'document_path' => 'duplication-checks/x.pdf', 'concepts' => [], 'top_score' => 20, 'verdict' => 'Passed', 'matches' => [], 'instructors_suggested' => [],
        ]);

        return CapstonePreProposal::create($attributes + [
            'team_id' => $team->team_id, 'duplication_check_id' => $check->id, 'slot' => 1, 'title' => $title,
            'sdg_goals' => '4', 'research_agenda' => [], 'problem_statement' => 'Records are kept on paper.',
            'proposed_solution' => 'A system to keep them.', 'scope' => 'One barangay.', 'limitations' => 'None.',
            'sdm' => 'Agile', 'approved_status' => true, 'submitted_at' => now(),
        ]);
    }

    private function check(?Team $checking = null): array
    {
        return app(SimilarityService::class)->findSimilarDocuments([
            'title' => 'Barangay Records', 'main_topic' => 'records', 'domain' => 'Web System',
            'keywords' => ['records'], 'problem_statement' => 'Paper records.', 'proposed_solution' => 'A system.', 'scope' => 'One barangay.',
        ], 10, $checking?->team_id);
    }

    private function recommendedTitles(array $matches): array
    {
        return array_values(array_map(fn ($m) => $m['document']->title, array_filter($matches, fn ($m) => $m['recommended'])));
    }

    public function test_another_sections_recommended_title_is_a_match_shown_by_title_only(): void
    {
        $this->ollama->shouldReceive('embed')->andReturn(array_fill(0, 8, 0.5));
        $this->proposal($this->team('B'), 'Barangay Records System');

        $match = collect($this->check($this->team('A')))->firstWhere('recommended');

        $this->assertSame('Barangay Records System', $match['document']->title);
        $this->assertSame('Recommended this year — BSIT-4B', $match['recommended']);
        $this->assertNull($match['document']->abstract, 'their work in progress is not shown to another team');
    }

    public function test_the_checking_teams_own_title_is_not_a_match(): void
    {
        $this->ollama->shouldReceive('embed')->andReturn(array_fill(0, 8, 0.5));
        $mine = $this->team('A');
        $this->proposal($mine, 'Our Own Title');

        $this->assertSame([], $this->recommendedTitles($this->check($mine)));
    }

    public function test_a_retired_or_unrecommended_title_is_not_a_match(): void
    {
        $this->ollama->shouldReceive('embed')->andReturn(array_fill(0, 8, 0.5));
        $this->proposal($this->team('B'), 'Set Aside', ['retired_at' => now()]);
        $this->proposal($this->team('C'), 'Still Pending', ['approved_status' => false]);

        $this->assertSame([], $this->recommendedTitles($this->check($this->team('A'))));
    }

    public function test_a_title_is_embedded_once_and_again_after_it_is_revised(): void
    {
        $proposal = $this->proposal($this->team('B'), 'Barangay Records System');

        // One for the checking title, one for theirs; the second check reads theirs from the column.
        $this->ollama->shouldReceive('embed')->times(3)->andReturn(array_fill(0, 8, 0.5));
        $this->check();
        $this->assertNotNull($proposal->fresh()->embedding);

        $this->check();
        $this->assertNotNull($proposal->fresh()->embedding);

        $proposal->fresh()->update(['title' => 'Barangay Records System v2']);
        $this->assertNull($proposal->fresh()->embedding, 'a revised title is read again');
    }
}
