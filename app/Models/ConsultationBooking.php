<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
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
        'team_id',
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

    /**
     * The team the consultation was booked for - not the team the student
     * who clicked Book happens to be in now. Asking through the student sent
     * a moved student's old consultations along with them to their new team.
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'team_id', 'team_id');
    }

    /**
     * The bookings a team sees on its Consultation page: only the ones booked
     * for it. A booking whose team could not be worked out (see
     * fillMissingTeams), or whose team no longer exists, is shown to no
     * student at all.
     *
     * It used to be shown to the student who made it, wherever they are now -
     * so a student who left their team still found their old team's session
     * with that team's adviser on their new team's page, before the new team
     * had any expert.
     */
    public function scopeForTeam(Builder $query, ?int $teamId): Builder
    {
        return $teamId ? $query->where('team_id', $teamId) : $query->whereRaw('1 = 0');
    }

    /**
     * Call off every consultation a team still had coming up, when the team
     * itself is gone. Nobody is left to attend, and the expert would
     * otherwise keep the time free for it with no way to let it go.
     *
     * @return Collection<int, ConsultationBooking> the bookings cancelled
     */
    public static function cancelForRemovedTeam(int $teamId): Collection
    {
        $bookings = static::where('team_id', $teamId)->where('booking_status', 'scheduled')->get();

        foreach ($bookings as $booking) {
            $booking->forceFill([
                'booking_status' => 'cancelled',
                'cancellation_requested_at' => null,
                'cancellation_reason' => self::TEAM_REMOVED,
            ])->saveQuietly();

            ConsultationSchedule::where('schedule_id', $booking->schedule_id)->update(['is_available' => true]);
        }

        return $bookings;
    }

    public const TEAM_REMOVED = 'The team no longer exists.';

    /**
     * Give every booking without a team the one it was made for.
     *
     * Bookings used to record only the student. Where it can be known, the
     * team comes from the "Consultation booked" notice the expert was sent at
     * the time, which names the team. Failing that, the student's team now -
     * but only when that team actually has the booking's expert, since
     * otherwise the student has moved on and the booking belongs to the team
     * they left. Failing that, the expert's only team, if they have one.
     * Anything still unknown is left empty rather than guessed, and no
     * student sees it.
     */
    public static function fillMissingTeams(): void
    {
        $missing = static::whereNull('team_id')->with('schedule')->get();

        if ($missing->isEmpty()) {
            return;
        }

        $noticed = Notification::where('type', 'consultation_booked')
            ->whereNotNull('data')
            ->get(['data'])
            ->mapWithKeys(fn ($n) => isset($n->data['booking_id'], $n->data['team_id'])
                ? [(int) $n->data['booking_id'] => (int) $n->data['team_id']]
                : [])
            ->all();

        foreach ($missing as $booking) {
            $teamId = $noticed[$booking->booking_id] ?? null;

            if (! $teamId && $booking->schedule) {
                $current = StudentSchoolYear::where('student_id', $booking->student_id)
                    ->orderByDesc('school_year')
                    ->value('team_id');

                $expertIsTheirs = $current && DB::table('capstone_expert_application')
                    ->where('team_id', $current)
                    ->where('expert_id', $booking->schedule->expert_id)
                    ->where('status', 'approved')
                    ->exists();

                $teamId = $expertIsTheirs ? $current : null;
            }

            // The one team the expert serves, when there is only one and it
            // already existed when the booking was made - the booking can
            // have been for no other. Covers an expert who had their
            // "Consultation booked" notice turned off.
            if (! $teamId && $booking->schedule) {
                $served = DB::table('capstone_expert_application as a')
                    ->join('team as t', 't.team_id', '=', 'a.team_id')
                    ->where('a.expert_id', $booking->schedule->expert_id)
                    ->where('a.status', 'approved')
                    ->distinct()
                    ->get(['t.team_id', 't.created_at']);

                $only = $served->count() === 1 ? $served->first() : null;
                $existedThen = $only && (! $only->created_at || ! $booking->created_at || $booking->created_at->gte($only->created_at));

                $teamId = $existedThen ? (int) $only->team_id : null;
            }

            if ($teamId && Team::whereKey($teamId)->exists()) {
                $booking->forceFill(['team_id' => $teamId])->saveQuietly();
            }
        }
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
                    return Carbon::parse($row->session_date)->lt($now->copy()->startOfDay());
                }

                return Carbon::parse($row->session_date)
                    ->setTimeFromTimeString($row->end_time)
                    ->lt($now);
            })
            ->pluck('booking_id');

        if ($pastIds->isNotEmpty()) {
            static::whereIn('booking_id', $pastIds)->update(['booking_status' => 'done']);
        }
    }
}
