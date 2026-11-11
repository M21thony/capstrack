<?php

namespace Tests\Concerns;

use App\Models\CapstoneExpert;
use App\Models\Checklist;
use App\Models\Deliverable;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Support\MilestoneProgress;
use App\Support\MilestoneTemplate;
use Illuminate\Support\Facades\DB;

/**
 * Fixtures for the one-checklist-per-CaPSA/TSA model: a CaPSA/TSA who handles a
 * section, a team whose members put it in that section, the checklist they
 * follow, and uploads against its deliverables.
 */
trait BuildsChecklists
{
    protected function capsaFor(array $sections = ['BSIT-3A']): CapstoneExpert
    {
        return $this->makeCapsa(CapstoneExpert::factory()->create(['approved_status' => true, 'sections' => $sections]));
    }

    /** @return array{0: Team, 1: Student} A team whose leader puts it in BSIT-3A unless told otherwise. */
    protected function teamInSection(
        string $name,
        string $course = 'Bachelor of Science in Information Technology',
        string $yearSection = '3rd · A',
    ): array {
        $leader = Student::factory()->withCompleteProfile()->create([
            'email' => strtolower(preg_replace('/\W+/', '', $name)).'.'.uniqid().'@my.cspc.edu.ph',
            'course' => $course,
            'year_section' => $yearSection,
        ]);

        $team = Team::create([
            'team_name' => $name,
            'team_status' => 'Active',
            'leader_id' => $leader->student_id,
            'is_confirmed' => true,
            'member_count' => 5,
        ]);

        StudentSchoolYear::create(['student_id' => $leader->student_id, 'team_id' => $team->team_id]);

        return [$team, $leader];
    }

    /** With every milestone already opened by the CaPSA/TSA. */
    protected function departmentChecklist(CapstoneExpert $capsa, string $program = 'BSIT'): Checklist
    {
        $checklist = MilestoneTemplate::createChecklist($capsa->expert_id, $program);
        $checklist->milestones()->update(['opened_at' => now()]);

        return $checklist;
    }

    /** A checklist with one empty milestone, for deliverables added by hand. */
    protected function emptyChecklist(CapstoneExpert $capsa, string $program = 'BSIT', string $milestone = 'Capstone 1'): Checklist
    {
        $checklist = Checklist::create(['capsa_id' => $capsa->expert_id, 'program' => $program, 'title' => 'Capstone 1 Checklist']);
        $checklist->milestones()->create(['name' => $milestone, 'position' => 0, 'opened_at' => now()]);

        return $checklist;
    }

    protected function addDeliverable(Checklist $checklist, array $attributes): Deliverable
    {
        $milestone = $checklist->milestones()->first();

        return $milestone->deliverables()->create($attributes + [
            'kind' => Deliverable::FORM,
            'position' => (int) $milestone->deliverables()->max('position') + 1,
        ]);
    }

    protected function line(Checklist $checklist, string $templateKey): Deliverable
    {
        return $checklist->deliverables()->where('deliverables.template_key', $templateKey)->firstOrFail();
    }

    protected function upload(Student $student, ?Deliverable $deliverable, ?string $at = null, ?string $chapter = null, string $path = 'submissions/file.pdf'): int
    {
        $at ??= now()->toDateTimeString();

        return DB::table('capstone_documents')->insertGetId([
            'student_id' => $student->student_id,
            'team_id' => StudentSchoolYear::where('student_id', $student->student_id)->value('team_id'),
            'file_name' => pathinfo($path, PATHINFO_FILENAME),
            'file_path' => $path,
            'chapter' => $chapter,
            'deliverable_id' => $deliverable?->id,
            'file_version' => 1,
            'document_status' => 'Pending',
            'created_at' => $at,
            'updated_at' => $at,
        ]);
    }

    protected function progressOf(Checklist $checklist, Team $team, Deliverable $deliverable): object
    {
        return MilestoneProgress::forTeam($checklist, $team->team_id)
            ->items
            ->first(fn ($row) => $row->item->id === $deliverable->id);
    }
}
