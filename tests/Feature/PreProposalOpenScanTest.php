<?php

namespace Tests\Feature;

use App\Models\CapstonePreProposal;
use App\Models\DuplicationCheck;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsChecklists;
use Tests\TestCase;

/**
 * The scan drawn on the pre-proposal page is the signed-in student's own
 * latest one, until it is saved. It used to be the team's latest unsaved
 * scan: one member's scan showed on every teammate's page, and saving it
 * brought back an older scan the team had moved on from.
 */
class PreProposalOpenScanTest extends TestCase
{
    use BuildsChecklists;
    use RefreshDatabase;

    private Team $team;

    private Student $leader;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->team, $this->leader] = $this->teamInSection('Piplup');
    }

    private function member(): Student
    {
        $member = Student::factory()->withCompleteProfile()->create(['course' => 'Bachelor of Science in Information Technology', 'year_section' => '3rd · A']);
        StudentSchoolYear::create(['student_id' => $member->student_id, 'team_id' => $this->team->team_id]);

        return $member;
    }

    private function scan(Student $student, string $title): DuplicationCheck
    {
        return DuplicationCheck::create([
            'student_s_y_id' => StudentSchoolYear::where('student_id', $student->student_id)->value('s_y_id'),
            'document_path' => 'checks/'.uniqid().'.pdf', 'concepts' => ['title' => $title],
            'top_score' => 10, 'verdict' => 'Passed', 'matches' => [], 'instructors_suggested' => [],
        ]);
    }

    private function save(DuplicationCheck $check, int $slot): void
    {
        CapstonePreProposal::create([
            'team_id' => $this->team->team_id, 'duplication_check_id' => $check->id, 'slot' => $slot, 'title' => 'Saved '.$slot,
            'sdg_goals' => 'SDG 4', 'research_agenda' => ['x'], 'problem_statement' => 'p', 'proposed_solution' => 's',
            'scope' => 'sc', 'limitations' => 'l', 'sdm' => 'Agile',
        ]);
    }

    public function test_a_student_sees_their_own_unsaved_scan_after_a_reload(): void
    {
        $check = $this->scan($this->leader, 'My Scan');

        $this->actingAs($this->leader)->get(route('student.duplication'))
            ->assertOk()
            ->assertViewHas('lastCheck', fn ($shown) => $shown?->id === $check->id);
    }

    public function test_a_teammates_scan_does_not_show_on_another_members_page(): void
    {
        $this->scan($this->leader, 'Leader Scan');

        $this->actingAs($this->member())->get(route('student.duplication'))
            ->assertOk()
            ->assertViewHas('lastCheck', null);
    }

    public function test_saving_the_latest_scan_does_not_bring_back_an_older_one(): void
    {
        $this->scan($this->leader, 'Abandoned Scan');
        $latest = $this->scan($this->leader, 'Saved Scan');
        $this->save($latest, 1);

        $this->actingAs($this->leader)->get(route('student.duplication'))
            ->assertOk()
            ->assertViewHas('lastCheck', null)
            ->assertDontSee('Abandoned Scan');
    }

    /**
     * Clearing only the latest scan let openScan() fall back to the one
     * before it, so the result never went away however often it was cleared.
     */
    public function test_clearing_a_scan_does_not_bring_back_an_older_one(): void
    {
        $saved = $this->scan($this->leader, 'Saved Scan');
        $this->save($saved, 1);
        $this->scan($this->leader, 'First Try');
        $latest = $this->scan($this->leader, 'Second Try');
        $teammates = $this->scan($this->member(), 'Teammate Scan');

        $this->actingAs($this->leader)
            ->deleteJson(route('student.pre-proposal.check.destroy', $latest))
            ->assertOk();

        $this->actingAs($this->leader)->get(route('student.duplication'))
            ->assertViewHas('lastCheck', null)
            ->assertDontSee('First Try');

        $this->assertModelExists($saved);
        $this->assertModelExists($teammates);
    }

    public function test_a_new_account_with_no_team_sees_no_ones_scan(): void
    {
        $this->scan($this->leader, 'Leader Scan');
        $newcomer = Student::factory()->withCompleteProfile()->create();
        StudentSchoolYear::create(['student_id' => $newcomer->student_id]);

        $this->actingAs($newcomer)->get(route('student.duplication'))
            ->assertOk()
            ->assertViewHas('lastCheck', null)
            ->assertDontSee('Leader Scan');
    }

    public function test_a_newer_scan_replaces_an_older_unsaved_one(): void
    {
        $this->scan($this->leader, 'First Try');
        $newer = $this->scan($this->leader, 'Second Try');

        $this->actingAs($this->leader)->get(route('student.duplication'))
            ->assertViewHas('lastCheck', fn ($shown) => $shown?->id === $newer->id);
    }
}
