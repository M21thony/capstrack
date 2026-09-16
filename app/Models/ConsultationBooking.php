<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

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
        'cancellation_reason',
        'cancellation_requested_at',
        'cancellation_requested_by',
        'google_event_id',
    ];

    protected function casts(): array
    {
        return [
            'booking_status' => 'string',
            'cancellation_requested_at' => 'datetime',
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

    /**
     * Flip any 'scheduled' booking whose session has already ended (date +
     * the slot's own end_time) to 'done'. Called on read rather than via a
     * cron job — cheap enough for this table's size, and guarantees the
     * status is correct the moment anyone actually looks at it instead of
     * depending on a scheduler running.
     *
     * Filtered in PHP rather than via a DB-side date+time expression (e.g.
     * MySQL's TIMESTAMP()) so this works the same on every driver, including
     * SQLite in tests.
     */
    public static function markPastAsDone(): void
    {
        $now = now();

        $pastIds = DB::table('consultation_booking as b')
            ->leftJoin('consultation_schedule as s', 's.schedule_id', '=', 'b.schedule_id')
            ->where('b.booking_status', 'scheduled')
            ->whereNotNull('b.session_date')
            ->whereDate('b.session_date', '<=', $now->toDateString())
            ->select('b.booking_id', 'b.session_date', 's.end_time')
            ->get()
            ->filter(function ($row) use ($now) {
                // A left join (not inner) - the referenced consultation_schedule
                // row can be missing entirely if it was deleted while bookings
                // still pointed at it. Without an end_time to check against,
                // fall back to "the whole day has passed" so these don't get
                // stuck as 'scheduled' forever, permanently blocking the team
                // from booking anything else.
                if (! $row->end_time) {
                    return \Illuminate\Support\Carbon::parse($row->session_date)->lt($now->copy()->startOfDay());
                }

                return \Illuminate\Support\Carbon::parse($row->session_date)
                    ->setTimeFromTimeString($row->end_time)
                    ->lt($now);
            })
            ->pluck('booking_id');

        if ($pastIds->isNotEmpty()) {
            static::whereIn('booking_id', $pastIds)->update(['booking_status' => 'done']);
        }
    }
}
