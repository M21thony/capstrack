<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\ExpertExpertise;
use App\Models\Role;
use App\Support\CapsaReports;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\Concerns\BuildsChecklists;
use Tests\TestCase;

/**
 * Expert Management as a report: View Report opens the table with the column
 * picker, Download Excel gives only the columns that were ticked.
 */
class ExpertListReportTest extends TestCase
{
    use BuildsChecklists;
    use RefreshDatabase;

    private function expert(string $first, string $last, array $roles = ['adviser'], array $expertise = [], array $attributes = []): CapstoneExpert
    {
        $expert = CapstoneExpert::factory()->create($attributes + [
            'approved_status' => true, 'is_admin' => false, 'first_name' => $first, 'last_name' => $last, 'max_teams' => 4,
        ]);

        foreach ($roles as $role) {
            $expert->roles()->attach(Role::firstOrCreate(['role_name' => $role])->role_id);
        }
        foreach ($expertise as $field) {
            ExpertExpertise::create(['expert_id' => $expert->expert_id, 'expertise_field' => $field]);
        }

        return $expert;
    }

    /** @return array<int, array<int, mixed>> the sheet's rows */
    private function sheet(array $query): array
    {
        $response = $this->get(route('capsa.reports.show', ['report' => 'expert_list', 'download' => 'xlsx'] + $query))->assertOk();

        $path = tempnam(sys_get_temp_dir(), 'exl');
        file_put_contents($path, $response->streamedContent());
        $rows = IOFactory::load($path)->getActiveSheet()->toArray();
        unlink($path);

        return $rows;
    }

    /**
     * The sheet row for one expert. The CaPSA viewing the report is an expert
     * too, with a random factory name, so the list's alphabetical order can
     * put them first; finding the row by name keeps the test off that luck.
     *
     * @param  array<int, array<int, mixed>>  $rows
     * @return array<int, mixed>
     */
    private function rowOf(array $rows, string $name): array
    {
        return collect($rows)->slice(4)->first(fn (array $row) => $row[0] === $name) ?? [];
    }

    public function test_the_expert_management_page_offers_view_report_and_download_excel(): void
    {
        $capsa = $this->capsaFor();

        $this->actingAs($capsa, 'expert')->get(route('capsa.experts'))
            ->assertOk()
            ->assertSee('href="'.route('capsa.reports.show', 'expert_list').'"', false)
            ->assertSee('View Report')
            ->assertSee('Download Excel')
            ->assertSee('id="expertExportModal"', false)
            ->assertSee('value="accepts"', false);
    }

    public function test_the_report_lists_the_experts_with_the_columns_of_the_page(): void
    {
        $capsa = $this->capsaFor();
        $this->expert('Ana', 'Cruz', ['adviser', 'consultant'], ['Machine Learning', 'Databases']);

        $this->actingAs($capsa, 'expert')->get(route('capsa.reports.show', 'expert_list'))
            ->assertOk()
            ->assertSee('Expert List')
            ->assertSee('Ana Cruz')
            ->assertSee('Adviser, Consultant')
            ->assertSee('Machine Learning, Databases')
            ->assertSee('Slots');
    }

    public function test_the_excel_file_has_only_the_ticked_columns_in_order(): void
    {
        $capsa = $this->capsaFor();
        $ana = $this->expert('Ana', 'Cruz', ['adviser'], ['Machine Learning']);
        $this->actingAs($capsa, 'expert');

        $hide = collect(array_keys(CapsaReports::EXPERT_LIST_COLUMNS))->diff(['expert', 'roles', 'email'])->implode(',');
        $rows = $this->sheet(['hide' => $hide]);

        $this->assertSame('Expert List', $rows[0][0]);
        $this->assertSame(['Name', 'Email', 'Role(s)'], array_slice($rows[3], 0, 3));
        $this->assertNull($rows[3][3] ?? null);
        $this->assertSame([$ana->name, $ana->email, 'Adviser'], array_slice($this->rowOf($rows, $ana->name), 0, 3));
    }

    public function test_name_accept_slots_and_expertise_only(): void
    {
        $capsa = $this->capsaFor();
        $ana = $this->expert('Ana', 'Cruz', ['adviser'], ['Databases']);
        $ana->update(['max_teams' => 5]);
        $this->actingAs($capsa, 'expert');

        $hide = collect(array_keys(CapsaReports::EXPERT_LIST_COLUMNS))->diff(['expert', 'accepts', 'expertise'])->implode(',');
        $rows = $this->sheet(['hide' => $hide, 'order' => 'expert,accepts,expertise']);

        $this->assertSame(['Name', 'Slots', 'Expertise'], array_slice($rows[3], 0, 3));
        $this->assertSame([$ana->name, '0 of 5 · any program', 'Databases'], array_slice($this->rowOf($rows, $ana->name), 0, 3));
    }

    public function test_the_name_column_can_never_be_left_out(): void
    {
        $capsa = $this->capsaFor();
        $this->expert('Ana', 'Cruz');
        $this->actingAs($capsa, 'expert');

        $rows = $this->sheet(['hide' => implode(',', array_keys(CapsaReports::EXPERT_LIST_COLUMNS))]);

        $this->assertSame('Name', $rows[3][0]);
        $this->assertNull($rows[3][1] ?? null);
    }

    public function test_deactivated_experts_are_listed_and_marked_but_the_admin_and_unapproved_are_not(): void
    {
        $capsa = $this->capsaFor();
        $this->expert('Off', 'Line', attributes: ['deactivated_at' => now()]);
        $this->expert('Not', 'Approved', attributes: ['approved_status' => false]);
        $this->expert('Super', 'Admin', attributes: ['is_admin' => true]);
        $this->actingAs($capsa, 'expert');

        $names = collect($this->sheet(['hide' => 'email,roles,expertise,accepts,capacity,program,sections,degree']))
            ->slice(4)->pluck(0)->all();

        $this->assertContains('Off Line', $names);
        $this->assertNotContains('Not Approved', $names);
        $this->assertNotContains('Super Admin', $names);
    }

    public function test_the_pdf_download_works_too(): void
    {
        ini_set('memory_limit', '512M');
        $capsa = $this->capsaFor();
        $this->expert('Ana', 'Cruz');

        $pdf = $this->actingAs($capsa, 'expert')
            ->get(route('capsa.reports.show', ['report' => 'expert_list', 'download' => 'pdf']))->assertOk();

        $this->assertStringStartsWith('%PDF', $pdf->getContent());
    }

    public function test_the_reports_index_lists_it(): void
    {
        $this->actingAs($this->capsaFor(), 'expert')->get(route('capsa.reports'))
            ->assertOk()
            ->assertSee('Expert List');
    }
}
