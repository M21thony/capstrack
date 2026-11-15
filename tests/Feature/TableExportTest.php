<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/**
 * View Report and Download Excel on the tables of the CaPSA/TSA, Expert and Admin
 * pages: the page sends the table it is showing, the server lays it out.
 */
class TableExportTest extends TestCase
{
    use RefreshDatabase;

    private function everyHat(): CapstoneExpert
    {
        $expert = CapstoneExpert::factory()->create([
            'approved_status' => true, 'is_admin' => true, 'sections' => ['BSIT-3A'],
        ]);

        foreach ([CapstoneExpert::CAPSA_ROLE, 'adviser'] as $role) {
            $expert->roles()->attach(Role::firstOrCreate(['role_name' => $role])->role_id);
        }

        return $expert;
    }

    /** @return array<int, array<int, mixed>> */
    private function sheetOf($response): array
    {
        $path = tempnam(sys_get_temp_dir(), 'tbl');
        file_put_contents($path, $response->streamedContent());
        $rows = IOFactory::load($path)->getActiveSheet()->toArray();
        unlink($path);

        // Every export opens with the letterhead; the title, scope and table follow from row 7.
        $this->assertSame('COLLEGE of COMPUTER STUDIES', $rows[4][1]);
        $trim = function (array $row) {
            while ($row && end($row) === null) {
                array_pop($row);
            }

            return $row;
        };

        return array_map($trim, array_slice($rows, 6));
    }

    public function test_the_tables_on_the_capsa_and_admin_pages_carry_the_buttons(): void
    {
        $expert = $this->everyHat();

        $pages = [
            'capsa.activitylog' => ['Activity Log'],
            // Progress is a column of the Teams table now, not a second table.
            'capsa.team_management' => ['Teams'],
            'capsa.financial' => ['Receipts'],
            'admin.students' => ['Student Accounts'],
        ];

        foreach ($pages as $route => $titles) {
            $html = $this->actingAs($expert, 'expert')->get(route($route))->assertOk()
                ->assertSee('id="tx-dialog"', false)
                ->getContent();

            foreach ($titles as $title) {
                $this->assertStringContainsString('data-export="'.$title.'"', $html, $route.' should offer '.$title);
            }
        }
    }

    public function test_the_expert_pages_carry_the_buttons_too(): void
    {
        $adviser = CapstoneExpert::factory()->create(['approved_status' => true, 'is_admin' => false]);
        $adviser->roles()->attach(Role::firstOrCreate(['role_name' => 'adviser'])->role_id);

        foreach (['instructor.grading', 'instructor.teams'] as $route) {
            $this->actingAs($adviser, 'expert')->get(route($route))->assertOk()
                ->assertSee('id="tx-dialog"', false)
                ->assertSee('table-export', false);
        }

        // Papers are not something anyone reports on: Document Review has none.
        $this->actingAs($adviser, 'expert')->get(route('instructor.docreview'))->assertOk()
            ->assertDontSee('data-export=', false);
    }

    public function test_the_excel_file_has_the_title_the_chosen_columns_and_the_rows(): void
    {
        $expert = $this->everyHat();

        $response = $this->actingAs($expert, 'expert')->postJson(route('table.export'), [
            'title' => 'Activity Log',
            'scope' => '2 rows matching "ana"',
            'headings' => ['Student', 'Action'],
            'rows' => [['Ana Cruz', 'Uploaded'], ['Ana Reyes', '=SUM(1+1)']],
        ])->assertOk();

        $this->assertStringContainsString('activity-log_', $response->headers->get('content-disposition'));
        $this->assertStringContainsString('.xlsx', $response->headers->get('content-disposition'));

        $rows = $this->sheetOf($response);
        $this->assertSame('Activity Log', $rows[0][0]);
        $this->assertStringContainsString('2 rows matching "ana"', $rows[1][0]);
        $this->assertSame(['Student', 'Action'], $rows[3]);
        $this->assertSame(['Ana Cruz', 'Uploaded'], $rows[4]);
        // A cell that reads like a formula is kept as the text it is.
        $this->assertSame('=SUM(1+1)', $rows[5][1]);
    }

    public function test_an_empty_table_still_downloads(): void
    {
        $response = $this->actingAs($this->everyHat(), 'expert')->postJson(route('table.export'), [
            'title' => 'Sections / Programs: [1]',
            'headings' => ['Team'],
            'rows' => [],
        ])->assertOk();

        $rows = $this->sheetOf($response);

        $this->assertSame(['Team'], $rows[3]);
        $this->assertSame('No rows to show.', $rows[4][0]);
    }

    public function test_a_bad_request_is_refused_and_a_signed_out_visitor_is_sent_to_sign_in(): void
    {
        $this->postJson(route('table.export'), ['title' => 'x', 'headings' => ['A'], 'rows' => []])
            ->assertRedirect();

        $this->actingAs($this->everyHat(), 'expert')
            ->postJson(route('table.export'), ['title' => 'x', 'headings' => [], 'rows' => []])
            ->assertStatus(422);
        $this->actingAs($this->everyHat(), 'expert')
            ->postJson(route('table.export'), ['title' => '', 'headings' => ['A'], 'rows' => []])
            ->assertStatus(422);
    }

    /** Opens a report over a table the way the page does, and returns the report's address. */
    private function openReport(CapstoneExpert $expert, string $from = '/capsa/activitylog'): string
    {
        return $this->actingAs($expert, 'expert')->postJson(route('table.report.store'), [
            'title' => 'Activity Log',
            'scope' => '3 rows as shown on the page',
            'from' => $from,
            'headings' => ['Student', 'Team', 'Action'],
            'rows' => [['Ana Cruz', 'Alpha', 'Uploaded'], ['Ben Reyes', 'Bravo', 'Booked'], ['Cara Lim', 'Alpha', 'Joined']],
        ])->assertOk()->json('url');
    }

    public function test_view_report_opens_the_reports_screen_over_the_table_in_the_capsa_portal(): void
    {
        $url = $this->openReport($this->everyHat());

        $this->assertStringContainsString('/table-report/', $url);
        $this->get($url)->assertOk()
            ->assertSee('Activity Log')
            ->assertSee('Ana Cruz')
            ->assertSee('Preview PDF')
            ->assertSee('Download')
            ->assertSee('id="cols-btn"', false)
            ->assertSee('Back to the page')
            ->assertSee('Sign Out');
    }

    public function test_the_expert_portal_gets_the_same_report_inside_its_own_sidebar(): void
    {
        $adviser = CapstoneExpert::factory()->create(['approved_status' => true, 'is_admin' => false]);
        $adviser->roles()->attach(Role::firstOrCreate(['role_name' => 'adviser'])->role_id);

        $url = $this->openReport($adviser, '/instructor/grading');

        $this->get($url)->assertOk()
            ->assertSee('Ben Reyes')
            ->assertSee('id="cols-btn"', false)
            ->assertSee('href="/instructor/grading"', false)
            // The Expert portal's own frame: its stylesheet, its sidebar, its bell.
            ->assertSee('css/expert.css', false)
            ->assertSee('Expert Portal')
            ->assertSee('Schedule Management')
            ->assertSee('Sign Out')
            ->assertSee('function toggleSidebar', false);
    }

    public function test_the_admin_portal_gets_its_own_sidebar_too(): void
    {
        $admin = CapstoneExpert::factory()->create(['approved_status' => true, 'is_admin' => true]);

        $this->get($this->openReport($admin, '/admin/students'))->assertOk()
            ->assertSee('Ben Reyes')
            ->assertSee('Student Accounts')
            ->assertSee('Sign Out')
            ->assertSee('href="/admin/students"', false);
    }

    public function test_an_admin_or_capsa_who_is_also_an_expert_keeps_the_merged_sidebar(): void
    {
        $url = $this->openReport($this->everyHat(), '/instructor/grading');

        $this->get($url)->assertOk()
            ->assertSee('Ben Reyes')
            ->assertSee('Sign Out')
            ->assertSee('Activity Log');
    }

    public function test_the_report_downloads_as_excel_and_pdf_with_the_columns_picked_in_order(): void
    {
        ini_set('memory_limit', '512M');
        $url = $this->openReport($this->everyHat());
        $base = strtok($url, '?');

        $rows = $this->sheetOf($this->get($base.'?download=xlsx&order=c0,c2,c1&hide=c1')->assertOk());

        $this->assertSame('Activity Log', $rows[0][0]);
        $this->assertSame(['Student', 'Action'], array_slice($rows[3], 0, 2));
        $this->assertNull($rows[3][2] ?? null);
        $this->assertSame(['Ben Reyes', 'Booked'], array_slice($rows[5], 0, 2));

        // Searched and sorted the way the page had it.
        $searched = $this->sheetOf($this->get($base.'?download=xlsx&q=alpha&sort=c0&dir=desc')->assertOk());
        $this->assertSame(['Cara Lim', 'Alpha', 'Joined'], array_slice($searched[4], 0, 3));
        $this->assertSame(['Ana Cruz', 'Alpha', 'Uploaded'], array_slice($searched[5], 0, 3));
        $this->assertNull($searched[6][0] ?? null);

        $pdf = $this->get($base.'?download=pdf')->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
        $this->assertStringContainsString('activity-log_', $pdf->headers->get('content-disposition'));
    }

    public function test_a_report_belongs_to_the_person_who_opened_it_and_expires(): void
    {
        $url = $this->openReport($this->everyHat());

        $other = CapstoneExpert::factory()->create(['approved_status' => true, 'is_admin' => true]);
        $this->actingAs($other, 'expert')->get($url)->assertStatus(410)->assertSee('This report has expired');

        $this->actingAs($this->everyHat(), 'expert')
            ->get(route('table.report.show', 'A'.str_repeat('b', 39)))->assertStatus(410);
        $this->actingAs($this->everyHat(), 'expert')
            ->get(route('table.report.show', 'short'))->assertStatus(410);
    }

    public function test_the_way_back_can_only_be_a_page_of_this_site(): void
    {
        $adviser = $this->everyHat();

        foreach (['//evil.example/x', 'https://evil.example/x', 'javascript:alert(1)'] as $from) {
            $url = $this->openReport($adviser, $from);

            $this->get($url)->assertOk()->assertDontSee('evil.example');
        }
    }
}
