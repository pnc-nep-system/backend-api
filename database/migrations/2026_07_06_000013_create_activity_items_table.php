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
        Schema::create('activity_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subcategory_id')->constrained('activity_subcategories')->onDelete('cascade');
            $table->string('code')->unique();
            $table->string('label');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_other')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_items');
    }
};
