<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConsultationSchedule extends Model
{
    protected $table = 'consultation_schedule';

    protected $primaryKey = 'schedule_id';

    protected $fillable = [
        'expert_id',
        'available_date',
        'specific_date',
        'start_time',
        'end_time',
        'consultation_limit',
        'is_available',
        'google_event_id',
    ];

    protected $casts = [
        'is_available' => 'boolean',
        'specific_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
