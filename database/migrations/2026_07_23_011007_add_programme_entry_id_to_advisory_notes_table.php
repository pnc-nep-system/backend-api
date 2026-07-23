<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('advisory_notes', function (Blueprint $table) {
            $table->foreignId('programme_entry_id')
                ->nullable()
                ->constrained('programme_entries')
                ->nullOnDelete()
                ->after('id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('advisory_notes', function (Blueprint $table) {
            $table->dropForeign(['programme_entry_id']);
            $table->dropColumn('programme_entry_id');
        });
    }
};