<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_blocks', function (Blueprint $table) {
            $table->id();
            $table->string('type', 16);
            $table->string('value', 255);
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->unique(['type', 'value']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_blocks');
    }
};
