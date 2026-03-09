<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('path', 500);
            $table->string('alt', 255)->nullable();
            $table->unsignedSmallInteger('sort')->default(0)->index();
            $table->boolean('is_main')->default(false);
            $table->timestamps();
        });

        Schema::table('product_images', function (Blueprint $table) {
            $table->index(['product_id', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_images');
    }
};
