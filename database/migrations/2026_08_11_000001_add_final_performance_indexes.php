<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // notifications: the two most common queries are:
        //   1. latest 30 for a user:  WHERE notifiable_id = ? ORDER BY created_at DESC
        //   2. mark all read:         WHERE notifiable_id = ? AND read_at IS NULL
        // The existing single-column notifiable_id index doesn't cover read_at.
        Schema::table('notifications', function (Blueprint $table) {
            $table->index(['notifiable_id', 'read_at', 'created_at'], 'notif_user_read_created');
        });

        // advisory_notes: showByProgrammeEntry queries WHERE programme_entry_id = ?
        Schema::table('advisory_notes', function (Blueprint $table) {
            $table->index('programme_entry_id', 'an_programme_entry');
        });

        // permission_user: hasPermission loads direct permissions by user_id
        Schema::table('permission_user', function (Blueprint $table) {
            $table->index('user_id', 'pu_user');
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex('notif_user_read_created');
        });

        Schema::table('advisory_notes', function (Blueprint $table) {
            $table->dropIndex('an_programme_entry');
        });

        Schema::table('permission_user', function (Blueprint $table) {
            $table->dropIndex('pu_user');
        });
    }
};
