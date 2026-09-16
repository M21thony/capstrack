<?php

namespace Tests\Feature;

use App\Models\CapstonePreProposal;
use App\Models\DuplicationCheck;
use App\Models\Student;
use App\Models\StudentRoster;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Support\ProjectTags;
use App\Support\PreProposalPayload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A pre-proposal says what kind of project it is, picked from a fixed list.
 */
class PreProposalTagsTest extends TestCase
{
    use RefreshDatabase;

    private Student $leader;

    private Team $team;

    private StudentSchoolYear $sy;

    private const TITLE = 'Digital Inventory Patient Records Management System Designed Serving Small Lying Clinics';

    protected function setUp(): void
    {
        parent::setUp();

        $this->leader = Student::create([
            'email' => 'leader@my.cspc.edu.ph', 'f_name' => 'Mark', 'l_name' => 'Balilla',
            'course' => 'BSIT', 'year_section' => '3rd · A',
        ]);
        StudentRoster::create([
            'email' => $this->leader->email, 'name' => 'Mark Balilla', 'section' => '3rd · A',
            'program' => 'BSIT', 'school_year' => \App\Models\Term::currentSchoolYear(),
        ]);
        $this->team = Team::create(['team_name' => 'Taggers', 'leader_id' => $this->leader->student_id]);
        $this->sy = StudentSchoolYear::create(['student_id' => $this->leader->student_id, 'team_id' => $this->team->team_id]);
    }

    private function save(mixed $tags)
    {
        $check = DuplicationCheck::create([
            'student_s_y_id' => $this->sy->s_y_id, 'document_path' => 'duplication-checks/x.pdf',
            'concepts' => [], 'top_score' => 20, 'verdict' => 'Passed', 'matches' => [], 'instructors_suggested' => [],
        ]);

        $payload = [
            'title' => self::TITLE, 'sdg_goals' => 'SDG 3', 'research_agenda' => ['Health'],
            'problem_statement' => 'x', 'proposed_solution' => 'x', 'scope' => 'x', 'limitations' => 'x',
            'sdm' => 'Agile', 'duplication_check_id' => $check->id,
        ];

        if ($tags !== null) {
            $payload['tags'] = $tags;
        }

        return $this->actingAs($this->leader, 'web')->post(route('student.pre-proposal.store'), $payload);
    }

    public function test_tags_are_saved_with_the_pre_proposal(): void
    {
        $this->save(['Mobile App', 'IoT'])->assertSessionHasNoErrors();

        $this->assertSame(['Mobile App', 'IoT'], CapstonePreProposal::first()->tags);
    }

    public function test_a_pre_proposal_without_tags_is_refused(): void
    {
        $this->save(null)->assertSessionHasErrors('tags');
        $this->save([])->assertSessionHasErrors('tags');

        $this->assertSame(0, CapstonePreProposal::count());
    }

    public function test_only_tags_from_the_list_are_accepted(): void
    {
        $this->save(['Mobile App', 'Something Made Up'])->assertSessionHasErrors('tags.1');

        $this->assertSame(0, CapstonePreProposal::count());
    }

    public function test_no_more_than_the_limit(): void
    {
        $this->save(array_slice(ProjectTags::OPTIONS, 0, ProjectTags::MAX + 1))->assertSessionHasErrors('tags');

        $this->assertSame(0, CapstonePreProposal::count());
    }

    public function test_the_capsa_review_sees_the_tags(): void
    {
        $this->save(['Web-based'])->assertSessionHasNoErrors();
        CapstonePreProposal::query()->update(['submitted_at' => now()]);

        $this->assertSame(['Web-based'], PreProposalPayload::forTeam($this->team->fresh())[0]['tags']);
    }

    public function test_the_form_offers_every_tag(): void
    {
        $page = $this->actingAs($this->leader, 'web')->get(route('student.duplication'))->assertOk();

        foreach (ProjectTags::OPTIONS as $tag) {
            $page->assertSee($tag);
        }
    }
}
