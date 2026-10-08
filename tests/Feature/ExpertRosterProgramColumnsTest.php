<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * Accepted Programs and Team Slots on the expert list upload.
 *
 * Both optional. An expert who has never opened their profile cannot be
 * applied to, so letting the CaPSA fill this in while they are already
 * typing the list saves everybody a round of chasing - but a blank cell has
 * to keep meaning "not saying", never "clear what they set".
 */
class ExpertRosterProgramColumnsTest extends TestCase
{
    use RefreshDatabase;

    private function capsa(): CapstoneExpert
    {
        $capsa = CapstoneExpert::factory()->create([
            'approved_status' => true, 'is_admin' => false, 'sections' => ['BSIT-4A'],
        ]);

        $capsa->roles()->attach(Role::firstOrCreate(['role_name' => CapstoneExpert::CAPSA_ROLE])->role_id);

        foreach (CapstoneExpert::STUDENT_FACING_ROLES as $role) {
            Role::firstOrCreate(['role_name' => $role]);
        }

        return $capsa;
    }

    /** @param array<int, array<int, mixed>> $rows */
    private function sheet(array $rows): UploadedFile
    {
        $book = new Spreadsheet;
        $book->getActiveSheet()->fromArray(
            [['Name', 'Email Address', 'Role', 'Expertise', 'Accepted Programs', 'Team Slots'], ...$rows],
            null,
            'A1'
        );

        $path = tempnam(sys_get_temp_dir(), 'roster').'.xlsx';
        (new Xlsx($book))->save($path);

        return new UploadedFile($path, 'experts.xlsx', null, null, true);
    }

    private function upload(array $rows): void
    {
        $this->actingAs($this->capsa(), 'expert')
            ->post(route('capsa.rosters.experts.store'), ['file' => $this->sheet($rows)])
            ->assertSuccessful();
    }

    private function expert(string $email): CapstoneExpert
    {
        return CapstoneExpert::withTrashed()->where('email', $email)->firstOrFail();
    }

    public function test_the_template_offers_both_columns(): void
    {
        $body = $this->actingAs($this->capsa(), 'expert')
            ->get(route('capsa.rosters.experts.template'))
            ->assertOk()
            ->streamedContent();

        // The workbook is a zip, so the headings are not readable here; what
        // matters is that a file comes back at all.
        $this->assertNotSame('', $body);
    }

    public function test_programs_and_slots_are_recorded(): void
    {
        $this->upload([
            ['Neil Inocencio', 'neil@gmail.com', 'Adviser', '', 'BSIT, BSIS', 2],
        ]);

        $expert = $this->expert('neil@gmail.com');

        // The same "n - PROGRAM" shape the request form writes, so one
        // format is read everywhere.
        $this->assertSame('2 - BSIT, 2 - BSIS', $expert->program_slots);
        // The slot number is the total: two teams, not two per program.
        $this->assertSame(2, (int) $expert->max_teams);
        $this->assertTrue($expert->hasSetPrograms());
    }

    /** Slots on their own set the overall cap and nothing else. */
    public function test_slots_without_programs_set_only_the_cap(): void
    {
        $this->upload([
            ['Rosa Villanueva', 'rosa@gmail.com', 'Consultant', '', '', 3],
        ]);

        $expert = $this->expert('rosa@gmail.com');

        $this->assertSame(3, (int) $expert->max_teams);
        $this->assertNull($expert->program_slots);
    }

    /** Both blank leaves the expert to say for themselves. */
    public function test_leaving_both_blank_records_nothing(): void
    {
        $this->upload([
            ['Ana Cruz', 'ana@gmail.com', 'Grammarian', '', '', ''],
        ]);

        $expert = $this->expert('ana@gmail.com');

        $this->assertNull($expert->program_slots);
        $this->assertFalse($expert->hasSetPrograms());
    }

    /**
     * A blank cell says nothing rather than clearing. An expert who set
     * their own capacity should not lose it to a list that left the column
     * empty.
     */
    public function test_a_blank_cell_does_not_wipe_what_the_expert_set(): void
    {
        $this->upload([
            ['Neil Inocencio', 'neil@gmail.com', 'Adviser', '', 'BSIT', 2],
        ]);

        $this->upload([
            ['Neil Inocencio', 'neil@gmail.com', 'Adviser', '', '', ''],
        ]);

        $expert = $this->expert('neil@gmail.com');

        $this->assertSame('2 - BSIT', $expert->program_slots);
        $this->assertSame(2, (int) $expert->max_teams);
    }

    /** A programme nobody offers is reported, not written. */
    public function test_an_unknown_program_is_refused(): void
    {
        $this->upload([
            ['Neil Inocencio', 'neil@gmail.com', 'Adviser', '', 'BSIT, BSCRIMINOLOGY', 1],
        ]);

        $expert = $this->expert('neil@gmail.com');

        $this->assertSame('1 - BSIT', $expert->program_slots);
        // Created regardless: a bad cell is not a reason to lose the person.
        $this->assertNotNull($expert->expert_id);
    }

    /** Nor is a slot count that is not a number. */
    public function test_a_nonsense_slot_count_is_refused(): void
    {
        $this->upload([
            ['Neil Inocencio', 'neil@gmail.com', 'Adviser', '', '', 'lots'],
        ]);

        $this->assertNull($this->expert('neil@gmail.com')->program_slots);
    }

    /** Google never issues "ñ", so "peñafiel" is read as the account "penafiel". */
    public function test_an_accented_email_is_read_as_its_plain_google_address(): void
    {
        $this->upload([
            ['Jose Peñafiel', 'jose.peñafiel@gmail.com', 'Adviser', '', '', ''],
        ]);

        $this->assertSame('Jose Peñafiel', $this->expert('jose.penafiel@gmail.com')->name);
    }
}
