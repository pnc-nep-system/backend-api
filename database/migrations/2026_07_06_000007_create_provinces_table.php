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
        Schema::create('provinces', function (Blueprint $table) {
            $table->id();
<<<<<<< HEAD:database/migrations/2026_07_06_090741_create_provinces_table.php
            $table->string('province_name')->unique();
=======
            $table->string('province_name');
>>>>>>> 0109f92088ea4df12bf0f97c8356b8d5fa57349b:database/migrations/2026_07_06_000007_create_provinces_table.php
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('provinces');
    }
};
