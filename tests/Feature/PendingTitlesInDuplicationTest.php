<?php

namespace Tests\Feature;

use App\Models\ArchivedDocument;
use App\Models\CapstonePreProposal;
use App\Models\DuplicationCheck;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Services\OllamaSSHClient;
use App\Services\SimilarityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsChecklists;
use Tests\TestCase;

/**
 * Panel comment 1: look for similar studies in other sections, same year.
 * Recommended titles were already compared; titles turned in and not yet
 * decided are now too, marked "Pending, not yet decided".
 */
class PendingTitlesInDuplicationTest extends TestCase
{
    use BuildsChecklists;
    use RefreshDatabase;

    private function judge(int $embeds = 0): void
    {
        foreach (range(1, 5) as $n) {
            $doc = ArchivedDocument::create([
                'team_id' => 0, 'title' => "Capstone {$n}", 'abstract' => 'An abstract.',
                'academic_year' => '2025', 'program' => 'BSIT', 'file_path' => "archive/{$n}.pdf",
            ]);
            $doc->embedding = json_encode(array_fill(0, 8, 0.5));
            $doc->save();
        }

        $verdict = json_encode(['matches' => true, 'score' => 80, 'reason' => 'Same subject.']);
        $ollama = $this->mock(OllamaSSHClient::class);
        $ollama->shouldReceive('generateMany')->andReturnUsing(fn (array $prompts) => array_fill(0, count($prompts), $verdict));
        $ollama->shouldReceive('generate')->andReturn($verdict);
        $embed = $ollama->shouldReceive('embed')->andReturn(array_fill(0, 8, 0.5));
        if ($embeds) {
            $embed->times($embeds);
        }
    }

    private function team(string $section): Team
    {
        $leader = Student::create([
            'email' => uniqid().'@my.cspc.edu.ph', 'f_name' => 'A', 'l_name' => 'Leader',
            'course' => 'BSIT', 'year_section' => '4th · '.$section,
        ]);
        $team = Team::create([
            'team_name' => 'Team '.$section.uniqid(), 'team_status' => 'Active', 'leader_id' => $leader->student_id,
            'is_confirmed' => true, 'member_count' => 5,
        ]);
        StudentSchoolYear::create(['student_id' => $leader->student_id, 'team_id' => $team->team_id]);

        return $team;
    }

    /** Turned in and not decided yet, unless told otherwise. */
    private function proposal(Team $team, string $title, array $attributes = []): CapstonePreProposal
    {
        $check = DuplicationCheck::create([
            'student_s_y_id' => StudentSchoolYear::where('team_id', $team->team_id)->value('s_y_id'),
            'document_path' => 'duplication-checks/x.pdf', 'concepts' => [], 'top_score' => 20, 'verdict' => 'Passed', 'matches' => [], 'instructors_suggested' => [],
        ]);

        return CapstonePreProposal::create($attributes + [
            'team_id' => $team->team_id, 'duplication_check_id' => $check->id, 'slot' => 1, 'title' => $title,
            'sdg_goals' => '4', 'research_agenda' => [], 'problem_statement' => 'Patients wait in long lines.',
            'proposed_solution' => 'An online queue.', 'scope' => 'One clinic.', 'limitations' => 'None.',
            'sdm' => 'Agile', 'approved_status' => false, 'submitted_at' => now(),
        ]);
    }

    /** This year's titles the check found, title => label. */
    private function thisYear(Team $checking): array
    {
        $matches = app(SimilarityService::class)->findSimilarDocuments([
            'title' => 'Clinic Queue', 'main_topic' => 'queues', 'domain' => 'Web System',
            'keywords' => ['queue'], 'problem_statement' => 'Long lines.', 'proposed_solution' => 'A system.', 'scope' => 'One clinic.',
        ], 10, $checking->team_id);

        return collect($matches)->filter(fn ($m) => $m['recommended'])
            ->mapWithKeys(fn ($m) => [$m['document']->title => $m['recommended']])->all();
    }

    /** A check of this student's team holding these matches. */
    private function check(array $matches): array
    {
        [$team, $leader] = $this->teamInSection('Mine');
        $check = DuplicationCheck::create([
            'student_s_y_id' => StudentSchoolYear::where('student_id', $leader->student_id)->value('s_y_id'),
            'document_path' => 'checks/m.pdf', 'concepts' => [], 'top_score' => 84, 'verdict' => 'High Similarity',
            'matches' => $matches, 'instructors_suggested' => [],
        ]);
        CapstonePreProposal::create([
            'team_id' => $team->team_id, 'duplication_check_id' => $check->id, 'slot' => 1, 'title' => 'Clinic Queue',
            'sdg_goals' => '3', 'research_agenda' => [], 'problem_statement' => 'Long lines.', 'proposed_solution' => 'A system.',
            'scope' => 'One clinic.', 'limitations' => 'None.', 'sdm' => 'Agile',
        ]);

        return [$leader, $check];
    }

    public function test_another_sections_title_still_pending_is_a_match_marked_pending(): void
    {
        $this->judge();
        $this->proposal($this->team('B'), 'ClinicQueue');

        $this->assertSame(['ClinicQueue' => 'Pending, not yet decided — BSIT-4B'], $this->thisYear($this->team('A')));
    }

    public function test_recommended_and_pending_titles_are_told_apart(): void
    {
        $this->judge();
        $this->proposal($this->team('B'), 'Recommended One', ['approved_status' => true]);
        $this->proposal($this->team('C'), 'Pending One');

        $found = $this->thisYear($this->team('A'));
        ksort($found);

        $this->assertSame([
            'Pending One' => 'Pending, not yet decided — BSIT-4C',
            'Recommended One' => 'Recommended this year — BSIT-4B',
        ], $found);
    }

    public function test_a_pending_title_once_recommended_is_relabelled_without_embedding_it_again(): void
    {
        // Two checks of mine plus theirs once.
        $this->judge(embeds: 3);
        $theirs = $this->proposal($this->team('B'), 'ClinicQueue');
        $mine = $this->team('A');

        $this->assertSame('Pending, not yet decided — BSIT-4B', $this->thisYear($mine)['ClinicQueue']);

        $theirs->fresh()->update(['approved_status' => true]);
        $this->assertSame('Recommended this year — BSIT-4B', $this->thisYear($mine)['ClinicQueue']);
    }

    public function test_the_report_lists_a_pending_title_under_this_school_year(): void
    {
        [$leader, $check] = $this->check([
            ['title' => 'ClinicQueue', 'academic_year' => '2026', 'program' => 'BSIT', 'score' => 84, 'reason' => 'Clinic', 'recommended' => 'Pending, not yet decided — BSIT 3C', 'pre_proposal_id' => 998],
            ['title' => 'MediLine', 'academic_year' => '2026', 'program' => 'BSIT', 'score' => 70, 'reason' => 'Lines', 'recommended' => 'Recommended this year — BSIT 3B', 'pre_proposal_id' => 999],
            ['title' => 'Archive One', 'academic_year' => '2023', 'program' => 'BSIT', 'score' => 60, 'reason' => 'Inventory'],
        ]);

        $this->actingAs($leader)->get(route('student.duplication.report', $check->id))
            ->assertOk()
            ->assertSeeText('Pending this year: ClinicQueue (BSIT 3C) is 84% close to yours.')
            ->assertSee('2 recommended this year')
            ->assertSeeInOrder(['Recommended this year <span class="pc-n">2</span>', 'Top 10 archived', 'ClinicQueue', 'BSIT 3C · Pending, not yet decided', 'MediLine', 'BSIT 3B', 'Archive One'], false)
            ->assertDontSee('BSIT 3B · Pending');
    }

    public function test_a_pending_match_shows_their_work_until_it_is_rejected(): void
    {
        $theirs = $this->proposal($this->team('C'), 'ClinicQueue');
        [$leader, $check] = $this->check([
            ['title' => 'ClinicQueue', 'academic_year' => '2026', 'program' => 'BSIT', 'score' => 84, 'reason' => 'Clinic', 'abstract' => '', 'authors' => '', 'recommended' => 'Pending, not yet decided — BSIT 3C', 'pre_proposal_id' => $theirs->id],
        ]);
        $page = fn () => $this->actingAs($leader)->get(route('student.duplication.similar', [$check->id, 1]))->assertOk();

        $page()->assertSee('This year 1 of 1')
            ->assertSee('Pending, not yet decided:', false)
            ->assertSee('Pending pre-proposal')
            ->assertSeeInOrder(['Patients wait in long lines.', 'An online queue.', 'One clinic.']);

        $theirs->update(['rejection_reason' => 'Too broad.']);
        $page()->assertSee('Only the title was kept with this check.')
            ->assertDontSee('Patients wait in long lines.');
    }
}
