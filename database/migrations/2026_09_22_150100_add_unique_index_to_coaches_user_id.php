<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nothing currently stops two Coach rows from pointing at the same User.
     * Enforce the one-to-one relationship at the schema level.
     */
    public function up(): void
    {
        Schema::table('coaches', function (Blueprint $table) {
            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('coaches', function (Blueprint $table) {
            $table->dropUnique(['user_id']);
        });
    }
};
