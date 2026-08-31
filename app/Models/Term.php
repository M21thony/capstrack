<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A semester of capstone: which school year, which semester, and whether it
 * is Capstone 1 or 2.
 *
 * Exactly one term is active, declared rather than inferred - a school knows
 * when its semester starts, and guessing from defense window dates falls
 * apart the moment two overlap or one is left unset.
 *
 * A team can appear in more than one term. A team that does not make it
 * through Capstone 1 retakes it, keeping the same members if they want to,
 * so their membership is recorded per term on student_s_y rather than once
 * and for all on the team.
 */
class Term extends Model
{
    protected $table = 'terms';

    protected $primaryKey = 'term_id';

    protected $fillable = [
        'school_year',
        'semester',
        'capstone_stage',
        'is_active',
    ];

    protected $casts = [
        'semester' => 'integer',
        'capstone_stage' => 'integer',
        'is_active' => 'boolean',
    ];

    public function schoolYears()
    {
        return $this->hasMany(StudentSchoolYear::class, 'term_id', 'term_id');
    }

    /**
     * The term everything new belongs to.
     *
     * Null when nobody has set one up yet, which callers have to cope with
     * rather than assume - a fresh install has no terms at all.
     */
    public static function active(): ?self
    {
        return static::where('is_active', true)->first();
    }

    /**
     * Memoised, because StudentSchoolYear's global scope asks for this on
     * every query it makes - without the memo that is one extra SELECT per
     * lookup, on a table that changes once a semester.
     *
     * The try/catch is for the case where the table is not there yet: the
     * scope runs during migrations and in tests that build their own schema,
     * and a missing terms table means "no term", not a crash.
     */
    private static ?int $activeIdMemo = null;

    private static bool $activeIdResolved = false;

    public static function activeId(): ?int
    {
        if (self::$activeIdResolved) {
            return self::$activeIdMemo;
        }

        try {
            self::$activeIdMemo = static::where('is_active', true)->value('term_id');
        } catch (\Throwable) {
            self::$activeIdMemo = null;
        }

        self::$activeIdResolved = true;

        return self::$activeIdMemo;
    }

    /** Drop the memo - after activating a term, or between tests. */
    public static function forgetActive(): void
    {
        self::$activeIdMemo = null;
        self::$activeIdResolved = false;
    }

    /** Only one term runs at a time, so activating one stands the rest down. */
    public function activate(): void
    {
        static::where('term_id', '!=', $this->term_id)->update(['is_active' => false]);
        $this->update(['is_active' => true]);
        self::forgetActive();
    }

    public function label(): string
    {
        return $this->school_year.' · '.$this->semesterLabel().' · Capstone '.$this->capstone_stage;
    }

    public function semesterLabel(): string
    {
        return match ($this->semester) {
            1 => '1st sem',
            2 => '2nd sem',
            default => 'Sem '.$this->semester,
        };
    }

    /** The Capstone 1 term whose students carry on into this one. */
    public function previousStage(): ?self
    {
        if ($this->capstone_stage <= 1) {
            return null;
        }

        return static::where('school_year', $this->school_year)
            ->where('capstone_stage', $this->capstone_stage - 1)
            ->first();
    }
}
