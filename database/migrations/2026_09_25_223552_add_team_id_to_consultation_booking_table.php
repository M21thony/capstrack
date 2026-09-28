<?php

use App\Models\ConsultationBooking;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A booking remembers the team it was made for.
 *
 * It used to know only the student who clicked Book, and every page worked
 * the team out from where that student is now. So a student who moved to
 * another team took their old team's consultations with them: the new team
 * saw them (and could ask to cancel them) even with no expert of its own,
 * and the old team lost them.
 *
 * Existing bookings are filled in by ConsultationBooking::fillMissingTeams().
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('consultation_booking', 'team_id')) {
            Schema::table('consultation_booking', function (Blueprint $table) {
                $table->unsignedBigInteger('team_id')->nullable()->after('student_id');
                $table->index('team_id');
            });
        }

        ConsultationBooking::fillMissingTeams();
    }

    public function down(): void
    {
        Schema::table('consultation_booking', function (Blueprint $table) {
            $table->dropIndex(['team_id']);
            $table->dropColumn('team_id');
        });
    }
};
