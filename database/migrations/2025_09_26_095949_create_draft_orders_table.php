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
        Schema::create('draft_orders', function (Blueprint $table) {
            $table->id('draft_order_id');
            $table->string('order_id')->nullable()->comment('Shopify order ID when draft order is completed');
            $table->jsonb('payload')->comment('Draft order payload from Shopify');
            $table->string('shopify_draft_order_id')->comment('Shopify draft order GID');
            $table->string('invoice_url')->nullable()->comment('Shopify invoice URL');
            $table->string('status')->default('draft')->comment('draft, completed, cancelled');
            $table->decimal('total_price', 10, 2)->nullable()->comment('Total price of the draft order');
            $table->string('currency_code', 3)->default('USD')->comment('Currency code');
            $table->timestamps();
            
            // Indexes
            $table->index('shopify_draft_order_id');
            $table->index('order_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('draft_orders');
    }
};
