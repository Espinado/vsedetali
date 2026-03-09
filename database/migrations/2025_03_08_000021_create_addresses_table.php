<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20)->default('shipping')->index(); // shipping, billing
            $table->string('name', 255)->nullable();
            $table->string('full_address', 500);
            $table->string('city', 100)->index();
            $table->string('region', 100)->nullable();
            $table->string('postcode', 20)->nullable();
            $table->string('country', 2)->default('LV')->index();
            $table->string('phone', 50)->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};
