<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsultationBooking extends Model
{
    protected $table = 'consultation_booking';

    protected $primaryKey = 'booking_id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = true;

    protected $fillable = [
        'student_id',
        'schedule_id',
        'session_date',
        'booking_status',
    ];

    protected function casts(): array
    {
        return [
            'booking_status' => 'string',
            'session_date' => 'date',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id', 'student_id');
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(ConsultationSchedule::class, 'schedule_id', 'schedule_id');
    }

    public function team()
    {
        return $this->hasOneThrough(
            Team::class,
            StudentSchoolYear::class,
            'student_id',
            'team_id',
            'student_id',
            'team_id'
        );
    }
}
