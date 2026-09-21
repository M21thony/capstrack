<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Who the project is being built for, and who speaks for them.
     *
     * Nullable so the pre-proposals saved before these columns existed stay
     * readable - the form is what requires them, from here on.
     */
    public function up(): void
    {
        Schema::table('capstone_pre_proposals', function (Blueprint $table) {
            $table->text('clients')->nullable()->after('sdm');
            $table->text('client_address')->nullable()->after('clients');
            $table->string('client_representative')->nullable()->after('client_address');
            $table->string('client_position')->nullable()->after('client_representative');
        });
    }

    public function down(): void
    {
        Schema::table('capstone_pre_proposals', function (Blueprint $table) {
            $table->dropColumn(['clients', 'client_address', 'client_representative', 'client_position']);
        });
    }
};
