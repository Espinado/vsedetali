<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('code', 50)->nullable()->after('id')->index();
            $table->decimal('cost_price', 12, 2)->nullable()->after('price');
        });

        Schema::table('stocks', function (Blueprint $table) {
            $table->unsignedInteger('days_in_warehouse')->nullable()->after('reserved_quantity');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['code', 'cost_price']);
        });
        Schema::table('stocks', function (Blueprint $table) {
            $table->dropColumn('days_in_warehouse');
        });
    }
};
