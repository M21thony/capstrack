<?php

namespace Tests\Feature;

use App\Models\CapstonePreProposal;
use App\Models\DuplicationCheck;
use App\Models\Student;
use App\Models\StudentRoster;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A team cannot turn the same proposal in twice.
 *
 * Turning in more than one title exists so the CaPSA has a choice. The same
 * title twice, or the same scanned document twice, is not a choice - and it
 * went in silently, so a team could fill every slot from one proposal and
 * believe they had done the work.
 */
class NoDuplicatePreProposalTest extends TestCase
{
    use RefreshDatabase;

    private Student $leader;
    private Team $team;
    private StudentSchoolYear $sy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->leader = Student::create([
            'email' => 'leader@my.cspc.edu.ph', 'f_name' => 'Mark', 'l_name' => 'Balilla',
            'course' => 'BSIT', 'year_section' => '4th · A',
        ]);
        StudentRoster::create([
            'email' => $this->leader->email, 'name' => 'Mark Balilla', 'section' => '4th · A',
            'program' => 'BSIT', 'school_year' => \App\Models\Term::currentSchoolYear(),
        ]);
        $this->team = Team::create(['team_name' => 'Bulbasaurus', 'leader_id' => $this->leader->student_id]);
        $this->sy = StudentSchoolYear::create([
            'student_id' => $this->leader->student_id, 'team_id' => $this->team->team_id,
        ]);
    }

    private function check(): DuplicationCheck
    {
        return DuplicationCheck::create([
            'student_s_y_id' => $this->sy->s_y_id,
            'document_path' => 'duplication-checks/x.pdf',
            'concepts' => [], 'top_score' => 20, 'verdict' => 'Passed',
            'matches' => [], 'instructors_suggested' => [],
        ]);
    }

    private function submit(string $title, DuplicationCheck $check)
    {
        return $this->actingAs($this->leader, 'web')->post(route('student.pre-proposal.store'), [
            'title' => $title,
            'sdg_goals' => 'SDG 4', 'research_agenda' => ['Education'],
            'problem_statement' => 'x', 'proposed_solution' => 'x',
            'scope' => 'x', 'limitations' => 'x', 'sdm' => 'Agile',
            'clients' => 'Barangay Hall', 'client_address' => 'Naga City',
            'client_representative' => 'Ana Cruz', 'client_position' => 'Secretary',
            'tags' => ['Mobile App'],
            'duplication_check_id' => $check->id,
        ]);
    }

    /** Eleven meaningful words, which the title rule demands. */
    private const TITLE = 'Digital Inventory Patient Records Management System Designed Serving Small Lying Clinics';

    private const OTHER = 'Sentiment Aware Feedback Portal Helping Faculty Understand Student Course Evaluation Comments';

    public function test_the_same_document_cannot_be_turned_in_twice(): void
    {
        $check = $this->check();

        $this->submit(self::TITLE, $check);
        $this->assertSame(1, CapstonePreProposal::where('team_id', $this->team->team_id)->count());

        // A different title, but the same scan standing as its evidence.
        $this->submit(self::OTHER, $check)
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'same document'));

        $this->assertSame(1, CapstonePreProposal::where('team_id', $this->team->team_id)->count());
    }

    public function test_the_same_title_cannot_be_turned_in_twice(): void
    {
        $this->submit(self::TITLE, $this->check());

        // Retyped: different capitals, spacing and a stray full stop.
        $this->submit('  digital inventory  PATIENT records management system designed serving small lying clinics. ', $this->check())
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'already turned that title in'));

        $this->assertSame(1, CapstonePreProposal::where('team_id', $this->team->team_id)->count());
    }

    /** Two genuinely different proposals still go in. */
    public function test_two_different_proposals_are_accepted(): void
    {
        $this->submit(self::TITLE, $this->check());
        $this->submit(self::OTHER, $this->check());

        $this->assertSame(2, CapstonePreProposal::where('team_id', $this->team->team_id)->count());
    }
}
