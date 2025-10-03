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
        Schema::table('service_parts', function (Blueprint $table) {
            $table->string('shopify_product_id')->nullable()->after('shopify_variant_gid');
            $table->string('product_title')->nullable()->after('shopify_product_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('service_parts', function (Blueprint $table) {
            $table->dropColumn(['shopify_product_id', 'product_title']);
        });
    }
};
