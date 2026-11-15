<?php

namespace Tests\Feature;

use App\Jobs\EmbedArchive;
use App\Models\ArchivedDocument;
use App\Services\OllamaSSHClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Mockery;
use Tests\Concerns\BuildsChecklists;
use Tests\TestCase;

/**
 * An archived capstone gets its embedding by itself - when it is published,
 * added or reworded - and "Update embeddings" fills in any that were missed.
 * An entry still waiting for the CaPSA/TSA never gets one.
 */
class ArchiveEmbeddingTest extends TestCase
{
    use BuildsChecklists;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs($this->capsaFor(), 'expert');
    }

    private function archive(array $overrides = []): ArchivedDocument
    {
        return ArchivedDocument::create($overrides + ['title' => 'Barangay Health Records', 'abstract' => 'Tracks patients.', 'academic_year' => '2026-2027', 'program' => 'BSIT', 'authors' => 'A. Cruz']);
    }

    private function embed(ArchivedDocument $archive, ?array $answer = [0.1, 0.2]): void
    {
        $ollama = Mockery::mock(OllamaSSHClient::class);
        $answer === null
            ? $ollama->shouldReceive('embed')->andThrow(new \Exception('server down'))
            : $ollama->shouldReceive('embed')->andReturn($answer);

        (new EmbedArchive($archive->archived_id))->handle($ollama);
    }

    private function sent(ArchivedDocument $archive): void
    {
        Bus::assertDispatched(EmbedArchive::class, fn ($job) => $job->archiveId === $archive->archived_id);
    }

    public function test_publishing_and_adding_each_ask_for_an_embedding(): void
    {
        $pending = $this->archive(['submission_status' => ArchivedDocument::PENDING]);
        $this->patchJson(route('api.admin.archive.submission.update', $pending), ['decision' => 'published'])->assertOk();
        $this->sent($pending);

        $id = $this->postJson(route('api.admin.archive.store'), ['title' => 'Added by Staff', 'academic_year' => '2026-2027', 'program' => 'BSIT', 'authors' => 'A. Cruz'])->assertCreated()->json('archived_id');
        $this->sent(ArchivedDocument::findOrFail($id));
    }

    public function test_rewording_clears_the_old_embedding_and_asks_for_a_new_one(): void
    {
        $archive = $this->archive();
        $archive->forceFill(['embedding' => '[9]'])->save();

        $this->putJson(route('api.admin.archive.update', $archive), ['title' => $archive->title, 'abstract' => $archive->abstract, 'authors' => 'B. Reyes'])->assertOk();
        $this->assertSame('[9]', $archive->fresh()->embedding);
        Bus::assertNotDispatched(EmbedArchive::class);

        $this->putJson(route('api.admin.archive.update', $archive), ['title' => 'A New Title'])->assertOk();
        $this->assertNull($archive->fresh()->embedding);
        $this->sent($archive);
    }

    public function test_a_published_capstone_is_embedded_and_a_waiting_one_is_not(): void
    {
        $published = $this->archive();
        $pending = $this->archive(['submission_status' => ArchivedDocument::PENDING]);

        $this->embed($published);
        $this->embed($pending);

        $this->assertSame('[0.1,0.2]', $published->fresh()->embedding);
        $this->assertNull($pending->fresh()->embedding);
    }

    public function test_a_dead_ai_server_leaves_the_capstone_without_one_and_does_not_fail(): void
    {
        $archive = $this->archive();

        $this->embed($archive, null);

        $this->assertNull($archive->fresh()->embedding);
    }

    public function test_the_button_asks_only_for_the_published_ones_still_missing(): void
    {
        $missing = $this->archive();
        $done = $this->archive();
        $done->forceFill(['embedding' => '[1]'])->save();
        $this->archive(['submission_status' => ArchivedDocument::PENDING]);

        $this->postJson(route('api.admin.archive.embeddings'))->assertOk()->assertJson(['count' => 1]);

        Bus::assertDispatchedTimes(EmbedArchive::class, 1);
        $this->sent($missing);
        $this->get(route('capsa.archive'))->assertOk()->assertSee('Update embeddings (1)');
    }
}
