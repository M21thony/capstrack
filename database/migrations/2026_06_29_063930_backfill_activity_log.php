<?php

use App\Models\ActivityLog;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Models\TeamInvitation;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Backfill team creation activities
        $membersByTeam = StudentSchoolYear::whereNotNull('team_id')
            ->get()
            ->groupBy('team_id');

        foreach ($membersByTeam as $teamId => $members) {
            $team = Team::find($teamId);
            if (! $team || ! $team->created_at) {
                continue;
            }

            $firstMember = $members->first();
            if (! $firstMember) {
                continue;
            }

            $alreadyLogged = ActivityLog::where('student_id', $firstMember->student_id)
                ->where('type', 'team_created')
                ->where('created_at', $team->created_at)
                ->exists();

            if (! $alreadyLogged) {
                // Log for team creator (first member)
                ActivityLog::create([
                    'student_id' => $firstMember->student_id,
                    'type' => 'team_created',
                    'description' => 'Created team <strong>'.e($team->team_name).'</strong>',
                    'metadata' => json_encode(['team_id' => $teamId, 'team_name' => $team->team_name]),
                    'created_at' => $team->created_at,
                ]);
            }
        }

        // Backfill accepted invitation activities
        $acceptedInvites = TeamInvitation::where('status', 'accepted')->get();
        foreach ($acceptedInvites as $invite) {
            $team = Team::find($invite->team_id);
            if (! $team) {
                continue;
            }

            $joinedStudentId = $invite->type === 'invite' ? $invite->receiver_id : $invite->sender_id;
            $time = $invite->updated_at ?? $invite->created_at;
            if (! $time) {
                continue;
            }

            $alreadyLogged = ActivityLog::where('student_id', $joinedStudentId)
                ->where('type', 'team_joined')
                ->where('created_at', $time)
                ->exists();

            if (! $alreadyLogged) {
                ActivityLog::create([
                    'student_id' => $joinedStudentId,
                    'type' => 'team_joined',
                    'description' => 'Joined team <strong>'.e($team->team_name).'</strong>',
                    'metadata' => json_encode(['team_id' => $team->team_id, 'team_name' => $team->team_name]),
                    'created_at' => $time,
                ]);
            }
        }
    }

    public function down(): void
    {
        ActivityLog::truncate();
    }
};
