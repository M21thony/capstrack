<?php

use App\Models\StudentSchoolYear;
use Illuminate\Database\Migrations\Migration;

/**
 * Put a program in every student_s_y row that has none.
 *
 * The team-forming write built its enrolment row with a raw upsert that
 * bypassed the model, and the profile save only fills it on submission - so
 * rows landed with program null, and a team whose enrolment rows name no
 * program counted for no program at all: the expert's capacity bars read
 * zero-filled with two teams already approved, and the apply check let
 * students pile onto a cap that was already met.
 *
 * students.course holds the same program as a full name ("Bachelor of
 * Science in Information Technology") or a code ("BSIT"); either maps.
 * New rows are stamped at creation from here on, so this is only the
 * cleanup of what already exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        StudentSchoolYear::backfillPrograms();
    }

    public function down(): void
    {
        // Deliberately not reversed: the values were derived from data that
        // is still there, and nulling them would reintroduce the bug.
    }
};
