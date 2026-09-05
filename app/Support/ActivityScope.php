<?php

namespace App\Support;

use App\Models\CapstoneExpert;
use App\Models\Student;

/**
 * Which students' activity a given portal user may see.
 *
 * A CaPSA covers named sections and the rest of their portal is scoped to
 * them - SectionScope exists for exactly that - but three surfaces were
 * reading the activity table with no scope at all: the notification bell,
 * the dashboard's recent-activity feed, and the Activity Log page. A CaPSA
 * covering BSIT-3A could watch every other section's teams submit documents,
 * book consultations and apply to experts.
 *
 * One copy of the rule, for the same reason SectionScope is one copy: three
 * surfaces answering the question differently is how a leak survives a fix.
 */
class ActivityScope
{
    /**
     * The student ids whose activity this viewer may see, or null for "all".
     *
     * Null is the admin: they have no sections and their job is the whole
     * system, so their view is deliberately unscoped. Callers must treat null
     * as "do not filter" rather than "filter to nothing".
     *
     * @return array<int, int>|null
     */
    public static function studentIdsFor(?CapstoneExpert $viewer): ?array
    {
        if (! $viewer || $viewer->is_admin) {
            return null;
        }

        $sections = $viewer->sections ?? [];

        // A CaPSA with no sections yet covers nobody. Returning null here
        // would hand them everything, which is the bug this class exists to
        // stop, so it is an empty list on purpose.
        if ($sections === []) {
            return [];
        }

        // The section is computed from the student's course and year, not
        // stored, so it cannot be a WHERE clause. Only the three columns that
        // decide it are loaded.
        return Student::query()
            ->get(['student_id', 'course', 'year_section'])
            ->filter(function ($student) use ($sections) {
                $label = Student::computeSectionLabel($student->course, $student->year_section);

                return $label && in_array($label, $sections, true);
            })
            ->pluck('student_id')
            ->all();
    }
}
