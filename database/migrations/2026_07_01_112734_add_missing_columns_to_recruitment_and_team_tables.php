<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('recruitment', 'member_count')) {
            Schema::table('recruitment', function (Blueprint $table): void {
                $table->tinyInteger('member_count')->unsigned()->nullable()->after('roles');
            });
        }

        if (! Schema::hasColumn('team', 'leader_id')) {
            Schema::table('team', function (Blueprint $table): void {
                $table->unsignedBigInteger('leader_id')->nullable()->after('team_id');
            });
        }

        if (! Schema::hasColumn('team', 'is_confirmed')) {
            Schema::table('team', function (Blueprint $table): void {
                $table->boolean('is_confirmed')->default(false)->after('team_status');
            });
        }
    }

    public function down(): void
    {
        Schema::table('recruitment', function (Blueprint $table): void {
            $table->dropColumn('member_count');
        });

        Schema::table('team', function (Blueprint $table): void {
            $table->dropColumn(['leader_id', 'is_confirmed']);
        });
    }
};
