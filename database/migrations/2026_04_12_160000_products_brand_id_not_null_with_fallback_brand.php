<?php

use App\Models\Brand;
use App\Models\Product;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $fallback = Brand::platformUnknownFallback();

        Product::query()->whereNull('brand_id')->update(['brand_id' => $fallback->id]);

        // MySQL: колонка NOT NULL несовместима с внешним ключом ON DELETE SET NULL — сначала снимаем FK.
        Schema::table('products', function (Blueprint $table): void {
            $table->dropForeign(['brand_id']);
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->unsignedBigInteger('brand_id')->nullable(false)->change();
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->foreign('brand_id')->references('id')->on('brands')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropForeign(['brand_id']);
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->unsignedBigInteger('brand_id')->nullable()->change();
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->foreign('brand_id')->references('id')->on('brands')->nullOnDelete();
        });
    }
};
