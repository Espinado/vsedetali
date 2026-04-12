<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_vehicle', function (Blueprint $table) {
            $table->unsignedSmallInteger('compat_year_from')->nullable()->after('oem_number');
            $table->unsignedSmallInteger('compat_year_to')->nullable()->after('compat_year_from');
        });
    }

    public function down(): void
    {
        Schema::table('product_vehicle', function (Blueprint $table) {
            $table->dropColumn(['compat_year_from', 'compat_year_to']);
        });
    }
};
