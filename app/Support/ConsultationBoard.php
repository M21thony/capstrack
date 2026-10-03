<?php

namespace App\Support;

use App\Models\CapstoneExpert;
use App\Models\ConsultationAvailability;
use App\Models\ConsultationBooking;
use App\Models\ConsultationSchedule;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * What a student's team can book and has booked, for the Consultations tab of
 * the Experts page and its 30-second refresh.
 *
 * Lived inside StudentConsultationController while Consultation was a page of
 * its own. The Experts page draws the same calendar now, so both read it here.
 */
class ConsultationBoard
{
    /**
     * @return array{
     *     experts: Collection<int, object>,
     *     defaultExpertId: int|null,
     *     availableSlots: Collection<int, ConsultationSchedule>,
     *     upcomingBookings: Collection<int, ConsultationBooking>,
     *     recentBookings: Collection<int, ConsultationBooking>,
     *     teamId: int|null,
     *     bookedExpertIds: Collection<int, int>,
     *     bookingWindows: Collection<int, array{earliest: string, latest: string|null}>
     * }
     */
    public function forStudent(Student $student): array
    {
        ConsultationBooking::markPastAsDone();

        $studentYear = StudentSchoolYear::where('student_id', $student->student_id)
            ->orderByDesc('school_year')
            ->first();

        $teamId = $studentYear?->team_id;

        // Only experts actually assigned to this student's own team — via an
        // accepted capstone_expert_application row for their team_id — not
        // every approved expert in the system (which was pulling in admins/
        // instructors with no relationship to this student at all).
        $experts = collect();
        if ($teamId) {
            // An expert can hold more than one of these roles at once (e.g.
            // both Adviser and Consultant for the same team) - the join
            // below still returns one row per (expert, role) pair, so
            // they're grouped back into a single entry per expert with a
            // combined role label.
            $rows = DB::table('capstone_expert_application as a')
                ->join('capstone_experts as e', 'e.expert_id', '=', 'a.expert_id')
                // Through the application, not the expert. Joining on
                // er.expert_id asks "what roles does this person hold
                // anywhere", so an expert who is an adviser to one team and
                // a consultant to another showed up to THIS team as both.
                // a.expert_role_id is the role they were engaged as here.
                ->join('expert_roles as er', 'er.expert_role_id', '=', 'a.expert_role_id')
                ->join('roles as r', 'r.role_id', '=', 'er.role_id')
                ->where('a.team_id', $teamId)
                ->where('a.status', 'approved')
                ->where('e.approved_status', true)
                ->whereRaw('LOWER(r.role_name) IN (?, ?, ?)', ['adviser', 'consultant', 'grammarian'])
                ->select('e.expert_id', 'e.first_name', 'e.last_name', 'e.profile_picture', 'r.role_name')
                ->distinct()
                ->get();

            // Adviser, consultant, grammarian - the order the department
            // names them in, used for both the list and the label on a
            // person holding more than one.
            $rank = ['adviser' => 0, 'consultant' => 1, 'grammarian' => 2];
            $rankOf = fn ($role) => $rank[strtolower((string) $role)] ?? 99;

            $experts = $rows
                ->groupBy('expert_id')
                ->map(function ($group) use ($rankOf) {
                    $first = $group->first();
                    $roles = $group->pluck('role_name')->map(fn ($r) => strtolower((string) $r))->unique()->sortBy($rankOf)->values();
                    // The model decides what counts as a usable photo - a
                    // URL, or a preset that exists on disk - so ask it.
                    $photo = new CapstoneExpert([
                        'profile_picture' => $first->profile_picture,
                        'first_name' => $first->first_name,
                        'last_name' => $first->last_name,
                    ]);

                    return (object) [
                        'expert_id' => $first->expert_id,
                        'first_name' => $first->first_name,
                        'last_name' => $first->last_name,
                        'name' => trim($first->first_name.' '.$first->last_name),
                        'initials' => $photo->avatarInitials(),
                        'avatar' => $photo->hasAvatarImage() ? $photo->avatarUrl() : null,
                        'roles' => $roles->all(),
                        'role_name' => $roles->map('ucfirst')->implode(', '),
                        'is_adviser' => $roles->contains('adviser'),
                        // Their most senior role, which is where they sit.
                        'role_rank' => $rankOf($roles->first()),
                    ];
                })
                ->sortBy([
                    fn ($a, $b) => $a->role_rank <=> $b->role_rank,
                    fn ($a, $b) => strcmp($a->last_name, $b->last_name),
                ])
                ->values();
        }

        $defaultExpertId = optional(
            $experts->first(fn ($e) => $e->is_adviser)
        )->expert_id;

        // Both available AND booked slots — the calendar needs booked ones
        // too (it checks each slot's own is_available) to show a day as
        // fully booked rather than as a day the expert never offered.
        $availableSlots = ConsultationSchedule::whereIn('expert_id', $experts->pluck('expert_id'))
            ->orderBy('available_date')
            ->orderBy('start_time')
            ->get();

        // Team-wide, not just this student's own bookings - a consultation
        // is booked once per team (whoever happened to click "Book"), so
        // every teammate needs to see it in their own lists too. By the team
        // it was booked for, not by who is in the team now: a student who
        // joins brings no consultations with them, and the team they left
        // keeps theirs.
        $myBookings = ConsultationBooking::forTeam($teamId)
            ->whereIn('booking_status', ['scheduled', 'done'])
            ->with(['schedule.expert', 'team'])
            ->get();

        $today = now()->startOfDay();

        // Over once its end time has passed, not only from the next day. A
        // booking with no session_date (older rows) stays upcoming rather
        // than silently dropping from both lists.
        $ended = fn ($b) => $b->session_date
            && $b->session_date->copy()->setTimeFromTimeString($b->schedule?->end_time ?: '23:59:59')->isPast();

        $upcomingBookings = $myBookings
            ->reject($ended)
            ->sortBy(fn ($b) => $b->session_date ?? $today)
            ->values();

        $recentBookings = $myBookings
            ->filter($ended)
            ->sortByDesc(fn ($b) => $b->session_date)
            ->values();

        // Which experts this team already has a session waiting with. One
        // live booking per expert, not one per team: a team due to see their
        // adviser on Thursday can still book their consultant for Friday.
        $bookedExpertIds = $teamId
            ? ConsultationBooking::where('booking_status', 'scheduled')
                ->where('team_id', $teamId)
                ->with('schedule')
                ->get()
                ->pluck('schedule.expert_id')
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
            : collect();

        // Each expert's scheduling window, so the calendar only offers times
        // a booking would be accepted for.
        $bookingWindows = ConsultationAvailability::whereIn('expert_id', $experts->pluck('expert_id'))
            ->get()
            ->mapWithKeys(fn ($a) => [$a->expert_id => $a->windowForClient()]);

        return compact('experts', 'defaultExpertId', 'availableSlots', 'upcomingBookings', 'recentBookings', 'teamId', 'bookedExpertIds', 'bookingWindows');
    }

    /**
     * One booking as the page draws it.
     *
     * @return array{booking_id: int, expert_id: int|null, expert_name: string, slot_type: string, session_date: string|null, available_date: string|null, start_time: string|null, end_time: string|null, team_name: string, cancel_requested: bool}
     */
    public function cardData(ConsultationBooking $booking): array
    {
        return [
            'booking_id' => $booking->booking_id,
            'expert_id' => $booking->schedule?->expert_id ? (int) $booking->schedule->expert_id : null,
            'expert_name' => trim(($booking->schedule?->expert?->first_name ?? 'Expert').' '.($booking->schedule?->expert?->last_name ?? '')),
            'slot_type' => $booking->schedule?->slot_type ?? 'consult',
            'session_date' => $booking->session_date?->format('Y-m-d'),
            'available_date' => $booking->schedule?->available_date,
            'start_time' => $booking->schedule?->start_time,
            'end_time' => $booking->schedule?->end_time,
            'team_name' => $booking->team->team_name ?? 'N/A',
            'cancel_requested' => $booking->cancellation_requested_at !== null,
        ];
    }

    /**
     * Which (schedule_id, session_date) pairs already have as many scheduled
     * bookings as their consultation_limit allows. Used instead of the
     * schedule row's own is_available for recurring slots, since that column
     * is shared across every future occurrence of the same weekday.
     *
     * @param  Collection<int, ConsultationSchedule>  $slots
     * @return list<string> "schedule_id|YYYY-MM-DD"
     */
    public function fullOccurrences(Collection $slots): array
    {
        $scheduleIds = $slots->pluck('schedule_id');
        if ($scheduleIds->isEmpty()) {
            return [];
        }

        $limitsBySchedule = $slots->pluck('consultation_limit', 'schedule_id');

        return ConsultationBooking::whereIn('schedule_id', $scheduleIds)
            ->where('booking_status', 'scheduled')
            ->get()
            ->groupBy(fn ($b) => $b->schedule_id.'|'.optional($b->session_date)->format('Y-m-d'))
            ->filter(function ($bookings, $key) use ($limitsBySchedule) {
                [$scheduleId] = explode('|', $key);
                $limit = max(1, (int) ($limitsBySchedule[$scheduleId] ?? 1));

                return $bookings->count() >= $limit;
            })
            ->keys()
            ->values()
            ->all();
    }
}
