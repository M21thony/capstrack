<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * The dates a defense stage is held within, and how long each defense runs.
 *
 * The defense day is fixed - 8 AM to 5 PM - and is cut into back-to-back
 * slots of duration_minutes, the last of which has to finish by 5. The slots
 * are worked out here and nowhere else, so the scheduler, the pages and the
 * validation cannot disagree about what a valid start time is.
 */
class DefenseWindow extends Model
{
    /** What every defense was before the length could be set: two hours. */
    public const DEFAULT_DURATION = 120;

    /** The defense day. It was always 8 to 5; it is not a setting. */
    public const DAY_START = '08:00';

    public const DAY_END = '17:00';

    /** The shortest and longest a single defense may be set to, in minutes. */
    public const MIN_DURATION = 15;

    public const MAX_DURATION = 240;

    /** Minutes are chosen in steps of this, so slots land on times people say out loud. */
    public const STEP = 5;

    protected $table = 'defense_windows';

    protected $primaryKey = 'defense_window_id';

    public $timestamps = false;

    protected $fillable = [
        'defense_type',
        'start_date',
        'end_date',
        'duration_minutes',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'duration_minutes' => 'integer',
    ];

    /**
     * The window for a stage, or an unsaved one carrying the defaults.
     *
     * Moving a single defense never needed a window to exist, and still does;
     * this is what lets that path ask "what are the slots" without a special
     * case for "no window yet".
     */
    public static function forStage(?string $defenseType): self
    {
        return static::where('defense_type', $defenseType)->first()
            ?? new self(['defense_type' => $defenseType]);
    }

    /** How long each defense in this stage runs, in minutes. */
    public function durationMinutes(): int
    {
        return (int) ($this->duration_minutes ?: self::DEFAULT_DURATION);
    }

    /**
     * Every time a defense in this stage may start, as "H:i".
     *
     * With the default two hours this is 08:00, 10:00, 12:00 and 14:00 - the
     * four blocks the scheduler used to have written into it.
     *
     * @return array<int, string>
     */
    public function slotTimes(): array
    {
        return self::slotsFor($this->durationMinutes());
    }

    /**
     * @return array<int, string>
     */
    public static function slotsFor(int $duration): array
    {
        $end = self::minutesOf(self::DAY_END);
        $at = self::minutesOf(self::DAY_START);
        $slots = [];

        while ($duration > 0 && $at + $duration <= $end) {
            $slots[] = sprintf('%02d:%02d', intdiv($at, 60), $at % 60);
            $at += $duration;
        }

        return $slots;
    }

    /** "H:i" as minutes since midnight. */
    public static function minutesOf(string $time): int
    {
        [$h, $m] = array_map('intval', explode(':', $time) + [1 => 0]);

        return $h * 60 + $m;
    }

    /**
     * What the pages need to know about this window's length.
     *
     * @return array{duration: int, slots: array<int, string>}
     */
    public function settings(): array
    {
        return [
            'duration' => $this->durationMinutes(),
            'slots' => $this->slotTimes(),
        ];
    }
}
