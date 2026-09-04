<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\ExpertExpertise;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * A CaPSA uploads the faculty list, and the file says what each of them does.
 *
 * The list is how faculty get accounts at all - nobody applies and nobody
 * picks their own role - so what the file carries is what the account ends up
 * being: approved, named, and holding the roles CaPSA wrote down.
 */
class ExpertListUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The roles are reference data a real install has from its seeder;
        // without them an upload has nothing to point an assignment at.
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    private function capsa(): CapstoneExpert
    {
        return $this->makeCapsa(CapstoneExpert::create([
            'email' => 'capsa.experts@test.com',
            'first_name' => 'Cap',
            'last_name' => 'Sa',
            // Not an Admin: the expert list is the CaPSA's to upload, and it
            // is their copy of Expert Management that offers it. An Admin
            // here would render the other view entirely.
            'is_admin' => false,
            'approved_status' => true,
        ]));
    }

    /** Build a real .xlsx, one sheet per name given. */
    private function listFile(array $sheets): UploadedFile
    {
        $book = new Spreadsheet;
        $book->removeSheetByIndex(0);

        foreach ($sheets as $title => [$header, $rows]) {
            $sheet = $book->createSheet();
            $sheet->setTitle($title);
            $sheet->fromArray([$header], null, 'A1');

            if ($rows !== []) {
                $sheet->fromArray($rows, null, 'A2');
            }
        }

        $path = tempnam(sys_get_temp_dir(), 'experts').'.xlsx';
        (new Xlsx($book))->save($path);

        return new UploadedFile($path, 'Expert List.xlsx', null, null, true);
    }

    private const HEADER = ['Name', 'Email Address', 'Role', 'Expertise'];

    private function rolesOf(string $email): array
    {
        return DB::table('expert_roles')
            ->join('roles', 'roles.role_id', '=', 'expert_roles.role_id')
            ->join('capstone_experts', 'capstone_experts.expert_id', '=', 'expert_roles.expert_id')
            ->where('capstone_experts.email', $email)
            ->pluck('roles.role_name')
            ->sort()
            ->values()
            ->all();
    }

    /** The account is made, approved, and given the role the file names. */
    public function test_a_row_creates_an_approved_account_with_its_role(): void
    {
        $this->actingAs($this->capsa(), 'expert');

        $response = $this->post(route('admin.rosters.experts.store'), [
            'file' => $this->listFile(['Experts' => [self::HEADER, [
                ['Juan Dela Cruz', 'juan@gmail.com', 'Adviser', ''],
            ]]]),
        ]);

        $response->assertOk();
        $response->assertJson(['inserted' => 1, 'skipped' => 0]);

        $expert = CapstoneExpert::where('email', 'juan@gmail.com')->first();

        $this->assertNotNull($expert);
        $this->assertTrue((bool) $expert->approved_status);
        $this->assertSame('Juan', $expert->first_name);
        $this->assertSame('Dela Cruz', $expert->last_name);
        $this->assertSame(['adviser'], $this->rolesOf('juan@gmail.com'));
    }

    /** One person can hold more than one role, written in one cell. */
    public function test_a_row_can_name_more_than_one_role(): void
    {
        $this->actingAs($this->capsa(), 'expert');

        $this->post(route('admin.rosters.experts.store'), [
            'file' => $this->listFile(['Experts' => [self::HEADER, [
                ['Maria Santos', 'maria@gmail.com', 'Consultant, Grammarian', ''],
            ]]]),
        ])->assertOk();

        $this->assertSame(['consultant', 'grammarian'], $this->rolesOf('maria@gmail.com'));
    }

    /** Expertise is read when given, as its own rows. */
    public function test_expertise_is_recorded_when_the_list_gives_it(): void
    {
        $this->actingAs($this->capsa(), 'expert');

        $this->post(route('admin.rosters.experts.store'), [
            'file' => $this->listFile(['Experts' => [self::HEADER, [
                ['Ana Reyes', 'ana@gmail.com', 'Adviser', 'Research Methodology, Data Analytics'],
            ]]]),
        ])->assertOk();

        $expert = CapstoneExpert::where('email', 'ana@gmail.com')->first();

        $this->assertSame(
            ['Data Analytics', 'Research Methodology'],
            ExpertExpertise::where('expert_id', $expert->expert_id)->pluck('expertise_field')->sort()->values()->all()
        );
    }

    /**
     * Expertise left blank means "not saying", not "clear it".
     *
     * An expert records their own areas from their profile, so a list that
     * leaves the column empty must not wipe what they put there.
     */
    public function test_a_blank_expertise_column_leaves_what_the_expert_recorded(): void
    {
        $this->actingAs($this->capsa(), 'expert');

        $expert = CapstoneExpert::create([
            'email' => 'kept@gmail.com',
            'first_name' => 'Kept',
            'last_name' => 'Skills',
            'approved_status' => true,
        ]);

        ExpertExpertise::create(['expert_id' => $expert->expert_id, 'expertise_field' => 'Machine Learning']);

        $this->post(route('admin.rosters.experts.store'), [
            'file' => $this->listFile(['Experts' => [self::HEADER, [
                ['Kept Skills', 'kept@gmail.com', 'Adviser', ''],
            ]]]),
        ])->assertOk();

        $this->assertSame(
            ['Machine Learning'],
            ExpertExpertise::where('expert_id', $expert->expert_id)->pluck('expertise_field')->all()
        );
    }

    /**
     * The older three-sheet file still works, the sheet name naming the role.
     *
     * CaPSA keeps this file by hand; a new template must not make the one she
     * already has stop importing.
     */
    public function test_the_older_three_sheet_file_still_imports(): void
    {
        $this->actingAs($this->capsa(), 'expert');

        $header = ['Name', 'Email Address'];

        $this->post(route('admin.rosters.experts.store'), [
            'file' => $this->listFile([
                'Adviser' => [$header, [['Old Shape', 'oldshape@gmail.com']]],
                'Grammarian' => [$header, [['Old Shape', 'oldshape@gmail.com']]],
            ]),
        ])->assertOk()->assertJson(['inserted' => 1]);

        // Listed on two sheets is two roles, not a fight between them.
        $this->assertSame(['adviser', 'grammarian'], $this->rolesOf('oldshape@gmail.com'));
    }

    /** A word that is not a role is reported, and the account still made. */
    public function test_an_unrecognised_role_is_reported_without_losing_the_account(): void
    {
        $this->actingAs($this->capsa(), 'expert');

        $response = $this->post(route('admin.rosters.experts.store'), [
            'file' => $this->listFile(['Experts' => [self::HEADER, [
                ['Typo Person', 'typo@gmail.com', 'Advisor', ''],
            ]]]),
        ]);

        $response->assertOk()->assertJson(['inserted' => 1]);
        $this->assertStringContainsString('Advisor', $response->json('errors.0'));

        $this->assertNotNull(CapstoneExpert::where('email', 'typo@gmail.com')->first());
        $this->assertSame([], $this->rolesOf('typo@gmail.com'));
    }

    /** An upload can neither grant nor revoke CaPSA. */
    public function test_an_upload_does_not_touch_a_capsa_account(): void
    {
        $capsa = $this->capsa();
        $this->actingAs($capsa, 'expert');

        $response = $this->post(route('admin.rosters.experts.store'), [
            'file' => $this->listFile(['Experts' => [self::HEADER, [
                ['Someone Else', $capsa->email, 'Adviser', ''],
            ]]]),
        ]);

        $response->assertOk()->assertJson(['skipped' => 1, 'inserted' => 0]);

        $capsa->refresh();
        $this->assertSame('Cap', $capsa->first_name);
        $this->assertContains(CapstoneExpert::CAPSA_ROLE, $this->rolesOf($capsa->email));
    }

    /** The template carries the columns the upload reads. */
    public function test_the_template_is_offered_with_the_columns_that_are_read(): void
    {
        $this->actingAs($this->capsa(), 'expert');

        $response = $this->get(route('admin.rosters.experts.template'));

        $response->assertOk();
        $response->assertDownload('Expert List Template.xlsx');

        $sheet = IOFactory::load($response->baseResponse->getFile()->getPathname())->getActiveSheet();

        $this->assertSame(['Name', 'Email Address', 'Role', 'Expertise'], $sheet->toArray()[0]);
    }

    /**
     * The template, filled in, is a list this accepts.
     *
     * Matching headings is not the same as the upload reading them - this
     * walks the template through the real import so the two cannot drift.
     */
    public function test_a_list_written_from_the_template_imports(): void
    {
        $this->actingAs($this->capsa(), 'expert');

        $headings = IOFactory::load(
            $this->get(route('admin.rosters.experts.template'))->baseResponse->getFile()->getPathname()
        )->getActiveSheet()->toArray()[0];

        $this->post(route('admin.rosters.experts.store'), [
            'file' => $this->listFile(['Experts' => [$headings, [
                ['From Template', 'fromtemplate@gmail.com', 'Consultant', 'UI/UX Design'],
            ]]]),
        ])->assertOk()->assertJson(['inserted' => 1, 'skipped' => 0]);

        $expert = CapstoneExpert::where('email', 'fromtemplate@gmail.com')->first();

        $this->assertNotNull($expert);
        $this->assertSame(['consultant'], $this->rolesOf('fromtemplate@gmail.com'));
        $this->assertSame(
            ['UI/UX Design'],
            ExpertExpertise::where('expert_id', $expert->expert_id)->pluck('expertise_field')->all()
        );
    }

    /** The modal offers the template, where the upload is done. */
    public function test_the_upload_modal_offers_the_template(): void
    {
        $this->actingAs($this->capsa(), 'expert');

        $response = $this->get(route('admin.experts'));

        $response->assertOk();
        $response->assertSee(route('admin.rosters.experts.template'), false);
        $response->assertSee('Download template', false);

        // A file can be dropped as well as browsed for, and either way it
        // lands in the one input the upload reads.
        $response->assertSee('id="expertRosterDrop"', false);
        $response->assertSee('id="expertRosterChip"', false);

        // Only .xlsx is read, so offering CSV would be an invitation to a
        // file that gets refused at the door.
        $response->assertDontSee('CSV', false);
        $response->assertDontSee('.csv', false);
    }

    // -- The preview -------------------------------------------------------

    /**
     * The preview reads the file and writes nothing.
     *
     * An upload approves people and grants them work in one go, and nothing
     * undoes it, so looking has to be free.
     */
    public function test_previewing_a_list_changes_nothing(): void
    {
        $this->actingAs($this->capsa(), 'expert');

        $this->post(route('admin.rosters.experts.preview'), [
            'file' => $this->listFile(['Experts' => [self::HEADER, [
                ['Nobody Yet', 'nobodyyet@gmail.com', 'Adviser', 'Data Analytics'],
            ]]]),
        ])->assertOk();

        $this->assertNull(CapstoneExpert::where('email', 'nobodyyet@gmail.com')->first());
        $this->assertSame([], $this->rolesOf('nobodyyet@gmail.com'));
    }

    /** Each person is reported as what they would become. */
    public function test_the_preview_says_what_each_person_would_become(): void
    {
        $this->actingAs($this->capsa(), 'expert');

        // Already an approved adviser - the row would change nothing.
        $same = CapstoneExpert::create([
            'email' => 'same@gmail.com',
            'first_name' => 'Same',
            'last_name' => 'Same',
            'approved_status' => true,
        ]);
        CapstoneExpert::syncRoles($same->expert_id, [DB::table('roles')->where('role_name', 'adviser')->value('role_id')]);

        // An adviser the list now names as a grammarian.
        $moved = CapstoneExpert::create([
            'email' => 'moved@gmail.com',
            'first_name' => 'Moved',
            'last_name' => 'Role',
            'approved_status' => true,
        ]);
        CapstoneExpert::syncRoles($moved->expert_id, [DB::table('roles')->where('role_name', 'adviser')->value('role_id')]);

        $response = $this->post(route('admin.rosters.experts.preview'), [
            'file' => $this->listFile(['Experts' => [self::HEADER, [
                ['Fresh Face', 'fresh@gmail.com', 'Consultant', ''],
                ['Same Same', 'same@gmail.com', 'Adviser', ''],
                ['Moved Role', 'moved@gmail.com', 'Grammarian', ''],
            ]]]),
        ]);

        $response->assertOk();
        $response->assertJson(['create' => 1, 'update' => 1, 'unchanged' => 1, 'skipped' => 0]);

        $rows = collect($response->json('rows'))->keyBy('email');

        $this->assertSame('create', $rows['fresh@gmail.com']['action']);
        $this->assertSame('unchanged', $rows['same@gmail.com']['action']);
        $this->assertSame('update', $rows['moved@gmail.com']['action']);

        // Named, so a CaPSA can see it is the roles about to move.
        $this->assertSame(['roles'], $rows['moved@gmail.com']['changes']);
        $this->assertSame(['grammarian'], $rows['moved@gmail.com']['roles']);
    }

    /** Expertise the row would record is shown before it is recorded. */
    public function test_the_preview_shows_the_expertise_a_row_carries(): void
    {
        $this->actingAs($this->capsa(), 'expert');

        $row = $this->post(route('admin.rosters.experts.preview'), [
            'file' => $this->listFile(['Experts' => [self::HEADER, [
                ['Ana Reyes', 'anapreview@gmail.com', 'Adviser', 'Research Methodology, Data Analytics'],
            ]]]),
        ])->assertOk()->json('rows.0');

        $this->assertSame(['Research Methodology', 'Data Analytics'], $row['expertise']);
    }

    /** A row the upload would skip is reported as skipped, not as a change. */
    public function test_the_preview_reports_the_rows_that_would_be_skipped(): void
    {
        $capsa = $this->capsa();
        $this->actingAs($capsa, 'expert');

        $response = $this->post(route('admin.rosters.experts.preview'), [
            'file' => $this->listFile(['Experts' => [self::HEADER, [
                ['Someone Else', $capsa->email, 'Adviser', ''],
            ]]]),
        ]);

        $response->assertOk();
        $response->assertJson(['skipped' => 1, 'create' => 0, 'update' => 0]);
        $this->assertSame([], $response->json('rows'));
    }

    /** The preview and the upload agree, because they read the same way. */
    public function test_what_the_preview_says_is_what_the_upload_does(): void
    {
        $this->actingAs($this->capsa(), 'expert');

        $rows = [['Agree Ment', 'agree@gmail.com', 'Consultant, Grammarian', 'UI/UX Design']];

        $preview = $this->post(route('admin.rosters.experts.preview'), [
            'file' => $this->listFile(['Experts' => [self::HEADER, $rows]]),
        ])->assertOk();

        $this->assertSame('create', $preview->json('rows.0.action'));
        $this->assertSame(['consultant', 'grammarian'], $preview->json('rows.0.roles'));

        $this->post(route('admin.rosters.experts.store'), [
            'file' => $this->listFile(['Experts' => [self::HEADER, $rows]]),
        ])->assertOk()->assertJson(['inserted' => 1]);

        $this->assertSame(['consultant', 'grammarian'], $this->rolesOf('agree@gmail.com'));

        // And a second look now reports it as settled, not as a change.
        $this->post(route('admin.rosters.experts.preview'), [
            'file' => $this->listFile(['Experts' => [self::HEADER, $rows]]),
        ])->assertOk()->assertJson(['unchanged' => 1, 'create' => 0, 'update' => 0]);
    }

    /** Saving is offered without previewing first. */
    public function test_the_modal_offers_both_preview_and_save(): void
    {
        $this->actingAs($this->capsa(), 'expert');

        $response = $this->get(route('admin.experts'));

        $response->assertOk();
        $response->assertSee('id="expertRosterPreviewBtn"', false);
        $response->assertSee('id="expertRosterStep2"', false);
        $response->assertSee('id="expertRosterPreviewTable"', false);
        $response->assertSee('uploadExpertRoster(this)', false);
    }
}
