<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_cross_numbers', function (Blueprint $table) {
            $table->string('manufacturer_name', 255)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('product_cross_numbers', function (Blueprint $table) {
            $table->dropColumn('manufacturer_name');
        });
    }
};
