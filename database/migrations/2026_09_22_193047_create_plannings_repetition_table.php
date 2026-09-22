<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plannings_repetition', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('jour_semaine');
            $table->time('heure_debut');
            $table->foreignId('coach_id')->constrained()->restrictOnDelete();
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plannings_repetition');
    }
};
