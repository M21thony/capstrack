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
