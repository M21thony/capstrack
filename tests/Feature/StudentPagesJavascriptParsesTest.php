<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

/**
 * The inline scripts on the student pages actually parse.
 *
 * This exists because of a bug that shipped. A string in the pre-proposal
 * page's submit confirmation was written with two real newlines inside the
 * quotes instead of "\n\n", which is a syntax error - and a syntax error kills
 * the whole <script> block, so every function declared in it went undefined.
 * The submit button then had no handler, the confirmation never appeared, and
 * the form posted straight through.
 *
 * Nothing caught it. The tests around it assert on rendered HTML, and the HTML
 * was perfect: the button was there, the markup was there, the strings were
 * there. Only running the script finds this, so that is what this does - it
 * hands each block to node --check.
 */
class StudentPagesJavascriptParsesTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<int,array{0:string}> */
    public static function studentPages(): array
    {
        return [
            ['student.duplication'],
            ['student.experts'],
            ['student.team'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('studentPages')]
    public function test_every_inline_script_parses(string $routeName): void
    {
        if (! $this->nodeIsAvailable()) {
            $this->markTestSkipped('node is not on PATH, so the scripts cannot be parsed here.');
        }

        $html = $this->actingAs($this->studentInATeam(), 'web')
            ->get(route($routeName))
            ->assertOk()
            ->getContent();

        // Inline blocks only. A src= script is a built asset with its own
        // checks, and there is nothing of ours inside the tag to parse.
        preg_match_all('/<script(?![^>]*\bsrc=)[^>]*>(.*?)<\/script>/s', $html, $matches);

        $this->assertNotEmpty($matches[1], 'the page carries inline script');

        foreach ($matches[1] as $i => $script) {
            if (trim($script) === '') {
                continue;
            }

            $file = tempnam(sys_get_temp_dir(), 'capstrack_js_').'.js';
            file_put_contents($file, $script);

            $result = Process::run(['node', '--check', $file]);
            @unlink($file);

            $this->assertTrue(
                $result->successful(),
                "Inline script #{$i} on {$routeName} does not parse:\n".$result->errorOutput()
            );
        }
    }

    private function nodeIsAvailable(): bool
    {
        return Process::run(['node', '--version'])->successful();
    }

    private function studentInATeam(): Student
    {
        $student = Student::create([
            'email' => 'js.check@my.cspc.edu.ph',
            'f_name' => 'Jerome',
            'l_name' => 'Abella',
            'course' => 'BSIT',
            'year_section' => '4th · A',
        ]);

        $team = Team::create([
            'team_name' => 'Codebreakers',
            'leader_id' => $student->student_id,
        ]);

        StudentSchoolYear::create([
            'student_id' => $student->student_id,
            'team_id' => $team->team_id,
        ]);

        return $student;
    }
}
