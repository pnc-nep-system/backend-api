<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('advisory_notes', function (Blueprint $table) {
            // Drop old foreign key
            $table->dropForeign(['assign_to_staff_user_id']);

            // Add new foreign key pointing to users table
            $table->foreign('assign_to_staff_user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('advisory_notes', function (Blueprint $table) {
            $table->dropForeign(['assign_to_staff_user_id']);

            $table->foreign('assign_to_staff_user_id')
                ->references('id')
                ->on('staff_users')
                ->nullOnDelete();
        });
    }
};
