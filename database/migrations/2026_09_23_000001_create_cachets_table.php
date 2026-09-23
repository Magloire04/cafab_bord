<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cachets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prestation_id')->constrained()->restrictOnDelete();
            $table->foreignId('fille_id')->constrained()->restrictOnDelete();
            $table->decimal('montant', 10, 2);
            $table->string('statut', 20)->default('du');
            $table->timestamp('declaree_at')->nullable();
            $table->timestamp('validee_at')->nullable();
            $table->foreignId('valide_par_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('corrige_par_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('motif_correction')->nullable();
            $table->timestamp('depense_creee_at')->nullable();
            $table->string('caisse_cafab_reference')->nullable();
            $table->text('depense_erreur')->nullable();
            $table->timestamps();

            $table->unique(['prestation_id', 'fille_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cachets');
    }
};
