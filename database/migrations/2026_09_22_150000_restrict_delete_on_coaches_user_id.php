<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A coach's roster row must never disappear as a side effect of deleting
     * the linked user account (accounts are deactivated via `statut`, never
     * deleted). Replace the cascading delete with a restrictive one so the
     * database itself blocks that class of bug.
     */
    public function up(): void
    {
        Schema::table('coaches', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('coaches', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('coaches', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('coaches', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
