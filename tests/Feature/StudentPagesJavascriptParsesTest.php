<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use PHPUnit\Framework\Attributes\DataProvider;
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

    #[DataProvider('studentPages')]
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
        // checks, and there is nothing of ours inside the tag to parse. A
        // type="application/json" block is data (the upload pop-up's
        // addresses), not a script.
        preg_match_all('/<script(?![^>]*\b(?:src=|type="application\/json"))[^>]*>(.*?)<\/script>/s', $html, $matches);

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

    /**
     * The team page's shared consts have to be declared for everyone.
     *
     * They used to sit inside the leader-only branch, so for a member who is
     * not the leader - and for a student with no team - the DOMContentLoaded
     * handler called updateModalUI(), which reads CREATION_ALLOWED, and threw
     * a ReferenceError. That killed the rest of the block with it: the
     * skeleton fade, the popups, the invitation modals. The leader's page
     * worked, which is why it went unnoticed.
     */
    public function test_shared_team_consts_are_declared_for_a_member_who_is_not_the_leader(): void
    {
        $html = $this->actingAs($this->memberWhoIsNotTheLeader(), 'web')
            ->get(route('student.team'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('const CREATION_ALLOWED', $html);
        $this->assertStringContainsString('const JOINING_ALLOWED', $html);
        $this->assertStringContainsString('const CREATION_CLOSED_MSG', $html);
    }

    public function test_shared_team_consts_are_declared_for_a_student_with_no_team(): void
    {
        $student = Student::create([
            'email' => 'teamless.js.check@my.cspc.edu.ph',
            'f_name' => 'Tanya',
            'l_name' => 'Villamor',
            'course' => 'BSIT',
            'year_section' => '4th · A',
        ]);

        $html = $this->actingAs($student, 'web')
            ->get(route('student.team'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('const CREATION_ALLOWED', $html);
        $this->assertStringContainsString('const JOINING_ALLOWED', $html);
    }

    private function nodeIsAvailable(): bool
    {
        return Process::run(['node', '--version'])->successful();
    }

    /** In a team, but somebody else leads it. */
    private function memberWhoIsNotTheLeader(): Student
    {
        $leader = Student::create([
            'email' => 'leader.js.check@my.cspc.edu.ph',
            'f_name' => 'Liza',
            'l_name' => 'Bautista',
            'course' => 'BSIT',
            'year_section' => '4th · A',
        ]);

        $member = Student::create([
            'email' => 'member.js.check@my.cspc.edu.ph',
            'f_name' => 'Marco',
            'l_name' => 'Reyes',
            'course' => 'BSIT',
            'year_section' => '4th · A',
        ]);

        $team = Team::create([
            'team_name' => 'Sparrows',
            'leader_id' => $leader->student_id,
        ]);

        foreach ([$leader, $member] as $student) {
            StudentSchoolYear::create([
                'student_id' => $student->student_id,
                'team_id' => $team->team_id,
            ]);
        }

        return $member;
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
