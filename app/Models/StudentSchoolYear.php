<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentSchoolYear extends Model
{
    protected $table = 'student_s_y';

    protected $primaryKey = 's_y_id';

    public $timestamps = false;

    protected $fillable = [
        'student_id',
        'term_id',
        'team_id',
        's_role_id',
        'school_year',
        'program',
        'student_status',
    ];

    /**
     * Make sure this student has an enrolment row, carrying the school year.
     *
     * The roster row is a record of what was uploaded or typed; this is what
     * the app actually reads - the classmate picker, every section lookup,
     * the team they join, and the duplication check, which cannot save a
     * result without one.
     *
     * On the model because both ways of adding a student need it and only
     * one of them did it: the spreadsheet upload wrote the row, adding
     * somebody one at a time did not, and that student signed in fine and
     * was then told their enrolment record was missing.
     */
    public static function recordFor(int $studentId, string $schoolYear, ?string $program = null): self
    {
        $row = static::where('student_id', $studentId)->first();

        if ($row) {
            // program only when it is missing: the caller's word (a roster
            // cell, an admin's form) fills a gap, it does not overwrite what
            // the student's own profile already says.
            $row->update(['school_year' => $schoolYear] + ($program && $row->program === null ? ['program' => $program] : []));

            return $row;
        }

        return static::create([
            'student_id' => $studentId,
            'school_year' => $schoolYear,
            'program' => $program,
        ]);
    }

    /**
     * Fill program in from each student's course, where it is missing.
     *
     * The writers that predate the program column left it null, and a team
     * whose enrolment rows carry no program counts for no program at all -
     * it read as zero-filled on the expert's capacity bars and stayed
     * open to apply to past a cap already met. The students.course column
     * holds the same program as a full name or a code, either of which
     * maps.
     *
     * @return int How many rows gained a program.
     */
    public static function backfillPrograms(): int
    {
        $fixed = 0;

        static::query()
            ->withoutGlobalScope(self::ACTIVE_TERM_SCOPE)
            ->whereNull('program')
            ->orderBy('s_y_id')
            ->each(function (self $row) use (&$fixed) {
                $program = Student::mapCourseToProgram((string) ($row->student?->course ?? ''));

                if ($program) {
                    $row->update(['program' => $program]);
                    $fixed++;
                }
            });

        return $fixed;
    }

    /**
     * Rows are scoped to the term that is running, everywhere, by default.
     *
     * This table is one row per student PER TERM - it is named "student
     * school year" and carries school_year and program for exactly that -
     * but every writer used to upsert on student_id alone, so it held one row
     * per student forever and the ~50 places that ask for "this student's
     * row" could assume there was only one.
     *
     * Now that a student can have a row per term (a team that does not pass
     * Capstone 1 retakes it, often with the same members), those places would
     * silently pick whichever row came back first. Scoping here means they
     * all keep meaning "this term" without each having to say so, and history
     * is reached deliberately with withoutGlobalScope(self::ACTIVE_TERM_SCOPE).
     *
     * Rows with no term are included on purpose: data written before this
     * existed, or by a raw insert that does not know about terms, must not
     * vanish from the app.
     */
    public const ACTIVE_TERM_SCOPE = 'active_term';

    protected static function booted(): void
    {
        static::addGlobalScope(self::ACTIVE_TERM_SCOPE, function ($query) {
            $activeTermId = Term::activeId();

            // No term set up yet (a fresh install) means nothing to scope by.
            if (! $activeTermId) {
                return;
            }

            $query->where(function ($q) use ($activeTermId) {
                $q->where('student_s_y.term_id', $activeTermId)
                    ->orWhereNull('student_s_y.term_id');
            });
        });

        static::creating(function (self $row) {
            $row->term_id ??= Term::activeId();

            // The table is named for this column, and plenty of readers
            // filter on it - the classmate picker among them - yet most
            // writers never set it, so rows arrived null and matched
            // nothing. A row belongs to the term it was written in, so it
            // takes that term's year, or the current one when it has no term.
            $row->school_year ??= $row->term_id
                ? (Term::find($row->term_id)?->school_year ?? Term::currentSchoolYear())
                : Term::currentSchoolYear();

            // Same story for the program short code (BSIT, BSCS...): the
            // per-program matching and the expert capacity counts group on
            // it, yet nothing wrote it, so teams counted for no program at
            // all. The student's course knows it in either shape ("BSIT" or
            // the full Bachelor of ... name) - derive it the same way the
            // profile save does.
            $row->program ??= $row->student_id
                ? Student::mapCourseToProgram((string) (Student::find($row->student_id)?->course ?? ''))
                : null;
        });
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class, 'term_id', 'term_id');
    }

    /** Every term this student has been enrolled for, newest first. */
    public function scopeAcrossAllTerms($query)
    {
        return $query->withoutGlobalScope(self::ACTIVE_TERM_SCOPE);
    }

    public function scopeForTerm($query, int $termId)
    {
        return $query->withoutGlobalScope(self::ACTIVE_TERM_SCOPE)->where('term_id', $termId);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id', 'student_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'team_id', 'team_id');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(StudentRole::class, 's_role_id', 's_role_id');
    }
}
