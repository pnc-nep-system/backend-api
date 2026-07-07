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
        Schema::create('districts', function (Blueprint $table) {
            $table->id();
<<<<<<< HEAD:database/migrations/2026_07_06_074255_create_districts_table.php
            $table->unsignedBigInteger('province_id')->index();
            $table->string('name');
=======
            $table->foreignId('province_id')->constrained('provinces')->onDelete('cascade');
            $table->string('district_name');
>>>>>>> 0109f92088ea4df12bf0f97c8356b8d5fa57349b:database/migrations/2026_07_06_000008_create_districts_table.php
            $table->timestamps();

            // No FK constraint (optional). If you want FK, ensure provinces.id exists and rerun migrations.
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('districts');
    }
};
