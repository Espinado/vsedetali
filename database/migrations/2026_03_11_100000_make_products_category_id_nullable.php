<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropForeign(['category_id']);
            });

            DB::statement('ALTER TABLE products MODIFY category_id BIGINT UNSIGNED NULL');

            Schema::table('products', function (Blueprint $table) {
                $table->foreign('category_id')
                    ->references('id')
                    ->on('categories')
                    ->nullOnDelete();
            });

            return;
        }

        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropForeign(['category_id']);
            });

            DB::statement('ALTER TABLE products MODIFY category_id BIGINT UNSIGNED NOT NULL');

            Schema::table('products', function (Blueprint $table) {
                $table->foreign('category_id')
                    ->references('id')
                    ->on('categories')
                    ->cascadeOnDelete();
            });

            return;
        }

        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable(false)->change();
        });
    }
};
