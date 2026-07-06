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
        Schema::create('government_agreements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('programme_entry_id')->constrained('programme_entries')->onDelete('cascade');
            $table->string('counterpart_agency');
            $table->string('status');
            $table->string('institution_name');
            $table->string('nature');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('government_agreements');
    }
};
