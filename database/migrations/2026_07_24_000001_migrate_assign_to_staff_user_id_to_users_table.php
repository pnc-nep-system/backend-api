<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('advisory_notes', function (Blueprint $table) {
            $table->dropForeign(['assign_to_staff_user_id']);
            $table->foreign('assign_to_staff_user_id')
                ->references('id')->on('users')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('advisory_notes', function (Blueprint $table) {
            $table->dropForeign(['assign_to_staff_user_id']);
            $table->foreign('assign_to_staff_user_id')
                ->references('id')->on('staff_users')
                ->onDelete('set null');
        });
    }
};
