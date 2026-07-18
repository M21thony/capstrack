<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('team_expert_assignments', 'capstone_expert_application');
    }

    public function down(): void
    {
        Schema::rename('capstone_expert_application', 'team_expert_assignments');
    }
};
