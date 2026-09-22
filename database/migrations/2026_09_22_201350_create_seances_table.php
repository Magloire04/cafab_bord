<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('planning_repetition_id')->nullable()->constrained('plannings_repetition')->nullOnDelete();
            $table->foreignId('coach_id')->constrained()->restrictOnDelete();
            $table->date('date');
            $table->time('heure_prevue');
            $table->string('type', 20);
            $table->string('statut', 20)->default('a_venir');
            $table->timestamp('cloturee_at')->nullable();
            $table->foreignId('cloture_par_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['planning_repetition_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seances');
    }
};
