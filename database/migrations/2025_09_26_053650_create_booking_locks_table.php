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
        Schema::create('booking_locks', function (Blueprint $table) {
            $table->string('slot_key')->primary();
            $table->boolean('taken')->default(false);
            $table->timestamp('created_at')->useCurrent();
            
            $table->index(['slot_key', 'taken']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_locks');
    }
};
