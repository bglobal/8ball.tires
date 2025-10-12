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
        Schema::table('bookings', function (Blueprint $table) {
            $table->unsignedBigInteger('draft_order_id')->nullable()->after('id');
            $table->foreign('draft_order_id')->references('draft_order_id')->on('draft_orders')->onDelete('set null');
            $table->index('draft_order_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['draft_order_id']);
            $table->dropIndex(['draft_order_id']);
            $table->dropColumn('draft_order_id');
        });
    }
};
