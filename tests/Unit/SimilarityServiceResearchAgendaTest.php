<?php

namespace Tests\Unit;

use App\Services\OllamaSSHClient;
use App\Services\SimilarityService;
use Tests\TestCase;

class SimilarityServiceResearchAgendaTest extends TestCase
{
    // Regression: the local model sometimes returns research_agenda entries
    // as nested objects (e.g. {"agenda": "..."}) instead of the plain
    // strings the prompt asks for. Left unnormalized, that shape flowed
    // straight through to the browser, got frozen into a hidden input's
    // string value as the literal text "[object Object]", and got saved to
    // the database that way - permanently losing the real content.
    public function test_research_agenda_objects_are_normalized_to_plain_strings(): void
    {
        $this->mock(OllamaSSHClient::class, function ($mock) {
            $mock->shouldReceive('generate')->andReturn(json_encode([
                'title' => 'A Title',
                'main_topic' => 'Topic',
                'domain' => 'Web System',
                'keywords' => ['a'],
                'problem_statement' => 'p',
                'proposed_solution' => 's',
                'scope' => 'sc',
                'limitations' => 'l',
                'sdg_goals' => 'SDG 4 – Quality Education',
                'research_agenda' => [
                    ['agenda' => 'Applications and Systems Development'],
                    ['name' => 'e-Services'],
                    'Educational Management',
                    ['unrelated_key' => 123],
                ],
                'sdm' => 'Agile',
                'technologies' => ['Laravel'],
                'target_users' => 'Students',
            ]));
        });

        $concepts = app(SimilarityService::class)->extractKeyConcepts('some pdf text');

        $this->assertSame([
            'Applications and Systems Development',
            'e-Services',
            'Educational Management',
        ], $concepts['research_agenda']);

        foreach ($concepts['research_agenda'] as $item) {
            $this->assertIsString($item);
            $this->assertNotSame('[object Object]', $item);
        }
    }

    public function test_research_agenda_left_alone_when_already_plain_strings(): void
    {
        $this->mock(OllamaSSHClient::class, function ($mock) {
            $mock->shouldReceive('generate')->andReturn(json_encode([
                'title' => 'A Title', 'main_topic' => 'Topic', 'domain' => 'Web System', 'keywords' => [],
                'problem_statement' => 'p', 'proposed_solution' => 's', 'scope' => 'sc', 'limitations' => 'l',
                'sdg_goals' => 'SDG 4', 'research_agenda' => ['Applications and Systems Development', 'e-Services'],
                'sdm' => 'Agile', 'technologies' => [], 'target_users' => 'Students',
            ]));
        });

        $concepts = app(SimilarityService::class)->extractKeyConcepts('some pdf text');

        $this->assertSame(['Applications and Systems Development', 'e-Services'], $concepts['research_agenda']);
    }

    public function test_one_entry_holding_several_bulleted_items_is_split_apart(): void
    {
        $this->mock(OllamaSSHClient::class, function ($mock) {
            $mock->shouldReceive('generate')->andReturn(json_encode([
                'title' => 'A Title', 'main_topic' => 'Topic', 'domain' => 'Web System', 'keywords' => ['a'],
                'problem_statement' => 'p', 'proposed_solution' => 's', 'scope' => 'sc', 'limitations' => 'l',
                'sdg_goals' => 'SDG 4', 'sdm' => 'Agile', 'technologies' => [], 'target_users' => 'Students',
                'research_agenda' => ["• Applications and Systems Development • e-Services\n● Data Science"],
            ]));
        });

        $concepts = app(SimilarityService::class)->extractKeyConcepts('some pdf text');

        $this->assertSame(['Applications and Systems Development', 'e-Services', 'Data Science'], $concepts['research_agenda']);
    }
}
