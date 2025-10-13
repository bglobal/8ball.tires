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
        Schema::create('location_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained()->onDelete('cascade');
            $table->time('open_time');
            $table->time('close_time');
            $table->boolean('is_weekend_open')->default(false);
            $table->time('weekend_open_time')->nullable();
            $table->time('weekend_close_time')->nullable();
            $table->integer('capacity_per_slot');
            $table->integer('slot_duration_minutes')->default(60);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('location_settings');
    }
};
