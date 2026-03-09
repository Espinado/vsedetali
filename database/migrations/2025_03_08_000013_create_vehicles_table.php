<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('make', 100)->index();
            $table->string('model', 100)->index();
            $table->string('generation', 100)->nullable()->index();
            $table->unsignedSmallInteger('year_from')->nullable()->index();
            $table->unsignedSmallInteger('year_to')->nullable()->index();
            $table->string('engine', 100)->nullable()->index();
            $table->string('body_type', 50)->nullable()->index();
            $table->timestamps();
        });

        Schema::table('vehicles', function (Blueprint $table) {
            $table->index(['make', 'model']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
