<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seller_products', function (Blueprint $table): void {
            $table->decimal('cost_price', 12, 2)->nullable()->after('price');
            $table->string('oem_code', 100)->nullable()->after('quantity');
            $table->string('article', 100)->nullable()->after('oem_code');
            $table->unsignedSmallInteger('shipping_days')->nullable()->after('article');
        });
    }

    public function down(): void
    {
        Schema::table('seller_products', function (Blueprint $table): void {
            $table->dropColumn(['cost_price', 'oem_code', 'article', 'shipping_days']);
        });
    }
};
