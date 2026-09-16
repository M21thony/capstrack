<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * An expert's appointment duration, general weekly hours, and scheduling
 * window - see the consultation_availability migration.
 */
class ConsultationAvailability extends Model
{
    protected $table = 'consultation_availability';

    /** The lengths offered as one tap; any other length is typed in. */
    public const DURATIONS = [15, 30, 45, 60, 90, 120];

    public const DURATION_MIN = 5;

    public const DURATION_MAX = 480;

    public const DAYS = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

    protected $fillable = [
        'expert_id',
        'duration_minutes',
        'buffer_minutes',
        'max_per_day',
        'hours',
        'starts_on',
        'ends_on',
        'max_days_ahead',
        'min_notice_hours',
    ];

    protected function casts(): array
    {
        return [
            'hours' => 'array',
            'starts_on' => 'date:Y-m-d',
            'ends_on' => 'date:Y-m-d',
            'duration_minutes' => 'integer',
            'buffer_minutes' => 'integer',
            'max_per_day' => 'integer',
            'max_days_ahead' => 'integer',
            'min_notice_hours' => 'integer',
        ];
    }

    /** What an expert who has never opened the dialog gets. */
    public static function defaultsFor(int $expertId): self
    {
        return new self([
            'expert_id' => $expertId,
            'duration_minutes' => 60,
            'buffer_minutes' => 0,
            'max_per_day' => null,
            'hours' => [],
            'max_days_ahead' => 60,
            'min_notice_hours' => 24,
        ]);
    }

    /**
     * The first moment a team may book, and the last day they may book on
     * (null when there is no end).
     *
     * @return array{0: Carbon, 1: ?Carbon}
     */
    public function window(?Carbon $now = null): array
    {
        $now ??= now();

        $earliest = $now->copy()->addHours((int) $this->min_notice_hours);
        if ($this->starts_on && $this->starts_on->copy()->startOfDay()->gt($earliest)) {
            $earliest = $this->starts_on->copy()->startOfDay();
        }

        $latest = $this->max_days_ahead ? $now->copy()->startOfDay()->addDays((int) $this->max_days_ahead) : null;
        if ($this->ends_on && (! $latest || $this->ends_on->lt($latest))) {
            $latest = $this->ends_on->copy()->startOfDay();
        }

        return [$earliest, $latest];
    }

    /** Whether a session on $date starting at $startTime falls inside the window. */
    public function allows(string $date, string $startTime, ?Carbon $now = null): bool
    {
        [$earliest, $latest] = $this->window($now);
        $startsAt = Carbon::parse($date.' '.substr($startTime, 0, 5));

        return $startsAt->gte($earliest)
            && (! $latest || $startsAt->copy()->startOfDay()->lte($latest));
    }

    /** The window as the student calendar reads it. */
    public function windowForClient(): array
    {
        [$earliest, $latest] = $this->window();

        return [
            'earliest' => $earliest->format('Y-m-d H:i'),
            'latest' => $latest?->format('Y-m-d'),
        ];
    }
}
