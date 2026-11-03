<?php

namespace App\Support;

use App\Models\Announcement;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use Illuminate\Support\Collection;

/**
 * Which students an announcement reaches.
 *
 * The student dashboard lists an announcement and the bell (and email)
 * announces it, and the two used to decide this separately: posting to
 * "Student" with no section picked notified every student in the system,
 * while the dashboard only showed it to the poster's own sections. A team
 * mentioned in a post notified that team even when the post was for experts
 * only. Both now ask this one place.
 */
class AnnouncementAudience
{
    /**
     * Whether the student's dashboard shows the announcement.
     *
     * @param  array<int, int>|null  $teamMemberIds  the mentioned team's members, when the caller already has them
     */
    public static function reachesStudent(Announcement $announcement, Student $student, ?array $teamMemberIds = null): bool
    {
        $audience = (array) ($announcement->target_audience ?: ['all']);

        if (! in_array('all', $audience, true) && ! in_array('student', $audience, true)) {
            return false;
        }

        // A team-targeted announcement is that team's business - it names
        // their defense or their milestone. Members see it whatever their
        // section; nobody else does.
        if ($announcement->team_id) {
            $teamMemberIds ??= self::teamMemberIds((int) $announcement->team_id);

            return in_array($student->student_id, $teamMemberIds, true);
        }

        $label = Student::computeSectionLabel($student->course, $student->year_section);
        if (! $label) {
            return false;
        }

        $label = strtolower($label);

        // "section:all" is the page's own shorthand, sent beside every
        // section it stands for; it names no section of its own.
        $targetSections = collect($audience)
            ->filter(fn ($a) => str_starts_with((string) $a, 'section:'))
            ->map(fn ($a) => strtolower(substr((string) $a, 8)))
            ->reject(fn ($s) => $s === 'all')
            ->values()
            ->all();

        if ($targetSections !== []) {
            return in_array($label, $targetSections, true);
        }

        // No section picked: the poster's own sections, or everyone when the
        // poster covers none (an Admin).
        $posterSections = array_map('strtolower', $announcement->admin?->sections ?? []);

        return $posterSections === [] || in_array($label, $posterSections, true);
    }

    /**
     * Every student the announcement reaches.
     *
     * @return Collection<int, Student>
     */
    public static function students(Announcement $announcement): Collection
    {
        $announcement->loadMissing('admin');

        if ($announcement->team_id) {
            $memberIds = self::teamMemberIds((int) $announcement->team_id);

            return Student::whereIn('student_id', $memberIds)->get()
                ->filter(fn (Student $student) => self::reachesStudent($announcement, $student, $memberIds))
                ->values();
        }

        return Student::whereNotNull('course')->whereNotNull('year_section')->get()
            ->filter(fn (Student $student) => self::reachesStudent($announcement, $student))
            ->values();
    }

    /**
     * @return array<int, int>
     */
    private static function teamMemberIds(int $teamId): array
    {
        return StudentSchoolYear::where('team_id', $teamId)
            ->whereNotNull('student_id')
            ->pluck('student_id')
            ->unique()
            ->values()
            ->all();
    }
}
