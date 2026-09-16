<?php

namespace Tests\Feature;

use App\Services\SimilarityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Why two studies were called similar has to be checkable.
 *
 * The report highlighted the pre-proposal's own words wherever they appeared
 * in a matched abstract - but "system", "management" and "development" are
 * true of nearly every capstone, so what lit up was the vocabulary of the
 * course rather than anything about this topic.
 */
class SimilarityReportExplainsItselfTest extends TestCase
{
    use RefreshDatabase;

    private function words(array $concepts): array
    {
        return app(SimilarityService::class)->extractHighlightWords($concepts);
    }

    public function test_words_every_capstone_has_are_not_the_explanation(): void
    {
        $words = $this->words([
            'title' => 'Development of a Web-Based Sangguniang Kabataan Management System',
            'main_topic' => 'youth council record keeping',
            'keywords' => ['barangay records', 'management system'],
        ]);

        foreach (['system', 'management', 'development', 'management system'] as $generic) {
            $this->assertNotContains($generic, $words, "'{$generic}' says nothing about this topic");
        }
    }

    public function test_what_makes_this_study_itself_survives(): void
    {
        $words = $this->words([
            'title' => 'Development of a Web-Based Sangguniang Kabataan Management System',
            'main_topic' => 'youth council record keeping',
            'keywords' => ['barangay records'],
        ]);

        foreach (['sangguniang', 'kabataan', 'barangay records'] as $distinctive) {
            $this->assertContains($distinctive, $words);
        }
    }

    /**
     * The embedding must not shift under the stored vectors.
     *
     * Every archived embedding was generated with the older stop-word list,
     * so widening that list would make old vectors and new queries disagree
     * about the same document. The generic words are dropped from the
     * highlighting only.
     */
    public function test_the_embedding_stop_words_are_left_alone(): void
    {
        $service = app(SimilarityService::class);

        $method = new \ReflectionMethod($service, 'removeStopWords');
        $method->setAccessible(true);

        $this->assertStringContainsString(
            'system',
            $method->invoke($service, 'a management system for barangay records')
        );
    }
}
