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
}
