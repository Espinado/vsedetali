<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20)->default('shipping')->index(); // shipping, billing
            $table->string('name', 255)->nullable();
            $table->string('full_address', 500);
            $table->string('city', 100);
            $table->string('region', 100)->nullable();
            $table->string('postcode', 20)->nullable();
            $table->string('country', 2)->default('LV');
            $table->string('phone', 50)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_addresses');
    }
};
