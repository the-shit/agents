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
        Schema::create('agent_identities', function (Blueprint $table) {
            $table->id();
            $table->string('agent_id')->unique();
            $table->string('name');
            $table->string('role'); // MUSIC, INTAKE, ATTEMPT, SUBMISSION, VALIDATION, DELIVERY, MEMORY, ANALYSIS
            $table->json('capabilities');
            $table->string('status')->default('idle'); // active, idle, blocked
            $table->timestamp('last_active_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_identities');
    }
};
