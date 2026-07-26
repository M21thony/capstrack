<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
}
