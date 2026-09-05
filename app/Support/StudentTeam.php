<?php

namespace App\Support;

use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;

/**
 * Which team a student is in.
 *
 * student_s_y holds one row per school year, so a returning student has
 * several. Most of the app asked this question as
 *
 *     StudentSchoolYear::where('student_id', $id)->first()->team_id
 *
 * which takes an arbitrary row - in practice the oldest - and reads the team
 * off that. A student whose earliest enrolment predates their current team
 * therefore comes back teamless, and the pages that ask this decide from it
 * whether you may see the dashboard, apply for an expert, or open the
 * Pre-Proposal form. Four call sites already guarded it with
 * whereNotNull('team_id'); thirteen did not.
 *
 * One copy of the rule, for the same reason SectionScope and ActivityScope
 * are one copy: the same question answered two ways is how the wrong answer
 * survives being fixed.
 *
 * This is deliberately NOT the way to find a student's enrolment row for its
 * own sake - the school year, the program, the row to write a school year
 * onto. Those want the row whether or not it carries a team, and asking for
 * one with a team would hide a student who has none. Use StudentSchoolYear
 * directly for that.
 */
class StudentTeam
{
    /**
     * The enrolment row carrying this student's team, or null when they are
     * in no team.
     *
     * The newest such row wins: a student who was in a team last year and is
     * in another this year belongs to this year's.
     */
    public static function rowFor(Student|int|null $student): ?StudentSchoolYear
    {
        $studentId = $student instanceof Student ? $student->student_id : $student;

        if (! $studentId) {
            return null;
        }

        return StudentSchoolYear::where('student_id', $studentId)
            ->whereNotNull('team_id')
            ->orderByDesc('school_year')
            ->orderByDesc('s_y_id')
            ->first();
    }

    /** The id of the team this student is in, or null. */
    public static function idFor(Student|int|null $student): ?int
    {
        $teamId = self::rowFor($student)?->team_id;

        return $teamId === null ? null : (int) $teamId;
    }

    /** The team this student is in, or null. */
    public static function for(Student|int|null $student): ?Team
    {
        $teamId = self::idFor($student);

        return $teamId === null ? null : Team::find($teamId);
    }

    /** Whether this student is in a team at all. */
    public static function has(Student|int|null $student): bool
    {
        return self::idFor($student) !== null;
    }
}
