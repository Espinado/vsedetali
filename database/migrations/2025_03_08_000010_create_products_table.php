<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->string('sku', 100)->unique();
            $table->string('name', 500)->index();
            $table->string('slug', 500)->unique();
            $table->text('description')->nullable();
            $table->string('short_description', 1000)->nullable();
            $table->decimal('weight', 10, 3)->nullable();
            $table->decimal('price', 12, 2)->default(0)->index();
            $table->decimal('vat_rate', 5, 2)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->string('type', 20)->default('part')->index(); // part, consumable, accessory
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
