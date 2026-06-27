<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('team_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sender_id')->constrained('students', 'student_id')->cascadeOnDelete();
            $table->foreignId('receiver_id')->constrained('students', 'student_id')->cascadeOnDelete();
            $table->foreignId('team_id')->nullable()->constrained('team', 'team_id')->cascadeOnDelete();
            $table->enum('type', ['invite', 'request']);
            $table->enum('status', ['pending', 'accepted', 'declined'])->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('team_invitations');
    }
};
