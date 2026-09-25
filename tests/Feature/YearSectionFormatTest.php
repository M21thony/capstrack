<?php

namespace Tests\Feature;

use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Year and section are stored one way, "4th · A". A class list with a
 * combined column stored "4th Year · BSCS-4A" as it came, so one section was
 * on file two ways - classmates are matched on the column - and the sidebar
 * read the long one as "BSCS - 4BSCS-4A".
 */
class YearSectionFormatTest extends TestCase
{
    use RefreshDatabase;

    private const DOT = "\u{00B7}";

    /** @return array<string, array{0: ?string, 1: ?string}> */
    public static function shapes(): array
    {
        $dot = self::DOT;

        return [
            'long form' => ["4th Year {$dot} BSCS-4A", "4th {$dot} A"],
            'short form' => ["4th {$dot} A", "4th {$dot} A"],
            'bare number, lower case' => ["3 {$dot} b", "3rd {$dot} B"],
            'first year' => ["1st Year {$dot} BSIT-1C", "1st {$dot} C"],
            'second year' => ["2 {$dot} D", "2nd {$dot} D"],
            'spaces around it' => ["  4th {$dot} A  ", "4th {$dot} A"],
            'no dot is kept as it came' => ['BSIT-4A', 'BSIT-4A'],
            'no section letter is kept' => ["4th {$dot} 1", "4th {$dot} 1"],
            'empty' => ['', null],
            'null' => [null, null],
        ];
    }

    #[DataProvider('shapes')]
    public function test_every_shape_reads_the_short_form(?string $given, ?string $stored): void
    {
        $this->assertSame($stored, Student::normalizeYearSection($given));
    }

    public function test_saving_a_student_stores_the_short_form(): void
    {
        $student = Student::factory()->create(['year_section' => '4th Year '.self::DOT.' BSCS-4A']);

        $this->assertSame('4th '.self::DOT.' A', $student->fresh()->year_section);
    }

    public function test_the_migration_rewrites_rows_already_stored_long(): void
    {
        $long = Student::factory()->create();
        $short = Student::factory()->create(['year_section' => '3rd '.self::DOT.' B']);
        // Past the model, as the rows already on file were written.
        DB::table('students')->where('student_id', $long->student_id)->update(['year_section' => '4th Year '.self::DOT.' BSCS-4A']);
        DB::table('student_rosters')->insert([
            'name' => 'Long, Row', 'email' => 'long.row@my.cspc.edu.ph', 'section' => '4th Year '.self::DOT.' BSCS-4A',
            'program' => 'BSCS', 'school_year' => '2026-2027', 'created_at' => now(), 'updated_at' => now(),
        ]);

        (require database_path('migrations/2026_09_25_173447_normalize_student_year_sections.php'))->up();

        $this->assertSame('4th '.self::DOT.' A', DB::table('students')->where('student_id', $long->student_id)->value('year_section'));
        $this->assertSame('3rd '.self::DOT.' B', DB::table('students')->where('student_id', $short->student_id)->value('year_section'));
        $this->assertSame('4th '.self::DOT.' A', DB::table('student_rosters')->where('email', 'long.row@my.cspc.edu.ph')->value('section'));
    }
}
