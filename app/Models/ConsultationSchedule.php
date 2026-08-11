<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class ConsultationSchedule extends Model
{
    protected $table = 'consultation_schedule';

    protected $primaryKey = 'schedule_id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = true;

    protected $fillable = [
        'expert_id',
        'available_date',
        'start_time',
        'end_time',
        'consultation_limit',
        'is_available',
        'google_event_id',
        'recur',
        'specific_date',
        'slot_type',
    ];

    protected function casts(): array
    {
        return [
            'is_available' => 'boolean',
            // specific_date and start_time deliberately stay raw strings, not
            // Carbon casts: they get JSON-serialized wholesale to the student
            // consultation calendar's JS (@json($availableSlots)), which reads
            // them as plain "HH:MM:SS"/"YYYY-MM-DD" strings. A Carbon cast
            // serializes as a full timezone-shifted ISO datetime instead,
            // silently corrupting both the displayed time and the date-key
            // matching that calendar depends on. Consumers that want a Carbon
            // instance should wrap with Carbon::parse() locally instead.
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(ConsultationBooking::class, 'schedule_id', 'schedule_id');
    }

    public function expert(): BelongsTo
    {
        return $this->belongsTo(CapstoneExpert::class, 'expert_id', 'expert_id');
    }

    /**
     * The real calendar date this slot actually refers to: for a "once" slot
     * that's just specific_date; for weekly/biweekly it's the next occurrence
     * of available_date's weekday on or after $reference. Centralizes the
     * weekday-name-to-date logic so it isn't recomputed differently in
     * different places (that mismatch is what caused slots and bookings to
     * silently show the wrong date).
     */
    public function nextOccurrenceOnOrAfter(?Carbon $reference = null): ?string
    {
        if ($this->recur === 'once') {
            return $this->specific_date;
        }

        $days = ['Sunday' => 0, 'Monday' => 1, 'Tuesday' => 2, 'Wednesday' => 3, 'Thursday' => 4, 'Friday' => 5, 'Saturday' => 6];

        if (! isset($days[$this->available_date])) {
            return null;
        }

        $reference ??= now();
        $diff = ($days[$this->available_date] - $reference->dayOfWeek + 7) % 7;

        return $reference->copy()->addDays($diff)->toDateString();
    }

    /**
     * Whether $date is actually a valid occurrence of this slot: for a
     * "once" slot that's just an exact match to specific_date; for weekly/
     * biweekly it's any on-or-after-today date that falls on the matching
     * weekday. Used to validate the date a student actually picked in the
     * calendar, instead of trusting the client or silently defaulting to
     * "whichever occurrence is nearest today" regardless of what was clicked.
     */
    public function isValidOccurrenceDate(string $date): bool
    {
        if ($this->recur === 'once') {
            // The slot's own is_available already governs whether it's
            // still bookable - specific_date is its one and only occurrence
            // regardless of how it relates to "today".
            return $this->specific_date === $date;
        }

        $parsed = Carbon::parse($date)->startOfDay();

        return ! $parsed->lt(now()->startOfDay())
            && $parsed->format('l') === $this->available_date;
    }

    /**
     * Human-readable recurrence, e.g. "Every Monday", "Every other Friday",
     * or "August 14, 2026" for a one-off slot. Used in notification emails
     * so students see how often a slot repeats, not just its next date.
     */
    public function recurrenceDescription(): string
    {
        return match ($this->recur) {
            'weekly' => "Every {$this->available_date}",
            'biweekly' => "Every other {$this->available_date}",
            default => $this->specific_date
                ? Carbon::parse($this->specific_date)->format('F j, Y')
                : $this->available_date,
        };
    }
}
