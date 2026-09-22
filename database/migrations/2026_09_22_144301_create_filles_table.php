<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('filles', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->string('prenom');
            $table->string('contact')->nullable();
            $table->string('pin', 4)->unique();
            $table->string('statut', 20)->default('actif');
            $table->date('date_entree');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('filles');
    }
};
