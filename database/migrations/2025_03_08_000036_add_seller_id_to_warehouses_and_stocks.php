<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warehouses', function (Blueprint $table) {
            $table->foreignId('seller_id')->nullable()->after('is_active')->constrained()->nullOnDelete();
        });

        Schema::table('stocks', function (Blueprint $table) {
            $table->foreignId('seller_id')->nullable()->after('reserved_quantity')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('warehouses', function (Blueprint $table) {
            $table->dropForeign(['seller_id']);
        });

        Schema::table('stocks', function (Blueprint $table) {
            $table->dropForeign(['seller_id']);
        });
    }
};
