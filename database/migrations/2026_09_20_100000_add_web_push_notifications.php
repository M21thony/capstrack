<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Notifications an expert can receive, and the browsers to push them to.
 *
 * The notifications table was built student-only: student_id was required, so
 * there was no row shape that could mean "this is for an adviser". Everything
 * an expert was ever told went out by email, which is the complaint this
 * feature exists to answer.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Every table here is MyISAM, so there is no transaction around any
        // of this: a migration that fails halfway stays half applied. Each
        // step asks whether it has already been done.
        if (! Schema::hasColumn('notifications', 'expert_id')) {
            Schema::table('notifications', function (Blueprint $table) {
                // Exactly one of these two is set. Which one it is says
                // whether the row belongs to a team member or to an expert.
                $table->unsignedBigInteger('student_id')->nullable()->change();
                $table->unsignedBigInteger('expert_id')->nullable()->after('student_id');

                $table->index(['expert_id', 'is_read']);
            });
        }

        // Per-person switches, one JSON blob rather than a row per type: it is
        // only ever read as a whole, for the one person logged in.
        // Null means "never touched the settings", which is everything on.
        foreach (['capstone_experts', 'students'] as $table) {
            if (Schema::hasColumn($table, 'notification_prefs')) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) {
                $t->text('notification_prefs')->nullable();
            });
        }

        if (! Schema::hasTable('push_subscriptions')) {
            Schema::create('push_subscriptions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('student_id')->nullable();
                $table->unsignedBigInteger('expert_id')->nullable();

                // One row per browser per person. The endpoint is the browser's
                // own address at Google/Mozilla and is far too long to index on
                // MyISAM, so uniqueness is enforced on a hash of it.
                $table->text('endpoint');
                $table->char('endpoint_hash', 64)->unique();
                $table->string('p256dh', 255);
                $table->string('auth', 255);
                $table->timestamp('created_at')->useCurrent();

                $table->index(['student_id']);
                $table->index(['expert_id']);
            });
        }

        $this->backfillPendingApplications();
    }

    /**
     * An expert opening the bell for the first time should not find it empty
     * while applications sit waiting on their Applications page. The old bell
     * worked these out live on every page load; now that they are stored, the
     * ones already waiting have to be written down once.
     */
    private function backfillPendingApplications(): void
    {
        // Re-running must not double up on an expert's bell.
        if (DB::table('notifications')->where('type', 'expert_application')->exists()) {
            return;
        }

        $pending = DB::table('capstone_expert_application as a')
            ->join('team as t', 't.team_id', '=', 'a.team_id')
            ->leftJoin('expert_roles as er', 'er.expert_role_id', '=', 'a.expert_role_id')
            ->leftJoin('roles as r', 'r.role_id', '=', 'er.role_id')
            ->where('a.status', 'pending')
            ->select('a.expert_id', 't.team_name', 'r.role_name', 'a.created_at')
            ->get();

        foreach ($pending as $row) {
            DB::table('notifications')->insert([
                'expert_id' => $row->expert_id,
                'type' => 'expert_application',
                'title' => $row->team_name,
                'message' => 'asked you to be their '.str_replace('_', ' ', $row->role_name ?: 'expert'),
                'link' => '/instructor/applications',
                'is_read' => false,
                'created_at' => $row->created_at ?: new Expression('CURRENT_TIMESTAMP'),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');

        foreach (['capstone_experts', 'students'] as $table) {
            if (! Schema::hasColumn($table, 'notification_prefs')) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn('notification_prefs');
            });
        }

        DB::table('notifications')->whereNotNull('expert_id')->delete();

        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex(['expert_id', 'is_read']);
            $table->dropColumn('expert_id');
            $table->unsignedBigInteger('student_id')->nullable(false)->change();
        });
    }
};
