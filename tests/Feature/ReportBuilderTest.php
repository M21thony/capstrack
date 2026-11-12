<?php

namespace Tests\Feature;

use App\Models\ReportTable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsChecklists;
use Tests\TestCase;

/** Report builder: a CaPSA/TSA saves, opens, renames and deletes only her own tables. */
class ReportBuilderTest extends TestCase
{
    use BuildsChecklists;
    use RefreshDatabase;

    private function payload(array $over = []): array
    {
        return $over + ['name' => 'Fees list', 'mode' => 'students', 'columns' => [
            ['key' => 'who', 'label' => 'Student', 'sources' => ['id', 'student'], 'separator' => ' · '],
            ['key' => 'team', 'label' => 'Team', 'sources' => ['team', 'title'], 'separator' => "\n"],
        ]];
    }

    public function test_a_table_is_saved_opened_renamed_and_deleted(): void
    {
        $capsa = $this->capsaFor(['BSIT-3A']);
        $this->actingAs($capsa, 'expert');

        $id = $this->postJson(route('capsa.reports.tables.store'), $this->payload())->assertCreated()->json('table.id');
        $table = ReportTable::find($id);
        $this->assertSame([' · ', "\n"], array_column($table->columns, 'separator'));

        $this->get(route('capsa.reports.builder', $id))->assertOk()->assertSee('Fees list');
        $this->get(route('capsa.reports'))->assertOk()->assertSee('Fees list');

        $this->putJson(route('capsa.reports.tables.update', $id), ['name' => 'Receipts'])->assertOk()->assertJsonPath('table.name', 'Receipts');
        $this->assertSame("\n", ReportTable::find($id)->columns[1]['separator']);

        $this->deleteJson(route('capsa.reports.tables.destroy', $id))->assertOk()->assertJsonPath('url', route('capsa.reports'));
        $this->assertNull(ReportTable::find($id));
    }

    public function test_names_are_unique_per_capsa_ignoring_case(): void
    {
        $this->actingAs($this->capsaFor(), 'expert');
        $this->postJson(route('capsa.reports.tables.store'), $this->payload())->assertCreated();

        $this->postJson(route('capsa.reports.tables.store'), $this->payload(['name' => ' fees LIST ']))
            ->assertUnprocessable()->assertJsonValidationErrors('name');
    }

    public function test_another_capsas_table_is_not_found(): void
    {
        $this->actingAs($this->capsaFor(), 'expert');
        $id = $this->postJson(route('capsa.reports.tables.store'), $this->payload())->json('table.id');

        $this->actingAs($this->capsaFor(['BSIT-4A']), 'expert');
        $this->get(route('capsa.reports.builder', $id))->assertNotFound();
        $this->putJson(route('capsa.reports.tables.update', $id), ['name' => 'Mine'])->assertNotFound();
        $this->deleteJson(route('capsa.reports.tables.destroy', $id))->assertNotFound();
        $this->assertSame('Fees list', ReportTable::find($id)->name);
    }

    public function test_data_holds_only_her_sections_rows(): void
    {
        $capsa = $this->capsaFor(['BSIT-3A']);
        $this->teamInSection('Alpha');
        $this->teamInSection('Outsider', yearSection: '4th · A');

        $json = $this->actingAs($capsa, 'expert')->getJson(route('capsa.reports.data'))
            ->assertOk()->assertJsonStructure(['rows' => ['*' => ['titlegrade', 'titlescores', 'titleverdict', 'prescores', 'finalverdict', 'orno', 'paidon', 'pregrade', 'finalgrade', 'avggrade', 'chair', 'paid']], 'expertRows', 'options' => ['year', 'term', 'program', 'section'], 'filters'])
            ->getContent();

        $this->assertStringContainsString('Alpha', $json);
        $this->assertStringNotContainsString('Outsider', $json);
    }

    public function test_a_built_report_downloads_as_excel_and_pdf_under_the_letterhead(): void
    {
        $this->actingAs($this->capsaFor(), 'expert');
        $send = ['name' => 'Fee collection', 'scope' => 'BSIT', 'headings' => ['Student', 'OR number'], 'rows' => [['Cruz, Ana', '0012345'], ['Reyes, Ben', '=1+1']]];

        $xlsx = $this->postJson(route('capsa.reports.tables.export'), $send + ['format' => 'xlsx'])->assertOk();
        $this->assertStringContainsString('fee-collection_', $xlsx->headers->get('content-disposition'));
        $path = tempnam(sys_get_temp_dir(), 'rb');
        file_put_contents($path, $xlsx->streamedContent());
        $sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path)->getActiveSheet();
        unlink($path);

        $this->assertCount(2, $sheet->getDrawingCollection());
        $this->assertSame('COLLEGE of COMPUTER STUDIES', $sheet->getCell('B5')->getValue());
        $this->assertSame('Fee collection', $sheet->getCell('A7')->getValue());
        $this->assertSame(['Student', 'OR number'], [$sheet->getCell('A10')->getValue(), $sheet->getCell('B10')->getValue()]);
        // Kept as typed: an OR number keeps its leading zeros, and a formula stays text.
        $this->assertSame('0012345', $sheet->getCell('B11')->getValue());
        $this->assertSame('=1+1', $sheet->getCell('B12')->getValue());
        $this->assertSame('s', $sheet->getCell('B12')->getDataType());

        $pdf = $this->postJson(route('capsa.reports.tables.export'), $send + ['format' => 'pdf'])->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        $this->postJson(route('capsa.reports.tables.export'), $send + ['format' => 'csv'])->assertUnprocessable();
    }
}
