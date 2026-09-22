<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pointages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seance_id')->constrained()->cascadeOnDelete();
            $table->string('pointable_type');
            $table->unsignedBigInteger('pointable_id');
            $table->dateTime('pointe_a')->nullable();
            $table->string('statut_ponctualite', 20);
            $table->integer('minutes_retard')->nullable();
            $table->string('source', 10);
            $table->foreignId('pointe_par_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('corrige_par_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('motif_correction')->nullable();
            $table->timestamps();

            $table->unique(['seance_id', 'pointable_type', 'pointable_id']);
            $table->index(['pointable_type', 'pointable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pointages');
    }
};
