<?php

namespace Tests\Unit;

use App\Models\ArchivedDocument;
use App\Models\Team;
use App\Services\OllamaSSHClient;
use App\Services\SimilarityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SimilarityServiceScoreCappingTest extends TestCase
{
    use RefreshDatabase;

    // Regression: a document sharing only generic wording ("management
    // system") with the uploaded pre-proposal could score high on raw
    // cosine similarity even after the AI explicitly determined the two are
    // unrelated in purpose (e.g. a capstone titling tool vs. an inventory
    // and sales tracking system) - the displayed percentage never reflected
    // that rejection at all, reading as a false near-duplicate warning.
    public function test_a_match_the_ai_rejects_has_its_score_capped_regardless_of_raw_similarity(): void
    {
        $team = Team::create(['team_name' => 'Archive Source Team']);

        // The actual subject: near-identical embedding to the query, so raw
        // cosine similarity alone would score very high. `embedding` isn't
        // mass-assignable on ArchivedDocument, so it's set directly.
        $unrelated = ArchivedDocument::create([
            'team_id' => $team->team_id,
            'title' => "Web-Based Management System for CSPC's Production and Auxiliary Services with Inventory and Sales Tracking",
            'abstract' => 'Inventory and sales tracking for a school auxiliary office.',
            'academic_year' => 2025,
            'program' => 'BSIT',
            'file_path' => 'archives/unrelated.pdf',
        ]);
        $unrelated->embedding = json_encode([1.0, 0.0]);
        $unrelated->save();

        // Four low-similarity filler docs so findSimilarDocuments() has >= 5
        // embedded documents and doesn't fall back to the legacy keyword
        // search path.
        for ($i = 0; $i < 4; $i++) {
            $filler = ArchivedDocument::create([
                'team_id' => $team->team_id,
                'title' => "Unrelated Filler Document {$i}",
                'abstract' => 'Filler.',
                'academic_year' => 2024,
                'program' => 'BSIT',
                'file_path' => "archives/filler{$i}.pdf",
            ]);
            $filler->embedding = json_encode([-1.0, -1.0]);
            $filler->save();
        }

        $capturedPrompt = null;
        $this->mock(OllamaSSHClient::class, function ($mock) use (&$capturedPrompt) {
            $mock->shouldReceive('embed')->andReturn([1.0, 1.0]);
            $mock->shouldReceive('generate')->andReturnUsing(function ($prompt) use (&$capturedPrompt) {
                $capturedPrompt = $prompt;

                return json_encode(['matches' => false, 'reason' => 'Different institution and purpose.']);
            });
        });

        $concepts = [
            'title' => 'Capstone Management System',
            'main_topic' => 'Capstone title tracking',
            'domain' => 'Management System',
            'keywords' => ['capstone', 'titling'],
            'problem_statement' => 'p',
            'proposed_solution' => 's',
            'scope' => 'sc',
        ];

        $matches = app(SimilarityService::class)->findSimilarDocuments($concepts, 10);
        $match = collect($matches)->firstWhere(fn ($m) => $m['document']->archived_id === $unrelated->archived_id);

        $this->assertNotNull($match, 'Expected the unrelated document to appear in the raw candidate list.');
        $this->assertFalse($match['is_relevant']);
        $this->assertSame('Different institution and purpose.', $match['reason']);
        // Safety property: a rejected match can never reach the Moderate
        // (55) verdict threshold on its own, regardless of raw score.
        $this->assertLessThan(55, $match['score']);

        // Regression: the AI reasoning prompt must never contain a specific
        // institution/location name - telling the model to "ignore" one in
        // the prompt's instructions proved unreliable (it cited "specific
        // institution - CSPC" as its own reason for a match in a real,
        // observed case), so judgeOneCandidate() now redacts these before
        // building the prompt at all.
        $this->assertNotNull($capturedPrompt);
        $this->assertStringNotContainsStringIgnoringCase('cspc', $capturedPrompt);
        $this->assertStringContainsString('Production and Auxiliary Services with Inventory and Sales Tracking', $capturedPrompt);
    }

    // Regression: every rejected match was being floored to the exact same
    // flat percentage, which erased the difference between "shares nothing
    // but generic wording" and "still lexically very close, maybe worth a
    // second look" - a title literally containing "Capstone Project" should
    // read as more similar than one about a completely unrelated system,
    // even when the AI rejected both.
    public function test_rejected_matches_with_different_raw_scores_stay_distinguishable(): void
    {
        $team = Team::create(['team_name' => 'Archive Source Team 3']);

        // Close in wording to the query embedding (high raw cosine score).
        $closeButRejected = ArchivedDocument::create([
            'team_id' => $team->team_id,
            'title' => 'Capstone Project and Thesis Class Information Management System',
            'abstract' => 'Tracks capstone class scheduling.',
            'academic_year' => 2022,
            'program' => 'BSIT',
            'file_path' => 'archives/close.pdf',
        ]);
        $closeButRejected->embedding = json_encode([1.0, 0.2]);
        $closeButRejected->save();

        // Far from the query embedding (low raw cosine score) but still
        // rejected by the AI for the same reason text.
        $farAndRejected = ArchivedDocument::create([
            'team_id' => $team->team_id,
            'title' => 'Chronolock: Smart Door Lock System',
            'abstract' => 'A smart door lock for a laboratory.',
            'academic_year' => 2024,
            'program' => 'BSIT',
            'file_path' => 'archives/far.pdf',
        ]);
        $farAndRejected->embedding = json_encode([0.2, 1.0]);
        $farAndRejected->save();

        for ($i = 0; $i < 3; $i++) {
            $filler = ArchivedDocument::create([
                'team_id' => $team->team_id,
                'title' => "Unrelated Filler Document {$i}",
                'abstract' => 'Filler.',
                'academic_year' => 2024,
                'program' => 'BSIT',
                'file_path' => "archives/filler3-{$i}.pdf",
            ]);
            $filler->embedding = json_encode([-1.0, -1.0]);
            $filler->save();
        }

        $this->mock(OllamaSSHClient::class, function ($mock) use ($closeButRejected, $farAndRejected) {
            $mock->shouldReceive('embed')->andReturn([1.0, 0.0]);
            // Both are rejected with the same reason text - the point of this
            // test is that their DIFFERENT raw embedding scores still survive
            // the same *0.4 scaling, so both get an identical AI verdict here
            // rather than needing to tell the calls apart.
            $mock->shouldReceive('generate')
                ->andReturn(json_encode(['matches' => false, 'reason' => 'Different domain and target users.']));
        });

        $concepts = [
            'title' => 'Capstone Management System', 'main_topic' => 'Capstone tracking', 'domain' => 'Management System',
            'keywords' => ['capstone'], 'problem_statement' => 'p', 'proposed_solution' => 's', 'scope' => 'sc',
        ];

        $matches = app(SimilarityService::class)->findSimilarDocuments($concepts, 10);
        $closeMatch = collect($matches)->firstWhere(fn ($m) => $m['document']->archived_id === $closeButRejected->archived_id);
        $farMatch = collect($matches)->firstWhere(fn ($m) => $m['document']->archived_id === $farAndRejected->archived_id);

        $this->assertNotNull($closeMatch);
        $this->assertNotNull($farMatch);
        $this->assertGreaterThan(
            $farMatch['score'],
            $closeMatch['score'],
            'A rejected match that is still lexically close should score higher than one that shares nothing but generic wording, not be flattened to the same number.'
        );
    }

    public function test_a_genuinely_relevant_high_scoring_match_keeps_its_full_score(): void
    {
        $team = Team::create(['team_name' => 'Archive Source Team 2']);

        $relevant = ArchivedDocument::create([
            'team_id' => $team->team_id,
            'title' => 'Capstone Project Management System',
            'abstract' => 'A system to track capstone project progress.',
            'academic_year' => 2025,
            'program' => 'BSIT',
            'file_path' => 'archives/relevant.pdf',
        ]);
        $relevant->embedding = json_encode([1.0, 0.0]);
        $relevant->save();

        for ($i = 0; $i < 4; $i++) {
            $filler = ArchivedDocument::create([
                'team_id' => $team->team_id,
                'title' => "Unrelated Filler Document {$i}",
                'abstract' => 'Filler.',
                'academic_year' => 2024,
                'program' => 'BSIT',
                'file_path' => "archives/filler{$i}.pdf",
            ]);
            $filler->embedding = json_encode([-1.0, -1.0]);
            $filler->save();
        }

        $this->mock(OllamaSSHClient::class, function ($mock) use ($relevant) {
            $mock->shouldReceive('embed')->andReturn([1.0, 1.0]);
            $mock->shouldReceive('generate')
                ->withArgs(fn ($prompt) => str_contains($prompt, $relevant->title))
                ->andReturn(json_encode(['matches' => true, 'reason' => 'Both track capstone projects end-to-end.']));
        });

        $concepts = [
            'title' => 'Capstone Management System', 'main_topic' => 'Capstone tracking', 'domain' => 'Management System',
            'keywords' => ['capstone'], 'problem_statement' => 'p', 'proposed_solution' => 's', 'scope' => 'sc',
        ];

        $matches = app(SimilarityService::class)->findSimilarDocuments($concepts, 10);
        $match = collect($matches)->firstWhere(fn ($m) => $m['document']->archived_id === $relevant->archived_id);

        $this->assertNotNull($match);
        $this->assertTrue($match['is_relevant']);
        $this->assertGreaterThanOrEqual(65, $match['score']);
    }

    // When the AI provides its own 0-100 score, that score is used directly
    // instead of the raw embedding math - this is what actually lets a
    // genuine match read with real confidence instead of just an embedding's
    // word-overlap number.
    public function test_the_ais_own_score_is_used_directly_when_it_confirms_a_match(): void
    {
        $team = Team::create(['team_name' => 'Archive Source Team 4']);

        $doc = ArchivedDocument::create([
            'team_id' => $team->team_id,
            'title' => 'Some Archived Capstone',
            'abstract' => 'An archived capstone.',
            'academic_year' => 2024,
            'program' => 'BSIT',
            'file_path' => 'archives/scored.pdf',
        ]);
        $doc->embedding = json_encode([1.0, 0.0]);
        $doc->save();

        for ($i = 0; $i < 4; $i++) {
            $filler = ArchivedDocument::create([
                'team_id' => $team->team_id,
                'title' => "Unrelated Filler Document {$i}",
                'abstract' => 'Filler.',
                'academic_year' => 2024,
                'program' => 'BSIT',
                'file_path' => "archives/filler4-{$i}.pdf",
            ]);
            $filler->embedding = json_encode([-1.0, -1.0]);
            $filler->save();
        }

        $this->mock(OllamaSSHClient::class, function ($mock) use ($doc) {
            $mock->shouldReceive('embed')->andReturn([1.0, 1.0]);
            $mock->shouldReceive('generate')
                ->withArgs(fn ($prompt) => str_contains($prompt, $doc->title))
                ->andReturn(json_encode(['matches' => true, 'score' => 78, 'reason' => 'Both manage the same underlying process for the same users.']));
        });

        $concepts = [
            'title' => 'Capstone Management System', 'main_topic' => 'Capstone tracking', 'domain' => 'Management System',
            'keywords' => ['capstone'], 'problem_statement' => 'p', 'proposed_solution' => 's', 'scope' => 'sc',
        ];

        $matches = app(SimilarityService::class)->findSimilarDocuments($concepts, 10);
        $match = collect($matches)->firstWhere(fn ($m) => $m['document']->archived_id === $doc->archived_id);

        $this->assertNotNull($match);
        $this->assertSame(78, $match['score']);
    }

    // Regression: observed directly against the real archive - the model can
    // write a perfectly correct rejection reason ("different domains: one
    // manages capstone projects, the other manages inventory and sales")
    // while attaching a high, internally CONTRADICTORY score (86) to that
    // same JSON entry - self-contradictory specifically because a HIGH score
    // doesn't square with "different". A rejection's own score is only
    // distrusted in that contradictory case (see the next test for the
    // normal case, where a low/moderate score on a rejection is trusted).
    public function test_a_rejected_matchs_own_score_is_never_trusted_even_if_high(): void
    {
        $team = Team::create(['team_name' => 'Archive Source Team 6']);

        $doc = ArchivedDocument::create([
            'team_id' => $team->team_id,
            'title' => 'Web-Based Management System for Production and Auxiliary Services',
            'abstract' => 'Inventory and sales tracking for a school auxiliary office.',
            'academic_year' => 2025,
            'program' => 'BSIT',
            'file_path' => 'archives/contradictory.pdf',
        ]);
        $doc->embedding = json_encode([1.0, 0.0]);
        $doc->save();

        for ($i = 0; $i < 4; $i++) {
            $filler = ArchivedDocument::create([
                'team_id' => $team->team_id,
                'title' => "Unrelated Filler Document {$i}",
                'abstract' => 'Filler.',
                'academic_year' => 2024,
                'program' => 'BSIT',
                'file_path' => "archives/filler6-{$i}.pdf",
            ]);
            $filler->embedding = json_encode([-1.0, -1.0]);
            $filler->save();
        }

        // matches:false paired with a self-contradictory high score of 86 -
        // exactly the real response observed from the model.
        $this->mock(OllamaSSHClient::class, function ($mock) use ($doc) {
            $mock->shouldReceive('embed')->andReturn([1.0, 1.0]);
            $mock->shouldReceive('generate')
                ->withArgs(fn ($prompt) => str_contains($prompt, $doc->title))
                ->andReturn(json_encode(['matches' => false, 'score' => 86, 'reason' => 'Different domains: one manages capstone projects, the other manages inventory and sales.']));
        });

        $concepts = [
            'title' => 'Capstone Management System', 'main_topic' => 'Capstone tracking', 'domain' => 'Management System',
            'keywords' => ['capstone'], 'problem_statement' => 'p', 'proposed_solution' => 's', 'scope' => 'sc',
        ];

        $matches = app(SimilarityService::class)->findSimilarDocuments($concepts, 10);
        $match = collect($matches)->firstWhere(fn ($m) => $m['document']->archived_id === $doc->archived_id);

        $this->assertNotNull($match);
        $this->assertFalse($match['is_relevant']);
        $this->assertLessThan(55, $match['score'], 'A rejected match must never display its own self-contradictory high score.');
    }

    // Regression: observed directly against the real archive - a genuine
    // near-duplicate ("VenueTrack" vs. the real archived "SPHERE" facility/
    // equipment reservation system, reworded but describing the same
    // features) scored 90% on raw embedding similarity, correctly reflecting
    // a real match - but the AI called it matches:false and its own score of
    // 30 got thrown out entirely by the OLD logic, flattening the display to
    // a scaled-down 36% indistinguishable from genuinely unrelated
    // candidates. A LOW/moderate score on a rejection isn't self-
    // contradictory (unlike the high-score case above) - it's the model
    // expressing a real "related but not identical" middle ground, and
    // should be trusted rather than discarded just because "matches" is
    // false.
    public function test_a_rejected_matchs_own_low_or_moderate_score_is_trusted(): void
    {
        $team = Team::create(['team_name' => 'Archive Source Team 7']);

        $doc = ArchivedDocument::create([
            'team_id' => $team->team_id,
            'title' => 'Facility and Equipment Rental and Event Management System',
            'abstract' => 'Automates booking and billing for campus venues and equipment.',
            'academic_year' => 2024,
            'program' => 'BSIT',
            'file_path' => 'archives/venue.pdf',
        ]);
        $doc->embedding = json_encode([1.0, 0.0]);
        $doc->save();

        for ($i = 0; $i < 4; $i++) {
            $filler = ArchivedDocument::create([
                'team_id' => $team->team_id,
                'title' => "Unrelated Filler Document {$i}",
                'abstract' => 'Filler.',
                'academic_year' => 2024,
                'program' => 'BSIT',
                'file_path' => "archives/filler7-{$i}.pdf",
            ]);
            $filler->embedding = json_encode([-1.0, -1.0]);
            $filler->save();
        }

        // matches:false with a moderate, non-contradictory score of 45 -
        // should be trusted and shown as-is, not scaled down to near-zero.
        $this->mock(OllamaSSHClient::class, function ($mock) use ($doc) {
            $mock->shouldReceive('embed')->andReturn([1.0, 1.0]);
            $mock->shouldReceive('generate')
                ->withArgs(fn ($prompt) => str_contains($prompt, $doc->title))
                ->andReturn(json_encode(['matches' => false, 'score' => 45, 'reason' => 'Related but for a different specific office.']));
        });

        $concepts = [
            'title' => 'Capstone Management System', 'main_topic' => 'Capstone tracking', 'domain' => 'Management System',
            'keywords' => ['capstone'], 'problem_statement' => 'p', 'proposed_solution' => 's', 'scope' => 'sc',
        ];

        $matches = app(SimilarityService::class)->findSimilarDocuments($concepts, 10);
        $match = collect($matches)->firstWhere(fn ($m) => $m['document']->archived_id === $doc->archived_id);

        $this->assertNotNull($match);
        $this->assertFalse($match['is_relevant']);
        $this->assertSame(45, $match['score'], 'A rejection\'s own moderate score should be trusted, not discarded in favor of scaled embedding math.');
    }

    // Regression: a candidate whose own AI call comes back unusable (bad
    // JSON, a timeout, anything judgeOneCandidate() can't parse - not an
    // explicit rejection, just no usable verdict at all) was left showing
    // its full raw embedding score, unscaled - reading as the strongest,
    // most concerning match on the page despite having zero actual AI
    // review. There's no positive confirmation it's a real match, so it
    // must be treated the same cautious way as an explicit rejection.
    public function test_a_candidate_the_ai_never_mentions_at_all_has_its_score_scaled_down(): void
    {
        $team = Team::create(['team_name' => 'Archive Source Team 5']);

        // Deliberately NOT one of the prompt's own hardcoded worked-example
        // titles (e.g. "Capstone Project and Thesis Class Information
        // Management System" appears verbatim in enrichWithReasons()'s
        // guidance text, present in every prompt regardless of document) -
        // reusing one here would make str_contains() below match on both
        // documents' calls instead of distinguishing them.
        $mentioned = ArchivedDocument::create([
            'team_id' => $team->team_id,
            'title' => 'CapAdvise: Capstone Adviser Scheduling and Consultation Portal',
            'abstract' => 'Manages capstone class information.',
            'academic_year' => 2022,
            'program' => 'BSIT',
            'file_path' => 'archives/mentioned.pdf',
        ]);
        $mentioned->embedding = json_encode([1.0, 0.0]);
        $mentioned->save();

        // Its own AI call comes back unusable below, despite scoring high
        // on raw embedding similarity.
        $omitted = ArchivedDocument::create([
            'team_id' => $team->team_id,
            'title' => 'Development of Web-Based Enrollment System with Face Recognition',
            'abstract' => 'An enrollment system for a vocational school.',
            'academic_year' => 2025,
            'program' => 'BSIT',
            'file_path' => 'archives/omitted.pdf',
        ]);
        $omitted->embedding = json_encode([1.0, 0.1]);
        $omitted->save();

        for ($i = 0; $i < 3; $i++) {
            $filler = ArchivedDocument::create([
                'team_id' => $team->team_id,
                'title' => "Unrelated Filler Document {$i}",
                'abstract' => 'Filler.',
                'academic_year' => 2024,
                'program' => 'BSIT',
                'file_path' => "archives/filler5-{$i}.pdf",
            ]);
            $filler->embedding = json_encode([-1.0, -1.0]);
            $filler->save();
        }

        $this->mock(OllamaSSHClient::class, function ($mock) use ($mentioned, $omitted) {
            $mock->shouldReceive('embed')->andReturn([1.0, 0.0]);
            // $mentioned gets a normal, valid verdict; $omitted's own call
            // comes back unusable, simulating a single candidate's judgment
            // failing outright (bad response, timeout, etc.) rather than an
            // explicit rejection.
            $mock->shouldReceive('generate')
                ->withArgs(fn ($prompt) => str_contains($prompt, $mentioned->title))
                ->andReturn(json_encode(['matches' => true, 'score' => 90, 'reason' => 'Both manage capstone projects.']));
            $mock->shouldReceive('generate')
                ->withArgs(fn ($prompt) => str_contains($prompt, $omitted->title))
                ->andReturn('not valid json');
        });

        $concepts = [
            'title' => 'Capstone Management System', 'main_topic' => 'Capstone tracking', 'domain' => 'Management System',
            'keywords' => ['capstone'], 'problem_statement' => 'p', 'proposed_solution' => 's', 'scope' => 'sc',
        ];

        $matches = app(SimilarityService::class)->findSimilarDocuments($concepts, 10);
        $omittedMatch = collect($matches)->firstWhere(fn ($m) => $m['document']->archived_id === $omitted->archived_id);

        $this->assertNotNull($omittedMatch, 'Expected the omitted document to still appear in the candidate list.');
        $this->assertLessThan(55, $omittedMatch['score'], 'A candidate the AI never reviewed must not display its raw, unvetted embedding score.');
    }
}
