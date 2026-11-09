<?php

namespace Tests\Feature;

use App\Models\ArchivedDocument;
use App\Models\CapstonePreProposal;
use App\Models\DuplicationCheck;
use App\Models\StudentSchoolYear;
use App\Services\OllamaSSHClient;
use App\Services\SimilarityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\BuildsChecklists;
use Tests\TestCase;

/**
 * Panel comment 5: the match page says how its score was reached - the
 * meaning match, the AI judge, which of the two is shown - with sources.
 */
class ScoreWorkedOutTest extends TestCase
{
    use BuildsChecklists;
    use RefreshDatabase;

    private const CONCEPTS = [
        'title' => 'Barangay Health Records', 'main_topic' => 'health records', 'domain' => 'Web System',
        'keywords' => ['health', 'records'], 'problem_statement' => 'Paper records.', 'proposed_solution' => 'A web system.',
        'scope' => 'One barangay.', 'technologies' => ['Laravel'], 'target_users' => 'Health workers',
    ];

    /** Six archive studies pointing the same way as the query, so step 1 reads 100%. */
    private function archive(): void
    {
        config(['services.ollama.judge_limit' => 2]);
        foreach (range(1, 6) as $n) {
            $doc = ArchivedDocument::create([
                'team_id' => 0, 'title' => "Capstone {$n}", 'abstract' => 'About it.',
                'academic_year' => '2024', 'program' => 'BSIT', 'file_path' => "archive/{$n}.pdf",
            ]);
            $doc->embedding = json_encode(array_fill(0, 8, 0.5));
            $doc->save();
        }
    }

    /** The judged ones, after the AI judge answered with this verdict. */
    private function judgedWhenJudgeSays(array $verdict): array
    {
        $this->archive();
        $answer = json_encode($verdict + ['reason' => 'Read both.']);
        $ollama = $this->mock(OllamaSSHClient::class);
        $ollama->shouldReceive('embed')->andReturn(array_fill(0, 8, 0.5));
        $ollama->shouldReceive('generateMany')->andReturn([$answer, $answer]);
        $ollama->shouldReceive('generate')->andReturn($answer);

        return array_values(array_filter(
            app(SimilarityService::class)->findSimilarDocuments(self::CONCEPTS, 10),
            fn ($m) => $m['assessed'],
        ));
    }

    /** The match page for a saved check holding just this one match. */
    private function page(array $match)
    {
        [$team, $leader] = $this->teamInSection('How');
        $check = DuplicationCheck::create([
            'student_s_y_id' => StudentSchoolYear::where('student_id', $leader->student_id)->value('s_y_id'),
            'document_path' => 'checks/h.pdf', 'concepts' => [], 'top_score' => 74, 'verdict' => 'High Similarity',
            'matches' => [$match + ['title' => 'Health Center Records System', 'academic_year' => '2024', 'program' => 'BSIT', 'reason' => 'Close.', 'abstract' => 'About it.', 'authors' => '']],
            'instructors_suggested' => [],
        ]);
        CapstonePreProposal::create([
            'team_id' => $team->team_id, 'duplication_check_id' => $check->id, 'slot' => 1, 'title' => 'Barangay Health Records',
            'sdg_goals' => '3', 'research_agenda' => [], 'problem_statement' => 'Paper records.', 'proposed_solution' => 'A web system.',
            'scope' => 'One barangay.', 'limitations' => 'Web only.', 'sdm' => 'Agile',
        ]);

        return $this->actingAs($leader)->get(route('student.duplication.similar', [$check->id, 1]))->assertOk();
    }

    public function test_the_page_says_when_the_exact_words_set_the_score(): void
    {
        $this->page(['score' => 100, 'embedding_score' => 99, 'judge_score' => 85, 'exact_score' => 100, 'assessed' => true, 'is_relevant' => true])
            ->assertSee('Exact words')
            ->assertSee('The exact words are used');
    }

    public function test_the_meaning_match_is_kept_beside_the_judges_score(): void
    {
        $judged = $this->judgedWhenJudgeSays(['matches' => true, 'score' => 71]);

        $this->assertCount(2, $judged);
        foreach ($judged as $m) {
            $this->assertSame(100, $m['embedding_score']);
            $this->assertSame(71, $m['judge_score']);
            $this->assertSame(71, $m['score']);
        }
    }

    public function test_a_contradicting_rejection_keeps_the_judges_number_and_shows_the_cut_one(): void
    {
        $judged = $this->judgedWhenJudgeSays(['matches' => false, 'score' => 80]);

        foreach ($judged as $m) {
            $this->assertFalse($m['is_relevant']);
            $this->assertSame(80, $m['judge_score']);
            $this->assertSame(100, $m['embedding_score']);
            $this->assertSame(40, $m['score'], '100 × 0.4');
        }
    }

    public function test_a_check_saves_both_numbers_with_each_match(): void
    {
        [, $leader] = $this->teamInSection('Save');
        $doc = ArchivedDocument::create([
            'team_id' => 0, 'title' => 'Health Center Records System', 'abstract' => 'About it.',
            'academic_year' => '2024', 'program' => 'BSIT', 'file_path' => 'archive/h.pdf',
        ]);
        $this->mock(OllamaSSHClient::class)->shouldReceive('warmup')->andReturn(null);
        $this->mock(SimilarityService::class, function ($mock) use ($doc) {
            $mock->shouldReceive('extractPdfText')->andReturn('Text.');
            $mock->shouldReceive('isPreProposal')->andReturn(['is_pre_proposal' => true, 'reason' => '']);
            $mock->shouldReceive('extractKeyConcepts')->andReturn(self::CONCEPTS);
            $mock->shouldReceive('findSimilarDocuments')->andReturn([
                ['document' => $doc, 'score' => 71, 'embedding_score' => 85, 'judge_score' => 71, 'reason' => 'Close.', 'is_relevant' => true, 'assessed' => true, 'matched_on' => []],
            ]);
            $mock->shouldReceive('suggestInstructors')->andReturn([]);
            $mock->shouldReceive('extractHighlightWords')->andReturn(['records']);
        });

        $this->actingAs($leader)->post(route('student.pre-proposal.check'), [
            'document' => UploadedFile::fake()->create('proposal.pdf', 10, 'application/pdf'),
        ])->assertOk();

        $saved = DuplicationCheck::latest('id')->first()->matches[0];
        $this->assertSame(85, $saved['embedding_score']);
        $this->assertSame(71, $saved['judge_score']);
    }

    public function test_the_match_page_shows_the_three_steps_with_this_titles_numbers_and_the_sources(): void
    {
        $this->page(['score' => 74, 'embedding_score' => 81, 'judge_score' => 74, 'assessed' => true, 'is_relevant' => true])
            ->assertSee('Score explanation')
            ->assertSeeInOrder(['Meaning match', '(0.62 + 1) ÷ 2 × 100 = 81%', 'AI judge', 'same kind of study', '74%', 'Score shown', "The judge's score is used."], false)
            ->assertSee('Salton, G., &amp; McGill, M. J. (1983)', false)
            ->assertSee('Sentence-BERT')
            ->assertSee('arXiv:2402.01613')
            ->assertSee('Judging LLM-as-a-Judge');
    }

    public function test_the_match_page_explains_a_cut_score_an_unread_title_and_an_older_check(): void
    {
        $this->page(['score' => 28, 'embedding_score' => 71, 'judge_score' => 80, 'assessed' => true, 'is_relevant' => false])
            ->assertSee('contradicts itself')
            ->assertSee('71 × 0.4 = 28');

        $this->page(['score' => 30, 'embedding_score' => 75, 'judge_score' => null, 'assessed' => false])
            ->assertSee('Not read. Only the')
            ->assertSee('No score is shown for a title the judge did not read.');

        $this->page(['score' => 60])
            ->assertSee('This check was run before this number was saved.')
            ->assertDontSee('× 0.4');
    }
}
