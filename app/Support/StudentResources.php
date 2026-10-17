<?php

namespace App\Support;

use App\Models\CapstoneExpert;
use App\Models\GradingRubric;
use App\Models\Resource;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * What a student finds on their Resources page: the files their own CaPSA
 * posted for them, and the rubrics their panel will grade them with.
 *
 * The page lists these and the download routes hand them over, so both ask
 * this one class and cannot disagree about who may open what.
 */
class StudentResources
{
    /**
     * The student's program: the enrolment row's when it has one, else their
     * course. Null when neither says, which shows them only what is meant for
     * every program.
     */
    public function program(Student $student): ?string
    {
        $enrolment = StudentSchoolYear::where('student_id', $student->student_id)->first();

        return $enrolment?->program ?: Student::mapCourseToProgram($student->course ?? '');
    }

    /** The student's section label, "BSIT-4B", or null when it cannot be read. */
    public function section(Student $student): ?string
    {
        return Student::computeSectionLabel($student->course, $student->year_section);
    }

    /**
     * The files this student may see, newest first.
     *
     * Their own CaPSA's uploads only. One aimed at named sections reaches
     * those sections; one aimed at none is scoped by program, or reaches every
     * program when it names none. No CaPSA covering the student means no
     * files - deliberately, not everything: an empty list read as "no filter"
     * is how this page once showed every program's material to everybody.
     *
     * @return Builder<resource>
     */
    public function files(Student $student): Builder
    {
        $program = $this->program($student);
        $section = $this->section($student);

        // The section is computed rather than stored, so the CaPSAs covering
        // it are matched in PHP. There are a handful of them.
        $capsaIds = $section
            ? CapstoneExpert::whereHas('roles', fn ($q) => $q->where('role_name', 'capstone_adviser'))
                ->get(['expert_id', 'sections'])
                ->filter(fn ($capsa) => in_array($section, $capsa->sections ?? [], true))
                ->pluck('expert_id')
                ->all()
            : [];

        return Resource::query()
            ->with('sections')
            ->whereIn('uploaded_by', $capsaIds)
            ->where(function ($q) use ($program, $section) {
                $q->whereHas('sections', fn ($w) => $w->where('section', $section ?: '__none__'));

                $q->orWhere(fn ($w) => $w
                    ->whereDoesntHave('sections')
                    ->where(fn ($p) => $p
                        ->when($program, fn ($x) => $x->where('resource_for', $program))
                        ->orWhereNull('resource_for')));
            })
            ->orderByDesc('uploaded_at');
    }

    /**
     * The rubric for each defense stage that has one, in stage order.
     *
     * The one written for the student's program wins; otherwise the stage's
     * rubric for every program - the same one the panel grades with.
     *
     * @return Collection<int, array{stage: string, slug: string, rubric: GradingRubric}>
     */
    public function rubrics(?string $program): Collection
    {
        return collect(DefenseProgress::STAGES)
            ->map(fn (string $stage) => [
                'stage' => $stage,
                'slug' => Str::slug($stage),
                'rubric' => GradingRubric::forStage($stage, $program),
            ])
            ->filter(fn (array $row) => $row['rubric'] !== null)
            ->values();
    }

    /**
     * The rubric behind a stage's slug, "title-defense", for this program.
     *
     * @return array{stage: string, slug: string, rubric: GradingRubric}|null
     */
    public function rubric(string $slug, ?string $program): ?array
    {
        return $this->rubrics($program)->firstWhere('slug', $slug);
    }
}
